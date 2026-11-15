import {
  SlashCommandBuilder,
  ChatInputCommandInteraction,
  EmbedBuilder,
  ActionRowBuilder,
  ButtonBuilder,
  ButtonStyle,
} from 'discord.js';
import { getAllCategories } from '../../database/repositories/storeRepository.js';
import { COLORS, EMOJIS } from '../../config/constants.js';

export const data = new SlashCommandBuilder()
  .setName('store')
  .setDescription('Browse all product categories and hosting plans');

export async function execute(interaction: ChatInputCommandInteraction): Promise<void> {
  await interaction.deferReply();

  const categories = await getAllCategories();

  const embed = new EmbedBuilder()
    .setColor(COLORS.STX_BLUE)
    .setTitle(`${EMOJIS.STORE} STX Enterprises Cloud Hosting Catalog`)
    .setDescription(
      `Welcome to our cloud infrastructure catalog! We provide managed hosting, bare-metal performance, and enterprise security.\n\n` +
        `**Available Infrastructure Categories:**\n` +
        `🖥️ **Cloud VPS**: Root access, high-frequency AMD EPYC/Ryzen NVMe servers.\n` +
        `🌐 **App Hosting**: 1-Click container deployments (Docker, Railpack, Git push).\n` +
        `🗄️ **Managed Databases**: High-availability PostgreSQL, MySQL, Redis, MongoDB.\n` +
        `📧 **Email Hosting**: WildDuck enterprise mailboxes with pooled storage & DKIM.\n` +
        `🎮 **Game Server Hosting**: Dedicated low-latency gaming instances.`
    )
    .addFields(
      categories.map((c) => ({
        name: c.category.toUpperCase().replace('_', ' '),
        value: `${c.count} plan(s) available`,
        inline: true,
      }))
    )
    .setFooter({ text: 'Use /products <query> to search specific plans' })
    .setTimestamp();

  const row = new ActionRowBuilder<ButtonBuilder>().addComponents(
    new ButtonBuilder()
      .setLabel('Visit Storefront')
      .setURL('http://localhost:8000')
      .setStyle(ButtonStyle.Link),
    new ButtonBuilder()
      .setLabel('Customer Portal')
      .setURL('http://localhost:8000/portal')
      .setStyle(ButtonStyle.Link)
  );

  await interaction.editReply({ embeds: [embed], components: [row] });
}
