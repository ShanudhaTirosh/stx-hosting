import { ShardingManager } from 'discord.js';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { env } from './config/env.js';
import { logger } from './utils/logger.js';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const manager = new ShardingManager(path.join(__dirname, 'index.js'), {
  token: env.DISCORD_BOT_TOKEN,
  totalShards: 'auto',
  respawn: true,
});

manager.on('shardCreate', (shard) => {
  logger.info({ shardId: shard.id }, `⚡ Launched Discord Shard #${shard.id}`);

  shard.on('disconnect', () => {
    logger.warn({ shardId: shard.id }, `Shard #${shard.id} disconnected.`);
  });

  shard.on('reconnecting', () => {
    logger.info({ shardId: shard.id }, `Shard #${shard.id} reconnecting...`);
  });

  shard.on('death', (process: any) => {
    logger.error({ shardId: shard.id, exitCode: process?.exitCode }, `Shard #${shard.id} died.`);
  });
});

manager.spawn().catch((err) => {
  logger.fatal({ err }, 'Failed to spawn sharding manager');
  process.exit(1);
});
