import { createDiscordClient } from './client.js';
import { startHttpServer } from './http/server.js';
import { env } from './config/env.js';
import { logger } from './utils/logger.js';

async function bootstrap() {
  logger.info({ nodeEnv: env.NODE_ENV }, 'Starting STX Enterprises Discord Bot service...');

  const client = createDiscordClient();

  // Start internal HTTP server for health checks & webhook events
  const server = startHttpServer(client);

  // Graceful shutdown handler
  const shutdown = async (signal: string) => {
    logger.info({ signal }, 'Received shutdown signal. Gracefully disconnecting...');
    server.close(() => {
      logger.info('HTTP server closed.');
    });
    client.destroy();
    logger.info('Discord client destroyed. Clean exit.');
    process.exit(0);
  };

  process.on('SIGTERM', () => shutdown('SIGTERM'));
  process.on('SIGINT', () => shutdown('SIGINT'));

  try {
    await client.login(env.DISCORD_BOT_TOKEN);
  } catch (err: any) {
    logger.fatal({ err: err.message }, 'Failed to login to Discord Gateway with token.');
    if (env.NODE_ENV !== 'test') {
      process.exit(1);
    }
  }
}

bootstrap().catch((err) => {
  logger.fatal({ err }, 'Fatal bootstrap failure');
  process.exit(1);
});
