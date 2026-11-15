import { Message, PermissionFlagsBits, EmbedBuilder, TextChannel } from 'discord.js';
import { GuildConfig } from '../database/repositories/guildConfigRepository.js';
import { logModAction } from '../database/repositories/modLogRepository.js';
import { KNOWN_PHISHING_DOMAINS, COLORS, EMOJIS } from '../config/constants.js';
import { logger } from '../utils/logger.js';

const DISCORD_INVITE_REGEX = /(https?:\/\/)?(www\.)?(discord\.(gg|io|me|li)|discordapp\.com\/invite|discord\.com\/invite)\/[a-zA-Z0-9]+/i;

export async function checkPhishingAndInvites(message: Message, config: GuildConfig): Promise<boolean> {
  if (message.author.bot || !message.guild || !message.member) {
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

  const content = message.content.toLowerCase();

  // 1. Phishing & Scam Domain Filter
  if (config.phishing_filter_enabled) {
    const matchedPhishing = KNOWN_PHISHING_DOMAINS.find((domain) => content.includes(domain));
    if (matchedPhishing) {
      logger.warn({ user: message.author.tag, domain: matchedPhishing }, 'Phishing link detected');
      try {
        await message.delete();
        await message.member.timeout(86400 * 1000, 'Automated Filter: Posting known phishing/scam domain');
      } catch (err) {
        logger.error({ err }, 'Failed to sanction phishing link author');
      }

      await logModAction({
        guild_id: config.guild_id,
        target_user_id: message.author.id,
        moderator_id: message.client.user?.id || 'SYSTEM_BOT',
        action: 'phishing_block',
        reason: `Posted malicious or scam link containing "${matchedPhishing}"`,
        metadata: { matchedPhishing, content: message.content },
      });

      sendPhishingAlert(message, config, 'Phishing / Fake Gift Domain Detected', matchedPhishing);
      return true;
    }
  }

  // 2. Unauthorized Discord Invite Filter
  if (config.invite_filter_enabled && DISCORD_INVITE_REGEX.test(message.content)) {
    logger.warn({ user: message.author.tag }, 'Unauthorized Discord invite link detected');
    try {
      await message.delete();
    } catch (err) {
      logger.error({ err }, 'Failed to delete invite link message');
    }

    try {
      if ('send' in message.channel && typeof message.channel.send === 'function') {
        const warningMsg = await (message.channel as TextChannel).send({
          content: `${EMOJIS.WARNING} ${message.author}, posting external Discord invite links is not permitted here.`,
        });
        setTimeout(() => warningMsg.delete().catch(() => {}), 6000);
      }
    } catch {}

    return true;
  }

  return false;
}

async function sendPhishingAlert(message: Message, config: GuildConfig, title: string, domain: string): Promise<void> {
  if (!config.mod_log_channel_id || !message.guild) return;
  try {
    const channel = message.guild.channels.cache.get(config.mod_log_channel_id) as TextChannel;
    if (channel) {
      const embed = new EmbedBuilder()
        .setColor(COLORS.DANGER)
        .setTitle(`${EMOJIS.SHIELD} Malicious Link Intercepted: ${title}`)
        .setDescription(`User ${message.author.tag} (\`${message.author.id}\`) posted a link matching blacklist: **${domain}**\n\nThe message was deleted and the user timed out for 24 hours.`)
        .addFields({ name: 'Filtered Message', value: `\`\`\`\n${message.content.slice(0, 500) || '[Empty]'}\n\`\`\`` })
        .setTimestamp();
      await channel.send({ embeds: [embed] });
    }
  } catch (err) {
    logger.error({ err }, 'Failed to send phishing alert');
  }
}
