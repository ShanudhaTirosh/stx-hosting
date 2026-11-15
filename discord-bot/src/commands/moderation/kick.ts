import {
  SlashCommandBuilder,
  ChatInputCommandInteraction,
  PermissionFlagsBits,
  EmbedBuilder,
} from 'discord.js';
import { logModAction } from '../../database/repositories/modLogRepository.js';
import { COLORS, EMOJIS } from '../../config/constants.js';

export const data = new SlashCommandBuilder()
  .setName('kick')
  .setDescription('Kick a member from the server')
  .addUserOption((opt) => opt.setName('user').setDescription('The member to kick').setRequired(true))
  .addStringOption((opt) => opt.setName('reason').setDescription('Reason for kick').setRequired(false))
  .setDefaultMemberPermissions(PermissionFlagsBits.KickMembers);

export async function execute(interaction: ChatInputCommandInteraction): Promise<void> {
  const guild = interaction.guild;
  if (!guild) return;

  const targetUser = interaction.options.getUser('user', true);
  const reason = interaction.options.getString('reason') || 'No reason specified';

  const member = await guild.members.fetch(targetUser.id).catch(() => null);
  if (!member) {
    await interaction.reply({ content: 'Member is not in the server.', ephemeral: true });
    return;
  }

  if (!member.kickable) {
    await interaction.reply({
      content: `${EMOJIS.ERROR} Cannot kick this member due to role hierarchy.`,
      ephemeral: true,
    });
    return;
  }

  try {
    await member.kick(reason);

    await logModAction({
      guild_id: guild.id,
      target_user_id: targetUser.id,
      moderator_id: interaction.user.id,
      action: 'kick',
      reason,
    });

    const embed = new EmbedBuilder()
      .setColor(COLORS.DANGER)
      .setDescription(`${EMOJIS.SUCCESS} Kicked **${targetUser.tag}**.\n**Reason**: ${reason}`);

    await interaction.reply({ embeds: [embed] });
  } catch (err: any) {
    await interaction.reply({
      content: `${EMOJIS.ERROR} Failed to kick member: ${err.message}`,
      ephemeral: true,
    });
  }
}
