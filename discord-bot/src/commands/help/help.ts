import {
  SlashCommandBuilder,
  ChatInputCommandInteraction,
  EmbedBuilder,
  PermissionFlagsBits,
} from 'discord.js';
import { getGuildConfig } from '../../database/repositories/guildConfigRepository.js';
import { COLORS, EMOJIS } from '../../config/constants.js';

export const data = new SlashCommandBuilder()
  .setName('help')
  .setDescription('List all commands and tools available to you');

export async function execute(interaction: ChatInputCommandInteraction): Promise<void> {
  const guild = interaction.guild;
  const member = interaction.member as any;

  let isStaff = false;
  let isAdmin = false;

  if (guild && member) {
    const config = await getGuildConfig(guild.id);
    isAdmin = member.permissions.has(PermissionFlagsBits.Administrator) || member.permissions.has(PermissionFlagsBits.ManageGuild);
    isStaff =
      isAdmin ||
      member.permissions.has(PermissionFlagsBits.ModerateMembers) ||
      (config?.staff_role_ids || []).some((id: string) => member.roles?.cache.has(id));
  }

  const embed = new EmbedBuilder()
    .setColor(COLORS.STX_BLUE)
    .setTitle(`${EMOJIS.SHIELD} STX Cloud Bot Command Reference`)
    .setDescription(`Here are the commands available to your role on this server:\n`)
    .addFields({
      name: '🛒 Store & Commerce',
      value:
        `• \`/store\` — Browse all available hosting categories\n` +
        `• \`/products [query] [category]\` — Search products & pricing tiers\n` +
        `• \`/order <order_id> <email>\` — Securely check order status (ephemeral)\n` +
        `• \`/link\` — Link your Discord account to your STX platform profile`,
    })
    .addFields({
      name: '🎫 Support & Help',
      value:
        `• \`/ticket open <topic> [subject]\` — Open a private staff ticket\n` +
        `• \`/status\` — View live platform health & node telemetry\n` +
        `• \`/faq show <keyword>\` — Look up quick answers to common questions\n` +
        `• \`/faq list\` — List all knowledgebase FAQ topics\n` +
        `• \`/suggest <idea>\` — Submit an idea to the suggestions board`,
    });

  if (isStaff) {
    embed.addFields({
      name: '🛡️ Staff Moderation & Tickets',
      value:
        `• \`/ticket claim | unclaim | close | add | remove | priority\`\n` +
        `• \`/warn <user> <reason>\` — Issue official infraction\n` +
        `• \`/timeout <user> <duration> <reason>\` — Apply native Discord timeout\n` +
        `• \`/kick <user> <reason>\` — Kick member with audit log\n` +
        `• \`/ban <user> [delete_days] <reason>\` — Ban member & purge messages\n` +
        `• \`/unban <user_id> <reason>\` — Revoke ban with reason\n` +
        `• \`/purge <count> [user]\` — Bulk delete up to 100 messages\n` +
        `• \`/modlogs <user>\` — View moderation history & honeypot triggers\n` +
        `• \`/note <add|list> <user>\` — Manage internal staff notes\n` +
        `• \`/faq set <keyword> <question> <answer>\` — Update FAQ entry`,
    });
  }

  if (isAdmin) {
    embed.addFields({
      name: '⚙️ Server Administration',
      value:
        `• \`/setup\` — Interactive server configuration wizard\n` +
        `• \`/config <setting> <value>\` — Adjust bot channels, roles, and thresholds\n` +
        `• \`/ticket panel\` — Deploy persistent ticket dropdown panel\n` +
        `• \`/raid <status|clear|lockdown>\` — Manage defensive raid mode`,
    });
  }

  embed.setFooter({ text: 'STX Enterprises Cloud Platform • Role-Based Access Control' });

  await interaction.reply({ embeds: [embed], ephemeral: true });
}
