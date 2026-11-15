import {
  SlashCommandBuilder,
  ChatInputCommandInteraction,
  PermissionFlagsBits,
  EmbedBuilder,
} from 'discord.js';
import { getModLogsForUser } from '../../database/repositories/modLogRepository.js';
import { COLORS, EMOJIS } from '../../config/constants.js';

export const data = new SlashCommandBuilder()
  .setName('modlogs')
  .setDescription('View moderation infractions and honeypot history for a user')
  .addUserOption((opt) => opt.setName('user').setDescription('Target member').setRequired(true))
  .setDefaultMemberPermissions(PermissionFlagsBits.ModerateMembers);

export async function execute(interaction: ChatInputCommandInteraction): Promise<void> {
  const guild = interaction.guild;
  if (!guild) return;

  const targetUser = interaction.options.getUser('user', true);
  await interaction.deferReply({ ephemeral: true });

  const logs = await getModLogsForUser(guild.id, targetUser.id);

  if (logs.length === 0) {
    await interaction.editReply({
      content: `${EMOJIS.SUCCESS} No moderation infractions found for **${targetUser.tag}**. Clean record!`,
    });
    return;
  }

  const embed = new EmbedBuilder()
    .setColor(COLORS.WARNING)
    .setTitle(`${EMOJIS.CLIPBOARD} Moderation History: ${targetUser.tag}`)
    .setDescription(`Showing the latest **${logs.length}** infraction(s) recorded in database.`)
    .setThumbnail(targetUser.displayAvatarURL())
    .setFooter({ text: `User ID: ${targetUser.id}` })
    .setTimestamp();

  for (const log of logs.slice(0, 10)) {
    const timestamp = log.created_at ? `<t:${Math.floor(new Date(log.created_at).getTime() / 1000)}:R>` : 'Unknown';
    embed.addFields({
      name: `${log.action.toUpperCase()} • ${timestamp}`,
      value: `**Reason**: ${log.reason || 'None'}\n**Mod**: <@${log.moderator_id}>`,
    });
  }

  await interaction.editReply({ embeds: [embed] });
}
