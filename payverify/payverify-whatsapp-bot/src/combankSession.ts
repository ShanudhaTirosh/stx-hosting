import makeWASocket, {
  DisconnectReason,
  useMultiFileAuthState,
  WASocket,
  proto
} from '@whiskeysockets/baileys';
import pino from 'pino';
import axios from 'axios';
import { parseComBankStatement, ParsedComBankStatement, ParsedBankTransaction } from './parser.js';

const logger = pino({ level: process.env.LOG_LEVEL || 'info' });

export type SessionState =
  | 'IDLE'
  | 'INIT'
  | 'LANG_SELECT'
  | 'TERMS_ACCEPT'
  | 'MENU_SELECT'
  | 'NIC_INPUT'
  | 'AWAITING_OTP'
  | 'OTP_SUBMITTED'
  | 'HISTORY_RECEIVED'
  | 'EXITED'
  | 'ERROR';

export interface BotStatus {
  connected: boolean;
  qrCode: string | null;
  pairingCode: string | null;
  state: SessionState;
  lastSyncAt: string | null;
  lastError: string | null;
  awaitingOtp: boolean;
}

export class ComBankSessionManager {
  private sock: WASocket | null = null;
  private authStateFolder: string;
  private combankNumber: string;
  private merchantNic: string;
  private payverifyApiUrl: string;
  private botSecret: string;

  private connected: boolean = false;
  private currentQr: string | null = null;
  private currentPairingCode: string | null = null;
  private state: SessionState = 'IDLE';
  private lastSyncAt: string | null = null;
  private lastError: string | null = null;

  private otpResolver: ((otp: string) => void) | null = null;
  private otpRejecter: ((err: Error) => void) | null = null;
  private syncTimeout: NodeJS.Timeout | null = null;

  constructor(options: {
    authStateFolder: string;
    combankNumber: string;
    merchantNic: string;
    payverifyApiUrl: string;
    botSecret?: string;
  }) {
    this.authStateFolder = options.authStateFolder;
    this.combankNumber = options.combankNumber.replace(/[^0-9]/g, '');
    this.merchantNic = options.merchantNic.trim();
    this.payverifyApiUrl = options.payverifyApiUrl.replace(/\/+$/, '');
    this.botSecret = options.botSecret || '';
  }

  public getStatus(): BotStatus {
    return {
      connected: this.connected,
      qrCode: this.currentQr,
      pairingCode: this.currentPairingCode,
      state: this.state,
      lastSyncAt: this.lastSyncAt,
      lastError: this.lastError,
      awaitingOtp: this.state === 'AWAITING_OTP'
    };
  }

  public async requestPairingCode(phoneNumber: string): Promise<string> {
    if (!this.sock) {
      throw new Error('WhatsApp socket is not initialized yet');
    }
    if (this.connected) {
      throw new Error('WhatsApp is already connected and authenticated');
    }
    const cleanNumber = phoneNumber.replace(/[^0-9]/g, '');
    if (cleanNumber.length < 8) {
      throw new Error('Invalid phone number: must be at least 8 digits with country code (e.g. 94771234567)');
    }

    logger.info(`Requesting WhatsApp pairing code for ${cleanNumber.substring(0, 4)}****`);
    const rawCode = await this.sock.requestPairingCode(cleanNumber);
    let formatted = rawCode;
    if (rawCode && rawCode.length === 8) {
      formatted = `${rawCode.substring(0, 4)}-${rawCode.substring(4)}`;
    }
    this.currentPairingCode = formatted;
    return formatted;
  }

  public async start(): Promise<void> {
    const { state, saveCreds } = await useMultiFileAuthState(this.authStateFolder);

    this.sock = makeWASocket({
      auth: state,
      printQRInTerminal: true,
      logger: pino({ level: 'warn' }),
      browser: ['PayVerify Sync', 'Chrome', '120.0.0']
    });

    this.sock.ev.on('creds.update', saveCreds);

    this.sock.ev.on('connection.update', (update) => {
      const { connection, lastDisconnect, qr } = update;

      if (qr) {
        this.currentQr = qr;
        logger.info('New WhatsApp QR code received. Scan with bank-registered WhatsApp.');
      }

      if (connection === 'close') {
        this.connected = false;
        const statusCode = (lastDisconnect?.error as any)?.output?.statusCode;
        const shouldReconnect = statusCode !== DisconnectReason.loggedOut;
        logger.warn(`WhatsApp connection closed (statusCode: ${statusCode}). Reconnecting: ${shouldReconnect}`);
        if (shouldReconnect) {
          setTimeout(() => this.start(), 5000);
        }
      } else if (connection === 'open') {
        this.connected = true;
        this.currentQr = null;
        logger.info('WhatsApp connection open and authenticated!');
      }
    });

    this.sock.ev.on('messages.upsert', async (m) => {
      if (m.type !== 'notify') return;
      for (const msg of m.messages) {
        if (msg.key?.fromMe) continue;
        await this.handleIncomingMessage(msg);
      }
    });
  }

