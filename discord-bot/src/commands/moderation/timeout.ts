import {
  SlashCommandBuilder,
  ChatInputCommandInteraction,
  PermissionFlagsBits,
  EmbedBuilder,
  GuildMember,
} from 'discord.js';
import { logModAction } from '../../database/repositories/modLogRepository.js';
import { COLORS, EMOJIS } from '../../config/constants.js';

export const data = new SlashCommandBuilder()
  .setName('timeout')
  .setDescription('Timeout/mute a member')
  .addUserOption((opt) => opt.setName('user').setDescription('The user to timeout').setRequired(true))
  .addIntegerOption((opt) =>
    opt
      .setName('minutes')
      .setDescription('Duration in minutes (e.g. 5, 60, 1440)')
      .setRequired(true)
      .setMinValue(1)
      .setMaxValue(40320)
  )
  .addStringOption((opt) => opt.setName('reason').setDescription('Reason for timeout').setRequired(true))
  .setDefaultMemberPermissions(PermissionFlagsBits.ModerateMembers);

export async function execute(interaction: ChatInputCommandInteraction): Promise<void> {
  const guild = interaction.guild;
  if (!guild) return;

  const targetUser = interaction.options.getUser('user', true);
  const minutes = interaction.options.getInteger('minutes', true);
  const reason = interaction.options.getString('reason', true);

  const member = await guild.members.fetch(targetUser.id).catch(() => null);
  if (!member) {
    await interaction.reply({ content: 'Member is not in the server.', ephemeral: true });
    return;
  }

  if (!member.moderatable) {
    await interaction.reply({
      content: `${EMOJIS.ERROR} Cannot timeout this member due to role hierarchy.`,
      ephemeral: true,
    });
    return;
  }

  try {
    await member.timeout(minutes * 60 * 1000, reason);

    await logModAction({
      guild_id: guild.id,
      target_user_id: targetUser.id,
      moderator_id: interaction.user.id,
      action: 'timeout',
      reason: `${reason} (${minutes}m)`,
      metadata: { minutes },
    });

    const embed = new EmbedBuilder()
      .setColor(COLORS.WARNING)
      .setDescription(`${EMOJIS.SUCCESS} Timed out <@${targetUser.id}> for **${minutes} minutes**.\n**Reason**: ${reason}`);

    await interaction.reply({ embeds: [embed] });
  } catch (err: any) {
    await interaction.reply({
      content: `${EMOJIS.ERROR} Failed to timeout member: ${err.message}`,
      ephemeral: true,
    });
  }
}
