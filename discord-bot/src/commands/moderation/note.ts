import {
  SlashCommandBuilder,
  ChatInputCommandInteraction,
  PermissionFlagsBits,
  EmbedBuilder,
} from 'discord.js';
import { addUserNote, getUserNotes } from '../../database/repositories/modLogRepository.js';
import { COLORS, EMOJIS } from '../../config/constants.js';

export const data = new SlashCommandBuilder()
  .setName('note')
  .setDescription('Internal staff notes for a user')
  .addSubcommand((sub) =>
    sub
      .setName('add')
      .setDescription('Add a private staff note to a user')
      .addUserOption((opt) => opt.setName('user').setDescription('Target member').setRequired(true))
      .addStringOption((opt) => opt.setName('text').setDescription('The note content').setRequired(true))
  )
  .addSubcommand((sub) =>
    sub
      .setName('list')
      .setDescription('View all staff notes for a user')
      .addUserOption((opt) => opt.setName('user').setDescription('Target member').setRequired(true))
  )
  .setDefaultMemberPermissions(PermissionFlagsBits.ModerateMembers);

export async function execute(interaction: ChatInputCommandInteraction): Promise<void> {
  const guild = interaction.guild;
  if (!guild) return;

  const subcommand = interaction.options.getSubcommand();
  const targetUser = interaction.options.getUser('user', true);

  if (subcommand === 'add') {
    const text = interaction.options.getString('text', true);
    await addUserNote(guild.id, targetUser.id, interaction.user.id, text);
    await interaction.reply({
      content: `${EMOJIS.SUCCESS} Added note to **${targetUser.tag}**: "${text}"`,
      ephemeral: true,
    });
    return;
  }

  if (subcommand === 'list') {
    await interaction.deferReply({ ephemeral: true });
    const notes = await getUserNotes(guild.id, targetUser.id);

    if (notes.length === 0) {
      await interaction.editReply({
        content: `No staff notes recorded for **${targetUser.tag}**.`,
      });
      return;
    }

    const embed = new EmbedBuilder()
      .setColor(COLORS.DARK)
      .setTitle(`📝 Staff Notes: ${targetUser.tag}`)
      .setDescription(`Showing **${notes.length}** note(s).`);

    for (const n of notes.slice(0, 15)) {
      const timestamp = n.created_at ? `<t:${Math.floor(new Date(n.created_at).getTime() / 1000)}:R>` : '';
      embed.addFields({
        name: `By <@${n.moderator_id}> ${timestamp}`,
        value: n.note,
      });
    }

    await interaction.editReply({ embeds: [embed] });
  }
}
