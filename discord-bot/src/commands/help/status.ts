import {
  SlashCommandBuilder,
  ChatInputCommandInteraction,
  EmbedBuilder,
  ActionRowBuilder,
  ButtonBuilder,
  ButtonStyle,
} from 'discord.js';
import { getPlatformMonitors, getActiveIncidents } from '../../database/repositories/storeRepository.js';
import { COLORS, EMOJIS } from '../../config/constants.js';

export const data = new SlashCommandBuilder()
  .setName('status')
  .setDescription('View live platform infrastructure, database, and node uptime status');

export async function execute(interaction: ChatInputCommandInteraction): Promise<void> {
  await interaction.deferReply();

  const [monitors, incidents] = await Promise.all([
    getPlatformMonitors(),
    getActiveIncidents(),
  ]);

  const allOperational = monitors.every((m) => m.current_status === 'operational');
  const embedColor = incidents.length > 0 ? COLORS.DANGER : allOperational ? COLORS.SUCCESS : COLORS.WARNING;

  const embed = new EmbedBuilder()
    .setColor(embedColor)
    .setTitle(`${EMOJIS.SERVER} STX Enterprises System Health & Telemetry`)
    .setDescription(
      allOperational && incidents.length === 0
        ? `${EMOJIS.SUCCESS} **All Systems Operational.** All cloud infrastructure, database nodes, edge proxies, and mail servers are running normally.`
        : `${EMOJIS.WARNING} **Degraded Performance or Incident Reported.** See details below.`
    )
    .setFooter({ text: 'Updated in real-time from platform telemetry probes' })
    .setTimestamp();

  // Active Incidents
  if (incidents.length > 0) {
    const incidentList = incidents
      .map((inc) => `🚨 **${inc.title}**\nStatus: \`${inc.status}\` | Severity: \`${inc.severity}\``)
      .join('\n\n');
    embed.addFields({ name: 'Active Incidents & Maintenance', value: incidentList });
  }

  // System Probes
  if (monitors.length > 0) {
    const statusPills: Record<string, string> = {
      operational: '🟢 Operational',
      degraded: '🟡 Degraded',
      down: '🔴 Down',
    };

    const monitorLines = monitors
      .map(
        (m) =>
          `• **${m.public_label}**: ${statusPills[m.current_status] || m.current_status} ` +
          `(${m.uptime_percentage}% 30d • \`${m.last_latency_ms}ms\`)`
      )
      .join('\n');

    embed.addFields({ name: 'Infrastructure Probes', value: monitorLines });
  }

  const row = new ActionRowBuilder<ButtonBuilder>().addComponents(
    new ButtonBuilder()
      .setLabel('Public Status Page')
      .setURL('http://localhost:8000/status')
      .setStyle(ButtonStyle.Link)
  );

  await interaction.editReply({ embeds: [embed], components: [row] });
}
