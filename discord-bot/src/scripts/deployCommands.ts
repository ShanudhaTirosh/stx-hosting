import { REST, Routes } from 'discord.js';
import { getAllCommands } from '../commands/index.js';
import { env } from '../config/env.js';
import { logger } from '../utils/logger.js';

async function deploy() {
  const commands = getAllCommands();
  const commandData = commands.map((c) => c.data.toJSON());

  const rest = new REST({ version: '10' }).setToken(env.DISCORD_BOT_TOKEN);

  logger.info({ count: commandData.length }, 'Started refreshing application (/) commands...');

  if (env.DISCORD_GUILD_ID) {
    logger.info({ guildId: env.DISCORD_GUILD_ID }, 'Deploying to specific development guild...');
    await rest.put(Routes.applicationGuildCommands(env.DISCORD_CLIENT_ID, env.DISCORD_GUILD_ID), {
      body: commandData,
    });
    logger.info('Successfully reloaded application guild (/) commands.');
  } else {
    logger.info('Deploying globally to all guilds...');
    await rest.put(Routes.applicationCommands(env.DISCORD_CLIENT_ID), {
      body: commandData,
    });
    logger.info('Successfully reloaded global application (/) commands.');
  }
}

deploy().catch((err) => {
  logger.error({ err }, 'Command deployment failed');
  process.exit(1);
});