  private getComBankJid(): string {
    return `${this.combankNumber}@s.whatsapp.net`;
  }

  private async sendMessage(text: string): Promise<void> {
    if (!this.sock || !this.connected) {
      throw new Error('WhatsApp socket is not connected');
    }
    const jid = this.getComBankJid();
    logger.info(`Sending message to ComBank (${jid}): ${text}`);
    await this.sock.sendMessage(jid, { text });
  }

  public deliverOtp(otp: string): boolean {
    const cleanOtp = otp.trim();
    if (this.state === 'AWAITING_OTP' && this.otpResolver) {
      logger.info('ComBank OTP delivered to session resolver (redacted)');
      this.otpResolver(cleanOtp);
      this.otpResolver = null;
      this.otpRejecter = null;
      return true;
    }
    logger.warn('OTP received but session was not awaiting OTP');
    return false;
  }

  public async triggerSync(): Promise<ParsedComBankStatement> {
    if (!this.connected) {
      throw new Error('WhatsApp bot is not connected. Scan QR code first.');
    }
    if (this.state !== 'IDLE' && this.state !== 'EXITED' && this.state !== 'ERROR') {
      throw new Error(`Session already in progress (current state: ${this.state})`);
    }
    if (!this.combankNumber) {
      throw new Error('ComBank WhatsApp number not configured');
    }
    if (!this.merchantNic) {
      throw new Error('Merchant NIC not configured');
    }

    logger.info('Triggering ComBank WhatsApp statement sync...');
    this.state = 'INIT';
    this.lastError = null;

    return new Promise<ParsedComBankStatement>(async (resolve, reject) => {
      // Set session timeout of 120 seconds
      if (this.syncTimeout) clearTimeout(this.syncTimeout);
      this.syncTimeout = setTimeout(() => {
        if (this.state !== 'IDLE' && this.state !== 'EXITED') {
          this.state = 'ERROR';
          this.lastError = 'Session timeout waiting for ComBank response';
          logger.error(this.lastError);
          reject(new Error(this.lastError));
        }
      }, 120000);

      try {
        await this.sendMessage('hi');
      } catch (err: any) {
        this.state = 'ERROR';
        this.lastError = err.message;
        reject(err);
      }

      const checkFinish = setInterval(() => {
        if (this.state === 'HISTORY_RECEIVED') {
          clearInterval(checkFinish);
          if (this.syncTimeout) clearTimeout(this.syncTimeout);
          resolve(this.lastParsedStatement!);
        } else if (this.state === 'ERROR') {
          clearInterval(checkFinish);
          if (this.syncTimeout) clearTimeout(this.syncTimeout);
          reject(new Error(this.lastError || 'Sync failed'));
        }
      }, 500);
    });
  }

  private lastParsedStatement: ParsedComBankStatement | null = null;

