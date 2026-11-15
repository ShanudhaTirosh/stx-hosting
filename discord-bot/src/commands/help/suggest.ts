import {
  SlashCommandBuilder,
  ChatInputCommandInteraction,
  EmbedBuilder,
  ActionRowBuilder,
  ButtonBuilder,
  ButtonStyle,
  TextChannel,
} from 'discord.js';
import { getGuildConfig } from '../../database/repositories/guildConfigRepository.js';
import { createSuggestion } from '../../database/repositories/faqAndSuggestionRepository.js';
import { COLORS, EMOJIS } from '../../config/constants.js';

export const data = new SlashCommandBuilder()
  .setName('suggest')
  .setDescription('Submit a community or platform feature suggestion')
  .addStringOption((opt) => opt.setName('idea').setDescription('Describe your suggestion or feedback').setRequired(true));

export async function execute(interaction: ChatInputCommandInteraction): Promise<void> {
  const guild = interaction.guild;
  if (!guild) {
    await interaction.reply({ content: 'Only available in a server.', ephemeral: true });
    return;
  }

  const config = await getGuildConfig(guild.id);
  if (!config || !config.suggestion_channel_id) {
    await interaction.reply({
      content: `${EMOJIS.WARNING} Suggestions channel is not configured on this server. Please inform staff.`,
      ephemeral: true,
    });
    return;
  }

  const suggestionChannel = guild.channels.cache.get(config.suggestion_channel_id) as TextChannel;
  if (!suggestionChannel) {
    await interaction.reply({
      content: `${EMOJIS.ERROR} Suggestions channel could not be found.`,
      ephemeral: true,
    });
    return;
  }

  const idea = interaction.options.getString('idea', true).trim();

  const embed = new EmbedBuilder()
    .setColor(COLORS.STX_BLUE)
    .setTitle(`💡 Community Suggestion`)
    .setDescription(idea)
    .addFields(
      { name: 'Author', value: `${interaction.user.tag} (<@${interaction.user.id}>)`, inline: true },
      { name: 'Status', value: '🟡 Under Review', inline: true }
    )
    .setThumbnail(interaction.user.displayAvatarURL())
    .setFooter({ text: 'Vote using the buttons below!' })
    .setTimestamp();

  const row = new ActionRowBuilder<ButtonBuilder>().addComponents(
    new ButtonBuilder().setCustomId('btn_vote_up').setLabel('0').setEmoji('👍').setStyle(ButtonStyle.Secondary),
    new ButtonBuilder().setCustomId('btn_vote_down').setLabel('0').setEmoji('👎').setStyle(ButtonStyle.Secondary)
  );

  const message = await suggestionChannel.send({ embeds: [embed], components: [row] });

  await createSuggestion({
    guild_id: guild.id,
    message_id: message.id,
    author_id: interaction.user.id,
    author_name: interaction.user.tag,
    suggestion: idea,
  });

  await interaction.reply({
    content: `${EMOJIS.SUCCESS} Your suggestion has been posted to <#${suggestionChannel.id}>!`,
    ephemeral: true,
  });
}
