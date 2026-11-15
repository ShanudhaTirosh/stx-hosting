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
  .setName('warn')
  .setDescription('Issue a formal warning to a member')
  .addUserOption((opt) => opt.setName('user').setDescription('The user to warn').setRequired(true))
  .addStringOption((opt) => opt.setName('reason').setDescription('Reason for warning').setRequired(true))
  .setDefaultMemberPermissions(PermissionFlagsBits.ModerateMembers);

export async function execute(interaction: ChatInputCommandInteraction): Promise<void> {
  const guild = interaction.guild;
  if (!guild) return;

  const targetUser = interaction.options.getUser('user', true);
  const reason = interaction.options.getString('reason', true);

  if (targetUser.id === interaction.user.id) {
    await interaction.reply({ content: 'You cannot warn yourself.', ephemeral: true });
    return;
  }

  // 1. Record in DB
  await logModAction({
    guild_id: guild.id,
    target_user_id: targetUser.id,
    moderator_id: interaction.user.id,
    action: 'warn',
    reason,
  });

  // 2. DM the user if possible
  try {
    const dmEmbed = new EmbedBuilder()
      .setColor(COLORS.WARNING)
      .setTitle(`${EMOJIS.WARNING} Official Warning from ${guild.name}`)
      .setDescription(`You have received an official warning from staff.\n\n**Reason**: ${reason}`)
      .setTimestamp();
    await targetUser.send({ embeds: [dmEmbed] });
  } catch {}

  const replyEmbed = new EmbedBuilder()
    .setColor(COLORS.WARNING)
    .setDescription(`${EMOJIS.SUCCESS} Warned <@${targetUser.id}> for: **${reason}**`);

  await interaction.reply({ embeds: [replyEmbed] });
}