  private async handleIncomingMessage(msg: proto.IWebMessageInfo): Promise<void> {
    const sender = msg.key?.remoteJid;

    // Check if message is from ComBank
    if (!sender || !sender.includes(this.combankNumber)) {
      return;
    }

    const text =
      msg.message?.conversation ||
      msg.message?.extendedTextMessage?.text ||
      '';

    if (!text.trim()) return;

    logger.info(`Incoming ComBank message [state: ${this.state}]: ${text.substring(0, 100).replace(/\n/g, ' ')}...`);

    try {
      // 1. Language Selection prompt
      if (text.includes('Please select the language you prefer') || text.includes('1. English')) {
        this.state = 'LANG_SELECT';
        await new Promise((r) => setTimeout(r, 1000));
        await this.sendMessage('1'); // Select English
        return;
      }

      // 2. Terms & Conditions
      if (text.includes('Welcome to ComBank WhatsApp Banking') || text.includes('Terms & Conditions')) {
        this.state = 'TERMS_ACCEPT';
        await new Promise((r) => setTimeout(r, 1000));
        await this.sendMessage('1'); // Accept
        return;
      }

      // 3. Service Menu selection
      if (text.includes('Please select one of the listed services') || text.includes('2. Account History')) {
        this.state = 'MENU_SELECT';
        await new Promise((r) => setTimeout(r, 1000));
        await this.sendMessage('2'); // Account History
        return;
      }

      // 4. NIC or Passport prompt
      if (
        text.includes('National Identity Card') ||
        text.includes('Passport Number')
      ) {
        this.state = 'NIC_INPUT';
        await new Promise((r) => setTimeout(r, 1000));
        await this.sendMessage(this.merchantNic);
        return;
      }

      // 5. OTP prompt
      if (
        text.includes('One Time Password') ||
        text.includes('sent via SMS to your registered mobile number')
      ) {
        this.state = 'AWAITING_OTP';
        logger.info('ComBank requested OTP. Waiting for Android relay...');

        // Notify PayVerify backend that OTP is awaited
        this.notifyBackendOtpAwaited().catch((e: any) =>
          logger.warn(`Failed to notify backend: ${e.message}`)
        );

        const otpPromise = new Promise<string>((res, rej) => {
          this.otpResolver = res;
          this.otpRejecter = rej;
          // 60s timeout for OTP delivery
          setTimeout(() => {
            if (this.state === 'AWAITING_OTP') {
              rej(new Error('OTP wait timeout (60s)'));
            }
          }, 60000);
        });

        try {
          const otp = await otpPromise;
          this.state = 'OTP_SUBMITTED';
          await new Promise((r) => setTimeout(r, 1000));
          await this.sendMessage(otp);
        } catch (otpErr: any) {
          this.state = 'ERROR';
          this.lastError = otpErr.message;
          logger.error(`OTP wait error: ${otpErr.message}`);
        }
        return;
      }

      // 6. Statement History Received
      if (text.includes('Account :') && (text.includes('DEPOSIT') || text.includes('WITHDRAWAL') || text.includes('TX CREDIT') || text.includes('Thank you for WhatsApp Banking'))) {
        logger.info('ComBank statement received! Parsing records...');
        const parsed = parseComBankStatement(text);
        this.lastParsedStatement = parsed;
        this.lastSyncAt = new Date().toISOString();
        this.state = 'HISTORY_RECEIVED';

        // Exit cleanly
        setTimeout(async () => {
          try {
            await this.sendMessage('2'); // Exit
            this.state = 'EXITED';
          } catch (e) {
            // ignore exit send error
          }
        }, 1500);

        // Forward parsed transactions to PayVerify API
        await this.forwardStatementToBackend(parsed);
        return;
      }

      // Handle "One moment... your request is being processed."
      if (text.includes('One moment... your request is being processed')) {
        // Informational, no action required
        return;
      }
    } catch (err: any) {
      logger.error(`Error during conversation state handling: ${err.message}`);
      this.state = 'ERROR';
      this.lastError = err.message;
    }
  }

  private async notifyBackendOtpAwaited(): Promise<void> {
    try {
      await axios.post(
        `${this.payverifyApiUrl}/api/v1/bank-sync/session-awaiting-otp`,
        { awaiting: true, expires_in_seconds: 60 },
        { headers: this.getAuthHeaders() }
      );
    } catch (err: any) {
      // Backend may be offline or endpoint optional
    }
  }

  private async forwardStatementToBackend(
    statement: ParsedComBankStatement
  ): Promise<void> {
    logger.info(`Sending ${statement.transactions.length} transactions to PayVerify backend...`);
    try {
      const resp = await axios.post(
        `${this.payverifyApiUrl}/api/v1/bank-sync/whatsapp-history`,
        {
          account_mask: statement.accountMask,
          account_tail: statement.accountTail,
          current_balance_minor: statement.currentBalanceMinor,
          available_balance_minor: statement.availableBalanceMinor,
          transactions: statement.transactions.map((t: ParsedBankTransaction) => ({
            value_date: t.valueDate,
            value_dt: t.valueDt,
            remarks: t.remarks,
            amount_minor: t.amountMinor,
            direction: t.direction,
            branch: t.branch,
            channel: t.channel,
            raw: t.raw
          }))
        },
        { headers: this.getAuthHeaders() }
      );
      logger.info(`Backend ingestion result: ${JSON.stringify(resp.data)}`);
    } catch (err: any) {
      logger.error(`Failed to post history to PayVerify API: ${err.message}`);
    }
  }

  private getAuthHeaders(): Record<string, string> {
    const headers: Record<string, string> = {
      'Content-Type': 'application/json'
    };
    if (this.botSecret) {
      headers['X-Bot-Secret'] = this.botSecret;
    }
    return headers;
  }
}
