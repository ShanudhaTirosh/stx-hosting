import {
  SlashCommandBuilder,
  ChatInputCommandInteraction,
  PermissionFlagsBits,
  EmbedBuilder,
} from 'discord.js';
import { logModAction } from '../../database/repositories/modLogRepository.js';
import { COLORS, EMOJIS } from '../../config/constants.js';

export const data = new SlashCommandBuilder()
  .setName('unban')
  .setDescription('Unban a user by their Discord user ID')
  .addStringOption((opt) => opt.setName('user_id').setDescription('The Discord Snowflake ID to unban').setRequired(true))
  .addStringOption((opt) => opt.setName('reason').setDescription('Reason for unban').setRequired(false))
  .setDefaultMemberPermissions(PermissionFlagsBits.BanMembers);

export async function execute(interaction: ChatInputCommandInteraction): Promise<void> {
  const guild = interaction.guild;
  if (!guild) return;

  const targetUserId = interaction.options.getString('user_id', true).trim();
  const reason = interaction.options.getString('reason') || 'Appeal approved';

  try {
    await guild.members.unban(targetUserId, reason);

    await logModAction({
      guild_id: guild.id,
      target_user_id: targetUserId,
      moderator_id: interaction.user.id,
      action: 'unban',
      reason,
    });

    const embed = new EmbedBuilder()
      .setColor(COLORS.SUCCESS)
      .setDescription(`${EMOJIS.SUCCESS} Successfully unbanned user ID \`${targetUserId}\`.\n**Reason**: ${reason}`);

    await interaction.reply({ embeds: [embed] });
  } catch (err: any) {
    await interaction.reply({
      content: `${EMOJIS.ERROR} Failed to unban user. Please verify the user ID is currently banned.`,
      ephemeral: true,
    });
  }
}
