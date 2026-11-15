import { GuildMember, PartialGuildMember } from 'discord.js';
import { getGuildConfig } from '../database/repositories/guildConfigRepository.js';
import { handleMemberLeave } from '../services/onboarding.js';
import { logger } from '../utils/logger.js';

export async function onGuildMemberRemove(member: GuildMember | PartialGuildMember): Promise<void> {
  const guild = member.guild;
  const config = await getGuildConfig(guild.id);
  if (!config) return;

  logger.debug({ user: member.user?.tag || member.id, guild: guild.name }, 'Member left guild');

  await handleMemberLeave(member as GuildMember, config);
}
