import { Client, GatewayIntentBits, Partials } from 'discord.js';
import { onReady } from './events/ready.js';
import { onInteractionCreate } from './events/interactionCreate.js';
import { onMessageCreate } from './events/messageCreate.js';
import { onGuildMemberAdd } from './events/guildMemberAdd.js';
import { onGuildMemberRemove } from './events/guildMemberRemove.js';
import { logger } from './utils/logger.js';

export function createDiscordClient(): Client {
  const client = new Client({
    intents: [
      GatewayIntentBits.Guilds,
      GatewayIntentBits.GuildMembers, // Privileged: Member onboarding & anti-raid
      GatewayIntentBits.GuildMessages,
      GatewayIntentBits.MessageContent, // Privileged: Honeypot trap, anti-spam & phishing filter
      GatewayIntentBits.GuildMessageReactions,
      GatewayIntentBits.DirectMessages,
    ],
    partials: [
      Partials.Channel,
      Partials.Message,
      Partials.Reaction,
      Partials.User,
      Partials.GuildMember,
    ],
  });

  // Attach event handlers
  client.once('ready', () => onReady(client));
  client.on('interactionCreate', (interaction) => onInteractionCreate(interaction));
  client.on('messageCreate', (message) => onMessageCreate(message));
  client.on('guildMemberAdd', (member) => onGuildMemberAdd(member));
  client.on('guildMemberRemove', (member) => onGuildMemberRemove(member));

  // Error and reconnect handlers
  client.on('error', (error) => {
    logger.error({ err: error.message }, 'Discord Client encountered an error');
  });

  client.on('shardDisconnect', (event, shardId) => {
    logger.warn({ shardId, event }, 'Discord Shard disconnected. Gateway will auto-reconnect.');
  });

  client.on('shardReconnecting', (shardId) => {
    logger.info({ shardId }, 'Discord Shard attempting reconnection...');
  });

  client.on('shardResume', (shardId, replayedEvents) => {
    logger.info({ shardId, replayedEvents }, 'Discord Shard resumed session successfully.');
  });

  return client;
}
