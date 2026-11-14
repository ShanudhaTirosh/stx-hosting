import express, { Request, Response } from 'express';
import dotenv from 'dotenv';
import pino from 'pino';
import path from 'path';
import { fileURLToPath } from 'url';
import QRCode from 'qrcode';
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
  try {
    const result = await manager.triggerSync();
    res.json({ success: true, count: result.transactions.length, statement: result });
  } catch (err: any) {
    res.status(500).json({ success: false, error: err.message });
  }
});

app.post('/sync-on-demand', authMiddleware, async (req: Request, res: Response) => {
  try {
    const result = await manager.triggerSync();
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
  res.json({ delivered, awaiting: manager.getStatus().awaitingOtp });
});

// Periodic sync scheduler (default: every 60 minutes / 1 hour)
const SYNC_INTERVAL_MINUTES = parseInt(process.env.SYNC_INTERVAL_MINUTES || '60', 10);
if (SYNC_INTERVAL_MINUTES > 0) {
  logger.info(`Scheduled auto-sync enabled: every ${SYNC_INTERVAL_MINUTES} minutes (1 hour)`);
  setInterval(async () => {
    const status = manager.getStatus();
    if (status.connected && status.state === 'IDLE') {
      try {
        logger.info('Running scheduled ComBank statement sync...');
        await manager.triggerSync();
      } catch (e: any) {
        logger.warn(`Scheduled sync failed: ${e.message}`);
      }
    }
  }, SYNC_INTERVAL_MINUTES * 60 * 1000);
}

app.listen(PORT, '0.0.0.0', () => {
  logger.info(`PayVerify WhatsApp Bot service running on http://0.0.0.0:${PORT}`);
  manager.start().catch((err: any) => {
    logger.error(`Failed to start WhatsApp manager: ${err.message}`);
  });
});
