import {
  SlashCommandBuilder,
  ChatInputCommandInteraction,
  PermissionFlagsBits,
  EmbedBuilder,
} from 'discord.js';
import { logModAction } from '../../database/repositories/modLogRepository.js';
import { COLORS, EMOJIS } from '../../config/constants.js';

export const data = new SlashCommandBuilder()
  .setName('ban')
  .setDescription('Ban a user and optionally purge their messages')
  .addUserOption((opt) => opt.setName('user').setDescription('The user to ban').setRequired(true))
  .addIntegerOption((opt) =>
    opt
      .setName('delete_days')
      .setDescription('Days of message history to delete (0 to 7)')
      .setRequired(false)
      .setMinValue(0)
      .setMaxValue(7)
  )
  .addStringOption((opt) => opt.setName('reason').setDescription('Reason for ban').setRequired(false))
  .setDefaultMemberPermissions(PermissionFlagsBits.BanMembers);

export async function execute(interaction: ChatInputCommandInteraction): Promise<void> {
  const guild = interaction.guild;
  if (!guild) return;

  const targetUser = interaction.options.getUser('user', true);
  const deleteDays = interaction.options.getInteger('delete_days') ?? 0;
  const reason = interaction.options.getString('reason') || 'No reason specified';

  try {
    await guild.members.ban(targetUser.id, {
      deleteMessageSeconds: deleteDays * 86400,
      reason,
    });

    await logModAction({
      guild_id: guild.id,
      target_user_id: targetUser.id,
      moderator_id: interaction.user.id,
      action: 'ban',
      reason,
      metadata: { deleteDays },
    });

    const embed = new EmbedBuilder()
      .setColor(COLORS.DANGER)
      .setDescription(
        `${EMOJIS.SUCCESS} Banned **${targetUser.tag}** (\`${targetUser.id}\`).\n` +
          `**Message History Purged**: ${deleteDays} day(s)\n` +
          `**Reason**: ${reason}`
      );

    await interaction.reply({ embeds: [embed] });
  } catch (err: any) {
    await interaction.reply({
      content: `${EMOJIS.ERROR} Failed to ban user: ${err.message}`,
      ephemeral: true,
    });
  }
}
