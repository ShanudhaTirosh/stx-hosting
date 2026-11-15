import {
  SlashCommandBuilder,
  ChatInputCommandInteraction,
  PermissionFlagsBits,
  EmbedBuilder,
} from 'discord.js';
import { isRaidActive, clearRaidMode } from '../../services/antiRaid.js';
import { COLORS, EMOJIS } from '../../config/constants.js';

export const data = new SlashCommandBuilder()
  .setName('raid')
  .setDescription('Manage anti-raid defensive status and lockdowns')
  .addSubcommand((sub) => sub.setName('status').setDescription('Check if raid mode is currently engaged'))
  .addSubcommand((sub) => sub.setName('clear').setDescription('Dismiss raid lockdown and reset join velocity counters'))
  .setDefaultMemberPermissions(PermissionFlagsBits.ManageGuild);

export async function execute(interaction: ChatInputCommandInteraction): Promise<void> {
  const guild = interaction.guild;
  if (!guild) return;

  const subcommand = interaction.options.getSubcommand();
  const active = isRaidActive(guild.id);

  if (subcommand === 'status') {
    const embed = new EmbedBuilder()
      .setColor(active ? COLORS.DANGER : COLORS.SUCCESS)
      .setTitle(`🛡️ Server Raid Status: ${guild.name}`)
      .setDescription(
        active
          ? `${EMOJIS.WARNING} **Raid Mode is currently ACTIVE.** A high-velocity join spike was detected.`
          : `${EMOJIS.SUCCESS} **Normal Status.** Join velocity is within safe operating parameters.`
      );
    await interaction.reply({ embeds: [embed], ephemeral: true });
    return;
  }

  if (subcommand === 'clear') {
    clearRaidMode(guild.id);
    await interaction.reply({
      content: `${EMOJIS.SUCCESS} Raid mode cleared. Join tracking counters have been reset.`,
      ephemeral: true,
    });
  }
}
