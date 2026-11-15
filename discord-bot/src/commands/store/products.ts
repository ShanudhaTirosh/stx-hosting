import {
  SlashCommandBuilder,
  ChatInputCommandInteraction,
  EmbedBuilder,
  ActionRowBuilder,
  ButtonBuilder,
  ButtonStyle,
} from 'discord.js';
import { searchProducts } from '../../database/repositories/storeRepository.js';
import { COLORS, EMOJIS } from '../../config/constants.js';

export const data = new SlashCommandBuilder()
  .setName('products')
  .setDescription('Search platform products, servers, and pricing')
  .addStringOption((option) =>
    option.setName('query').setDescription('Search term (e.g. VPS, Palworld, Postgres)').setRequired(false)
  )
  .addStringOption((option) =>
    option
      .setName('category')
      .setDescription('Filter by product category')
      .setRequired(false)
      .addChoices(
        { name: 'Cloud VPS', value: 'vps' },
        { name: 'App Hosting', value: 'app_hosting' },
        { name: 'Managed Database', value: 'database' },
        { name: 'Email Hosting', value: 'email_hosting' },
        { name: 'Web Hosting', value: 'web_hosting' }
      )
  );

export async function execute(interaction: ChatInputCommandInteraction): Promise<void> {
  const query = interaction.options.getString('query') || undefined;
  const category = interaction.options.getString('category') || undefined;

  await interaction.deferReply();

  const products = await searchProducts(query, category);

  if (products.length === 0) {
    await interaction.editReply({
      content: `${EMOJIS.WARNING} No products found matching your search. Try running \`/store\` to view all categories.`,
    });
    return;
  }

  const embeds: EmbedBuilder[] = [];

  for (const product of products.slice(0, 5)) {
    const embed = new EmbedBuilder()
      .setColor(COLORS.STX_BLUE)
      .setTitle(`${EMOJIS.STORE} ${product.name}`)
      .setDescription(product.description || 'High performance managed hosting service.')
      .addFields({ name: 'Category', value: `\`${product.category}\``, inline: true });

    if (product.variants.length > 0) {
      const variantList = product.variants
        .map((v) => {
          const spec = [];
          if (v.vcpu) spec.push(`${v.vcpu} vCPU`);
          if (v.ram_mb) spec.push(`${v.ram_mb >= 1024 ? v.ram_mb / 1024 + 'GB' : v.ram_mb + 'MB'} RAM`);
          if (v.storage_gb) spec.push(`${v.storage_gb}GB NVMe`);

          const price = v.pricing[0]
            ? `$${(v.pricing[0].price_cents / 100).toFixed(2)}/${v.pricing[0].interval}`
            : 'Custom';

          return `• **${v.name}**: ${spec.join(' / ')} — **${price}**`;
        })
        .join('\n');

      embed.addFields({ name: 'Plans & Pricing', value: variantList || 'Contact sales' });
    }

    embeds.push(embed);
  }

  const row = new ActionRowBuilder<ButtonBuilder>().addComponents(
    new ButtonBuilder()
      .setLabel('View Full Storefront')
      .setURL('http://localhost:8000')
      .setStyle(ButtonStyle.Link)
  );

  await interaction.editReply({ embeds, components: [row] });
}
