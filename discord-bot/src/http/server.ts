import http from 'node:http';
import { Client } from 'discord.js';
import { env } from '../config/env.js';
import { logger } from '../utils/logger.js';
import { taskQueue } from '../queue/taskQueue.js';
import { processPaidInvoiceNotification } from '../services/invoiceService.js';

export function startHttpServer(client: Client): http.Server {
  const server = http.createServer(async (req, res) => {
    const url = new URL(req.url || '/', `http://${req.headers.host}`);

    // 1. Health check endpoint
    if (req.method === 'GET' && url.pathname === '/health') {
      const ping = client.ws.ping;
      const responseData = {
        status: client.isReady() ? 'ok' : 'initializing',
        uptime: process.uptime(),
        ping_ms: ping,
        guilds_count: client.guilds.cache.size,
        memory: process.memoryUsage(),
        timestamp: new Date().toISOString(),
      };
      res.writeHead(200, { 'Content-Type': 'application/json' });
      res.end(JSON.stringify(responseData));
      return;
    }

    // 2. Invoice Paid Webhook endpoint
    if (req.method === 'POST' && url.pathname === '/api/webhook/invoice-paid') {
      const authHeader = req.headers['authorization'] || req.headers['x-bot-secret'];
      const secret = authHeader?.toString().replace(/^Bearer\s+/i, '');

      if (!secret || secret !== env.BOT_INTERNAL_SECRET) {
        res.writeHead(401, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({ error: 'Unauthorized: Invalid secret' }));
        return;
      }

      let body = '';
      req.on('data', (chunk) => (body += chunk));
      req.on('end', async () => {
        try {
          const payload = JSON.parse(body);
          if (!payload.invoice_number || !payload.platform_user_id) {
            res.writeHead(400, { 'Content-Type': 'application/json' });
            res.end(JSON.stringify({ error: 'Missing required fields: invoice_number, platform_user_id' }));
            return;
          }

          taskQueue.add(`invoice-${payload.invoice_number}`, async () => {
            await processPaidInvoiceNotification(client, payload);
          });

          res.writeHead(202, { 'Content-Type': 'application/json' });
          res.end(JSON.stringify({ status: 'queued', invoice_number: payload.invoice_number }));
        } catch (err: any) {
          logger.error({ err }, 'Error parsing invoice webhook payload');
          res.writeHead(400, { 'Content-Type': 'application/json' });
          res.end(JSON.stringify({ error: 'Invalid JSON payload' }));
        }
      });
      return;
    }

    res.writeHead(404, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify({ error: 'Not Found' }));
  });

  server.listen(env.PORT, () => {
    logger.info({ port: env.PORT }, `HTTP Health Check & Webhook Server running on port ${env.PORT}`);
  });

  return server;
}
