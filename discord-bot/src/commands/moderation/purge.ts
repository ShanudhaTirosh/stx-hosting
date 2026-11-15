import {
  SlashCommandBuilder,
  ChatInputCommandInteraction,
  PermissionFlagsBits,
  TextChannel,
} from 'discord.js';
import { logModAction } from '../../database/repositories/modLogRepository.js';
import { EMOJIS } from '../../config/constants.js';

export const data = new SlashCommandBuilder()
  .setName('purge')
  .setDescription('Bulk delete messages from the current channel')
  .addIntegerOption((opt) =>
    opt
      .setName('count')
      .setDescription('Number of messages to delete (1 to 100)')
      .setRequired(true)
      .setMinValue(1)
      .setMaxValue(100)
  )
  .addUserOption((opt) => opt.setName('user').setDescription('Filter messages by specific user').setRequired(false))
  .setDefaultMemberPermissions(PermissionFlagsBits.ManageMessages);

export async function execute(interaction: ChatInputCommandInteraction): Promise<void> {
  const channel = interaction.channel as TextChannel;
  if (!channel || !channel.bulkDelete) {
    await interaction.reply({ content: 'Cannot purge in this channel.', ephemeral: true });
    return;
  }

  const count = interaction.options.getInteger('count', true);
  const filterUser = interaction.options.getUser('user');

  await interaction.deferReply({ ephemeral: true });

  try {
    let messages = await channel.messages.fetch({ limit: count });
    if (filterUser) {
      messages = messages.filter((m) => m.author.id === filterUser.id);
    }

    const deleted = await channel.bulkDelete(messages, true);

    await logModAction({
      guild_id: interaction.guildId || '',
      target_user_id: filterUser ? filterUser.id : interaction.guildId || '',
      moderator_id: interaction.user.id,
      action: 'purge',
      reason: `Purged ${deleted.size} messages in #${channel.name}`,
      metadata: { deletedCount: deleted.size, channelId: channel.id },
    });

    await interaction.editReply({
      content: `${EMOJIS.SUCCESS} Successfully purged **${deleted.size} messages**!` +
        (filterUser ? ` (Filtered by <@${filterUser.id}>)` : ''),
    });
  } catch (err: any) {
    await interaction.editReply({
      content: `${EMOJIS.ERROR} Failed to purge messages: Messages older than 14 days cannot be bulk deleted.`,
    });
  }
}
