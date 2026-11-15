import { Guild, GuildMember, TextChannel, EmbedBuilder } from 'discord.js';
import { GuildConfig } from '../database/repositories/guildConfigRepository.js';
import { logModAction } from '../database/repositories/modLogRepository.js';
import { COLORS, EMOJIS } from '../config/constants.js';
import { logger } from '../utils/logger.js';

// Map of guildId -> array of join timestamps (ms)
const guildJoins = new Map<string, number[]>();
const raidActive = new Map<string, boolean>();

export function checkRaidVelocity(member: GuildMember, config: GuildConfig): boolean {
  const guildId = member.guild.id;
  const now = Date.now();
  const windowMs = (config.raid_threshold_seconds || 10) * 1000;
  const maxJoins = config.raid_threshold_joins || 10;

  let timestamps = guildJoins.get(guildId) || [];
  // Prune timestamps outside window
  timestamps = timestamps.filter((t) => now - t <= windowMs);
  timestamps.push(now);
  guildJoins.set(guildId, timestamps);

  if (timestamps.length >= maxJoins) {
    if (!raidActive.get(guildId)) {
      raidActive.set(guildId, true);
      triggerRaidLockdown(member.guild, config, timestamps.length, config.raid_threshold_seconds);
    }
    return true; // Raid threshold reached!
  }

  return false;
}

export function isRaidActive(guildId: string): boolean {
  return raidActive.get(guildId) ?? false;
}

export function clearRaidMode(guildId: string): void {
  raidActive.delete(guildId);
  guildJoins.delete(guildId);
}

async function triggerRaidLockdown(
  guild: Guild,
  config: GuildConfig,
  joinCount: number,
  timeWindowSeconds: number
): Promise<void> {
  logger.warn(
    { guild: guild.name, guildId: guild.id, joinCount, timeWindowSeconds },
    '🚨 RAID DETECTED: Sudden burst of member joins triggered automated lockdown!'
  );

  // 1. Log in database
  await logModAction({
    guild_id: guild.id,
    target_user_id: guild.id,
    moderator_id: guild.client.user?.id || 'SYSTEM_BOT',
    action: 'raid_lockdown',
    reason: `Automated Raid Alert: ${joinCount} joins in ${timeWindowSeconds} seconds`,
    metadata: { joinCount, timeWindowSeconds },
  });

  // 2. Alert staff in mod log channel
  if (config.mod_log_channel_id) {
    try {
      const channel = guild.channels.cache.get(config.mod_log_channel_id) as TextChannel;
      if (channel) {
        const embed = new EmbedBuilder()
          .setColor(COLORS.DANGER)
          .setTitle(`${EMOJIS.WARNING} RAID ALERT TRIGGERED`)
          .setDescription(
            `**${joinCount} members joined within ${timeWindowSeconds} seconds!**\n\n` +
              `The server has entered defensive raid mode. Recommended staff actions:\n` +
              `• Enable verification level High or Highest in Server Settings\n` +
              `• Temporarily pause invites using Server Settings -> Invites\n` +
              `• Run \`/raid clear\` to dismiss this status once safe.`
          )
          .setTimestamp();

        await channel.send({ content: '@here', embeds: [embed] });
      }
    } catch (err) {
      logger.error({ err }, 'Failed to send raid alert to mod log channel');
    }
  }
}
