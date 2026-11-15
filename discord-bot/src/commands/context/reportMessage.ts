import {
  ContextMenuCommandBuilder,
  ApplicationCommandType,
  MessageContextMenuCommandInteraction,
  EmbedBuilder,
  TextChannel,
} from 'discord.js';
import { getGuildConfig } from '../../database/repositories/guildConfigRepository.js';
import { COLORS, EMOJIS } from '../../config/constants.js';

export const data = new ContextMenuCommandBuilder()
  .setName('Report Message')
  .setType(ApplicationCommandType.Message);

export async function execute(interaction: MessageContextMenuCommandInteraction): Promise<void> {
  const guild = interaction.guild;
  if (!guild) {
    await interaction.reply({ content: 'Only available in a server.', ephemeral: true });
    return;
  }

  const config = await getGuildConfig(guild.id);
  if (!config || !config.mod_log_channel_id) {
    await interaction.reply({
      content: `${EMOJIS.WARNING} Moderation log channel is not configured.`,
      ephemeral: true,
    });
    return;
  }

  const modChannel = guild.channels.cache.get(config.mod_log_channel_id) as TextChannel;
  if (!modChannel) {
    await interaction.reply({
      content: `${EMOJIS.ERROR} Moderation log channel not found.`,
      ephemeral: true,
    });
    return;
  }

  const message = interaction.targetMessage;

  const embed = new EmbedBuilder()
    .setColor(COLORS.WARNING)
    .setTitle(`🚩 User Message Report`)
    .setDescription(`A member reported a message for staff review.`)
    .addFields(
      { name: 'Reported Author', value: `${message.author.tag} (<@${message.author.id}>)`, inline: true },
      { name: 'Reported By', value: `${interaction.user.tag} (<@${interaction.user.id}>)`, inline: true },
      { name: 'Channel', value: `<#${message.channel.id}>`, inline: true },
      { name: 'Message Content', value: `\`\`\`\n${message.content.slice(0, 500) || '[No text content / Media]'}\n\`\`\`` },
      { name: 'Jump to Message', value: `[Click Here](${message.url})` }
    )
    .setTimestamp();

  await modChannel.send({ embeds: [embed] });

  await interaction.reply({
    content: `${EMOJIS.SUCCESS} Thank you! Your report has been submitted to server staff for review.`,
    ephemeral: true,
  });
}
