import crypto from 'node:crypto';
import { Message, PermissionFlagsBits, EmbedBuilder, TextChannel } from 'discord.js';
import { GuildConfig } from '../database/repositories/guildConfigRepository.js';
import { logModAction } from '../database/repositories/modLogRepository.js';
import { COLORS, EMOJIS } from '../config/constants.js';
import { logger } from '../utils/logger.js';

interface MessageHashRecord {
  hash: string;
  timestamp: number;
}

// Map of userId -> array of recent message hashes
const userMessageHistory = new Map<string, MessageHashRecord[]>();
const SPAM_WINDOW_MS = 5000;
const DUPLICATE_THRESHOLD = 4;
const MAX_MENTIONS = 5;

export async function checkSpamAndMentions(message: Message, config: GuildConfig): Promise<boolean> {
  if (!config.spam_filter_enabled || message.author.bot || !message.guild || !message.member) {
    return false;
  }

  // Exempt staff and admin
  const isStaff =
    message.member.permissions.has(PermissionFlagsBits.ManageMessages) ||
    message.member.permissions.has(PermissionFlagsBits.Administrator) ||
    config.staff_role_ids.some((id) => message.member?.roles.cache.has(id));

  if (isStaff) {
    return false;
  }

  const userId = message.author.id;
  const now = Date.now();
  const rawContent = message.content.trim();

  // 1. Mass Mention Check
  const mentionCount = message.mentions.users.size + message.mentions.roles.size;
  const hasEveryone = message.content.includes('@everyone') || message.content.includes('@here');

  if (mentionCount >= MAX_MENTIONS || hasEveryone) {
    logger.warn({ user: message.author.tag, mentionCount, hasEveryone }, 'Mass mention violation detected');
    try {
      await message.delete();
      await message.member.timeout(3600 * 1000, 'Mass mention / Unauthorized ping abuse');
    } catch (err) {
      logger.error({ err }, 'Failed to sanction mass mention abuser');
    }

    await logModAction({
      guild_id: config.guild_id,
      target_user_id: userId,
      moderator_id: message.client.user?.id || 'SYSTEM_BOT',
      action: 'timeout',
      reason: `Mass mention / Unauthorized ping spam (${mentionCount} mentions)`,
      metadata: { content: rawContent },
    });

    sendSpamAlert(message, config, 'Mass Mention / Ping Abuse', `Sent message with ${mentionCount} mentions.`);
    return true;
  }

  // 2. Duplicate Message Rapid-Fire Spam
  if (rawContent.length > 5) {
    const hash = crypto.createHash('sha256').update(rawContent).digest('hex');
    let history = userMessageHistory.get(userId) || [];
    history = history.filter((h) => now - h.timestamp <= SPAM_WINDOW_MS);
    history.push({ hash, timestamp: now });
    userMessageHistory.set(userId, history);

    const duplicateCount = history.filter((h) => h.hash === hash).length;
    if (duplicateCount >= DUPLICATE_THRESHOLD) {
      logger.warn({ user: message.author.tag, duplicateCount }, 'Rapid-fire duplicate spam detected');
      try {
        await message.delete();
        await message.member.timeout(3600 * 1000, 'Rapid duplicate message spamming');
      } catch (err) {
        logger.error({ err }, 'Failed to sanction duplicate spammer');
      }

      await logModAction({
        guild_id: config.guild_id,
        target_user_id: userId,
        moderator_id: message.client.user?.id || 'SYSTEM_BOT',
        action: 'timeout',
        reason: `Rapid duplicate message spam (${duplicateCount} messages in 5s)`,
        metadata: { duplicateCount, content: rawContent },
      });

      sendSpamAlert(message, config, 'Rapid Duplicate Message Spam', `Sent identical message ${duplicateCount} times across channels.`);
      return true;
    }
  }

  return false;
}

async function sendSpamAlert(message: Message, config: GuildConfig, title: string, details: string): Promise<void> {
  if (!config.mod_log_channel_id || !message.guild) return;
  try {
    const channel = message.guild.channels.cache.get(config.mod_log_channel_id) as TextChannel;
    if (channel) {
      const embed = new EmbedBuilder()
        .setColor(COLORS.WARNING)
        .setTitle(`${EMOJIS.SHIELD} Anti-Spam Triggered: ${title}`)
        .setDescription(`User ${message.author.tag} (\`${message.author.id}\`) was timed out for 1 hour.\n\n**Reason**: ${details}`)
        .addFields({ name: 'Message Content', value: `\`\`\`\n${message.content.slice(0, 500) || '[Empty]'}\n\`\`\`` })
        .setTimestamp();
      await channel.send({ embeds: [embed] });
    }
  } catch (err) {
    logger.error({ err }, 'Failed to send spam alert');
  }
}
