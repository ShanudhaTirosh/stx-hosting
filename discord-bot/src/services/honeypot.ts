import { Message, GuildMember, EmbedBuilder, TextChannel, PermissionFlagsBits } from 'discord.js';
import { GuildConfig } from '../database/repositories/guildConfigRepository.js';
import { logModAction } from '../database/repositories/modLogRepository.js';
import { COLORS, EMOJIS } from '../config/constants.js';
import { logger } from '../utils/logger.js';

export async function handleHoneypotTrigger(message: Message, config: GuildConfig): Promise<boolean> {
  // If not in the honeypot trap channel, exit immediately
  if (!config.honeypot_channel_id || message.channel.id !== config.honeypot_channel_id) {
    return false;
  }

  // Never trigger for bots
  if (message.author.bot) {
    return true;
  }

  const member = message.member;
  if (!member) {
    return false;
  }

  // Exempt staff and admin roles
  const isStaff =
    member.permissions.has(PermissionFlagsBits.Administrator) ||
    member.permissions.has(PermissionFlagsBits.ManageGuild) ||
    config.staff_role_ids.some((roleId) => member.roles.cache.has(roleId)) ||
    config.admin_role_ids.some((roleId) => member.roles.cache.has(roleId));

  if (isStaff) {
    logger.debug({ user: message.author.tag }, 'Staff message in honeypot channel ignored');
    return true;
  }

  const user = message.author;
  const content = message.content;
  logger.warn(
    { guild: message.guild?.id, user: user.tag, userId: user.id },
    '🚨 HONEYPOT TRIGGERED: Compromised account or scammer posted in decoy channel!'
  );

  // 1. Delete the triggering message immediately
  try {
    await message.delete();
  } catch (err) {
    logger.error({ err }, 'Failed to delete honeypot message');
  }

  // 2. Attempt to send DM warning & appeal instructions before sanction
  try {
    const dmEmbed = new EmbedBuilder()
      .setColor(COLORS.DANGER)
      .setTitle(`${EMOJIS.SHIELD} Account Security Alert`)
      .setDescription(
        `You have been automatically banned from **${message.guild?.name}** because your account posted in a restricted trap channel reserved for automated bots and scammers.\n\n` +
          `If your Discord account was compromised or token-hijacked, please change your Discord password, enable 2FA, and reach out to our team at support@ourdomain.com to submit an appeal.`
      )
      .setTimestamp();
    await user.send({ embeds: [dmEmbed] });
  } catch {
    // Expected if user DMs are closed
  }

  // 3. Execute configured sanction (ban with message purge or timeout)
  const action = config.honeypot_action || 'ban';
  const deleteSeconds = (config.honeypot_delete_days || 7) * 86400; // Purge 7 days of spam

  try {
    if (action === 'ban') {
      await member.ban({
        deleteMessageSeconds: deleteSeconds,
        reason: 'Automated Honeypot Trap Trigger: Account compromised or scam bot',
      });
    } else {
      await member.timeout(28 * 86400 * 1000, 'Automated Honeypot Trap Trigger');
    }
  } catch (err) {
    logger.error({ err, userId: user.id }, 'Failed to execute honeypot sanction on member');
  }

  // 4. Record in database audit log
  await logModAction({
    guild_id: config.guild_id,
    target_user_id: user.id,
    moderator_id: message.client.user?.id || 'SYSTEM_BOT',
    action: 'honeypot_trigger',
    reason: `Posted in honeypot trap channel: "${content.substring(0, 100)}"`,
    metadata: {
      channel_id: message.channel.id,
      sanction: action,
      deleted_message_content: content,
      account_created_at: user.createdAt.toISOString(),
      joined_at: member.joinedAt?.toISOString(),
    },
  });

  // 5. Send alert to the mod log channel
  if (config.mod_log_channel_id) {
    try {
      const logChannel = message.guild?.channels.cache.get(config.mod_log_channel_id) as TextChannel;
      if (logChannel) {
        const alertEmbed = new EmbedBuilder()
          .setColor(COLORS.DANGER)
          .setTitle(`${EMOJIS.SHIELD} Honeypot Bot Trap Triggered`)
          .setDescription(`A compromised account or bot posted in the decoy channel and was automatically **${action.toUpperCase()}NED**.`)
          .addFields(
            { name: 'Target User', value: `${user.tag} (\`${user.id}\`)`, inline: true },
            { name: 'Action Taken', value: `${action.toUpperCase()} (Purged recent msgs)`, inline: true },
            { name: 'Account Created', value: `<t:${Math.floor(user.createdTimestamp / 1000)}:R>`, inline: true },
            { name: 'Trapped Message Content', value: `\`\`\`\n${content ? content.slice(0, 500) : '[Empty/Attachment]'}\n\`\`\`` }
          )
          .setThumbnail(user.displayAvatarURL())
          .setTimestamp();

        await logChannel.send({ embeds: [alertEmbed] });
      }
    } catch (err) {
      logger.error({ err }, 'Failed to send honeypot alert to mod log channel');
    }
  }

  return true;
}
