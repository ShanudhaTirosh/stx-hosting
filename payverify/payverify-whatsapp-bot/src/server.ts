import express, { Request, Response } from 'express';
import dotenv from 'dotenv';
import pino from 'pino';
import path from 'path';
import { fileURLToPath } from 'url';
import QRCode from 'qrcode';
import { proto } from '@whiskeysockets/baileys';
import { ComBankSessionManager } from './combankSession.js';

dotenv.config();

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const logger = pino({ level: process.env.LOG_LEVEL || 'info' });
const app = express();
app.use(express.json());

const PORT = parseInt(process.env.PORT || '3100', 10);
const AUTH_DIR = process.env.WHATSAPP_AUTH_DIR || path.join(__dirname, '../auth_info');
const COMBANK_NUMBER = process.env.PV_COMBANK_WHATSAPP_NUMBER || '';
const COMBANK_NIC = process.env.PV_COMBANK_NIC || '';
const PAYVERIFY_API_URL = process.env.PV_API_URL || 'http://localhost:8000';
const BOT_SECRET = process.env.PV_BOT_SECRET || '';

// Authorized Admin WhatsApp numbers (e.g. 94771234567, 94719876543)
const ADMIN_NUMBERS = (process.env.PV_ADMIN_WHATSAPP_NUMBERS || '')
  .split(',')
  .map(s => s.replace(/[^0-9]/g, ''))
  .filter(Boolean);

const manager = new ComBankSessionManager({
  authStateFolder: AUTH_DIR,
  combankNumber: COMBANK_NUMBER,
  merchantNic: COMBANK_NIC,
  payverifyApiUrl: PAYVERIFY_API_URL,
  botSecret: BOT_SECRET
});

// Middleware for bot secret verification
function authMiddleware(req: Request, res: Response, next: () => void) {
  if (!BOT_SECRET) return next();
  const secret = req.headers['x-bot-secret'] || req.query.secret;
  if (secret !== BOT_SECRET) {
    return res.status(401).json({ error: 'Unauthorized' });
  }
  next();
}

app.get('/status', (req: Request, res: Response) => {
  res.json(manager.getStatus());
});

app.get('/qr', async (req: Request, res: Response) => {
  const status = manager.getStatus();
  if (status.connected) {
    return res.json({ connected: true, qr: null, qr_image: null, message: 'Already connected' });
  }
  let qrImage: string | null = null;
  if (status.qrCode) {
    try {
      qrImage = await QRCode.toDataURL(status.qrCode, { margin: 2, width: 280 });
    } catch {
      qrImage = null;
    }
  }
  res.json({ connected: false, qr: status.qrCode, qr_image: qrImage });
});

app.post('/pairing-code', authMiddleware, async (req: Request, res: Response) => {
  const { phoneNumber } = req.body;
  if (!phoneNumber || typeof phoneNumber !== 'string') {
    return res.status(400).json({ error: 'Missing or invalid phoneNumber' });
  }
  try {
    const code = await manager.requestPairingCode(phoneNumber);
    res.json({ success: true, code, phoneNumber: phoneNumber.replace(/[^0-9]/g, '') });
  } catch (err: any) {
    res.status(500).json({ success: false, error: err.message });
  }
});

app.post('/sync', authMiddleware, async (req: Request, res: Response) => {
  const force = req.query.force === 'true' || req.body?.force === true;
  try {
    const result = await manager.triggerSync(force);
    res.json({ success: true, count: result.transactions.length, statement: result });
  } catch (err: any) {
    res.status(500).json({ success: false, error: err.message });
  }
});

app.post('/sync-on-demand', authMiddleware, async (req: Request, res: Response) => {
  try {
    const result = await manager.triggerSync(true);
    res.json({ success: true, count: result.transactions.length, statement: result });
  } catch (err: any) {
    res.status(500).json({ success: false, error: err.message });
  }
});

app.post('/otp', authMiddleware, (req: Request, res: Response) => {
  const { otp } = req.body;
  if (!otp || typeof otp !== 'string') {
    return res.status(400).json({ error: 'Missing or invalid OTP' });
  }
  const delivered = manager.deliverOtp(otp);
  res.json({ delivered, awaiting: manager.getStatus().awaitingOtp, buffered: manager.getStatus().bufferedOtpCount });
});

