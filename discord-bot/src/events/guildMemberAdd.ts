import { GuildMember } from 'discord.js';
import { getGuildConfig } from '../database/repositories/guildConfigRepository.js';
import { checkRaidVelocity } from '../services/antiRaid.js';
import { handleMemberJoin } from '../services/onboarding.js';
import { logger } from '../utils/logger.js';

export async function onGuildMemberAdd(member: GuildMember): Promise<void> {
  const guild = member.guild;
  const config = await getGuildConfig(guild.id);
  if (!config) return;

  logger.debug({ user: member.user.tag, guild: guild.name }, 'Member joined guild');

  // 1. Anti-Raid Join Velocity Monitor
  checkRaidVelocity(member, config);

  // 2. Onboarding Workflow (Account age check, auto-roles, welcome message)
  await handleMemberJoin(member, config);
}
