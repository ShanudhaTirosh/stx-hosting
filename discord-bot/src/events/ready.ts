import { Client, ActivityType, REST, Routes } from 'discord.js';
import { getAllCommands } from '../commands/index.js';
import { env } from '../config/env.js';
import { logger } from '../utils/logger.js';

export async function onReady(client: Client): Promise<void> {
  logger.info({ user: client.user?.tag, id: client.user?.id }, '🚀 STX Discord Bot is online and connected to Discord Gateway!');

  // Set rich presence
  client.user?.setPresence({
    activities: [
      {
        name: 'STX Cloud Platform | /help',
        type: ActivityType.Watching,
      },
    ],
    status: 'online',
  });

  // Deploy slash commands
  const commands = getAllCommands();
  const commandData = commands.map((c) => c.data.toJSON());

  const rest = new REST({ version: '10' }).setToken(env.DISCORD_BOT_TOKEN);

  try {
    if (env.DISCORD_GUILD_ID) {
      logger.info({ guildId: env.DISCORD_GUILD_ID, count: commandData.length }, 'Deploying slash commands to dev test guild...');
      await rest.put(
        Routes.applicationGuildCommands(env.DISCORD_CLIENT_ID, env.DISCORD_GUILD_ID),
        { body: commandData }
      );
      logger.info('✅ Guild slash commands registered instantly!');
    } else {
      logger.info({ count: commandData.length }, 'Deploying global slash commands across all guilds...');
      await rest.put(Routes.applicationCommands(env.DISCORD_CLIENT_ID), {
        body: commandData,
      });
      logger.info('✅ Global slash commands registered successfully!');
    }
  } catch (err: any) {
    logger.error({ err: err.message }, 'Failed to register application slash commands');
  }
}