function formatMoney(minor: number | null | undefined): string {
  if (minor == null) return 'N/A';
  return `LKR ${(minor / 100).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

// Handler for Authorized Admin WhatsApp commands
async function handleAdminCommand(msg: proto.IWebMessageInfo): Promise<void> {
  const senderJid = msg.key?.remoteJid;
  if (!senderJid || senderJid.includes('@g.us')) return; // ignore group messages

  const digits = senderJid.replace(/[^0-9]/g, '');
  if (COMBANK_NUMBER && digits.includes(COMBANK_NUMBER)) return; // bank message handled by session manager

  // If ADMIN_NUMBERS is configured, check allowlist
  if (ADMIN_NUMBERS.length > 0) {
    const isAllowlisted = ADMIN_NUMBERS.some(n => digits.endsWith(n) || n.endsWith(digits));
    if (!isAllowlisted) return;
  }

  const rawText = msg.message?.conversation || msg.message?.extendedTextMessage?.text || '';
  const text = rawText.trim().toLowerCase();
  if (!text) return;

  logger.info(`Received WhatsApp command from authorized admin [${senderJid}]: ${text}`);

  if (text === 'last 3' || text === 'statement' || text === 'tx' || text === 'transactions') {
    try {
      const stmt = await manager.triggerSync(false);
      let reply = `🏦 *ComBank Statement Summary*\n`;
      if (stmt.accountMask) reply += `Account: ${stmt.accountMask}\n`;
      if (stmt.currentBalanceMinor != null) reply += `Current Balance: ${formatMoney(stmt.currentBalanceMinor)}\n`;
      if (stmt.availableBalanceMinor != null) reply += `Available Balance: ${formatMoney(stmt.availableBalanceMinor)}\n`;
      reply += `\n*Recent Transactions:*\n`;

      if (stmt.transactions.length === 0) {
        reply += `_No recent transactions found._`;
      } else {
        stmt.transactions.forEach((t, i) => {
          const dirIcon = t.direction === 'credit' ? '🟢 CR' : '🔴 DR';
          reply += `\n*${i + 1}.* ${t.valueDate} | *${formatMoney(t.amountMinor)}* [${dirIcon}]\n`;
          reply += `   ${t.remarks}\n`;
          if (t.ref) reply += `   _Ref: ${t.ref}_\n`;
        });
      }

      await manager.sendMessage(senderJid, reply);
    } catch (e: any) {
      await manager.sendMessage(senderJid, `❌ Failed to fetch transactions: ${e.message}`);
    }
  } else if (text === 'status') {
    const st = manager.getStatus();
    const reply = `📊 *PayVerify WhatsApp Bot Status*\n\n` +
      `• *Connection:* ${st.connected ? '🟢 ONLINE' : '🔴 OFFLINE'}\n` +
      `• *Session State:* \`${st.state}\`\n` +
      `• *Awaiting OTP:* ${st.awaitingOtp ? '⚠️ YES' : 'NO'}\n` +
      `• *Buffered OTPs:* ${st.bufferedOtpCount}\n` +
      `• *Last Sync:* ${st.lastSyncAt ? new Date(st.lastSyncAt).toLocaleString() : 'Never'}\n` +
      (st.lastError ? `• *Last Error:* _${st.lastError}_\n` : '');
    await manager.sendMessage(senderJid, reply);
  } else if (text === 'refresh' || text === 'sync') {
    await manager.sendMessage(senderJid, `🔄 Triggering fresh ComBank sync... please wait.`);
    try {
      const res = await manager.triggerSync(true);
      await manager.sendMessage(
        senderJid,
        `✅ *Sync Complete!*\nRetrieved ${res.transactions.length} transaction(s).\nBalance: ${formatMoney(res.currentBalanceMinor)}`
      );
    } catch (e: any) {
      await manager.sendMessage(senderJid, `❌ Refresh failed: ${e.message}`);
    }
  } else if (text === 'help') {
    const reply = `🤖 *PayVerify Admin Bot Commands*\n\n` +
      `• *last 3* - Retrieve your last 3 bank transactions\n` +
      `• *status* - Check bot connection & relay health\n` +
      `• *refresh* - Trigger an immediate fresh sync with ComBank\n` +
      `• *help* - Show this command menu`;
    await manager.sendMessage(senderJid, reply);
  }
}

// Periodic sync scheduler (default: every 60 minutes)
const SYNC_INTERVAL_MINUTES = parseInt(process.env.SYNC_INTERVAL_MINUTES || '60', 10);
if (SYNC_INTERVAL_MINUTES > 0) {
  logger.info(`Scheduled auto-sync enabled: every ${SYNC_INTERVAL_MINUTES} minutes (1 hour)`);
  setInterval(async () => {
    const status = manager.getStatus();
    if (status.connected && status.state === 'IDLE') {
      try {
        logger.info('Running scheduled ComBank statement sync...');
        await manager.triggerSync(false);
      } catch (e: any) {
        logger.warn(`Scheduled sync failed: ${e.message}`);
      }
    }
  }, SYNC_INTERVAL_MINUTES * 60 * 1000);
}

app.listen(PORT, '0.0.0.0', () => {
  logger.info(`PayVerify WhatsApp Bot service running on http://0.0.0.0:${PORT}`);
  manager.start(handleAdminCommand).catch((err: any) => {
    logger.error(`Failed to start WhatsApp manager: ${err.message}`);
  });
});
