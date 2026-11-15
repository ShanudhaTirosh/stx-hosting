import {
  SlashCommandBuilder,
  ChatInputCommandInteraction,
  EmbedBuilder,
  ActionRowBuilder,
  ButtonBuilder,
  ButtonStyle,
} from 'discord.js';
import { lookupOrder } from '../../database/repositories/storeRepository.js';
import { COLORS, EMOJIS } from '../../config/constants.js';
import { env } from '../../config/env.js';

// Sliding window rate limiter for anti-guessing brute-force protection
// Map: userId -> array of failed timestamp ms
const orderAttemptHistory = new Map<string, number[]>();

export const data = new SlashCommandBuilder()
  .setName('order')
  .setDescription('Check the status of your order securely (ephemeral)')
  .addStringOption((option) =>
    option.setName('order_id').setDescription('Your Order UUID or Order Number').setRequired(true)
  )
  .addStringOption((option) =>
    option.setName('email').setDescription('The email address associated with your account').setRequired(true)
  );

export async function execute(interaction: ChatInputCommandInteraction): Promise<void> {
  const userId = interaction.user.id;
  const now = Date.now();
  const windowMs = env.ORDER_RATE_LIMIT_WINDOW_SECONDS * 1000;
  const maxAttempts = env.ORDER_RATE_LIMIT_ATTEMPTS;

  // 1. Check rate limit
  let attempts = orderAttemptHistory.get(userId) || [];
  attempts = attempts.filter((t) => now - t <= windowMs);
  orderAttemptHistory.set(userId, attempts);

  if (attempts.length >= maxAttempts) {
    const remainingMinutes = Math.ceil((windowMs - (now - attempts[0])) / 60000);
    await interaction.reply({
      content: `${EMOJIS.ERROR} **Too many lookup attempts.** For security and anti-guessing protection, please wait ${remainingMinutes} minute(s) before trying again.`,
      ephemeral: true,
    });
    return;
  }

  const orderId = interaction.options.getString('order_id', true).trim();
  const email = interaction.options.getString('email', true).trim();

  // Always reply ephemerally for privacy
  await interaction.deferReply({ ephemeral: true });

  const order = await lookupOrder(orderId, email);

  if (!order) {
    // Record failed attempt
    attempts.push(now);
    orderAttemptHistory.set(userId, attempts);

    // Generic error message: Never disclose whether the order ID exists!
    await interaction.editReply({
      content: `${EMOJIS.WARNING} No matching order was found with that ID and email combination. Please check your order confirmation details and try again.`,
    });
    return;
  }

  // Success: Clear failed attempts on match
  orderAttemptHistory.delete(userId);

  const formattedTotal = (order.total_cents / 100).toLocaleString('en-US', {
    style: 'currency',
    currency: order.currency || 'USD',
  });

  const statusEmojis: Record<string, string> = {
    pending: '🟡 Pending Payment',
    payment_submitted: '🟠 Verifying Payment',
    paid: '🟢 Paid / Provisioning',
    completed: '✅ Completed / Active',
    cancelled: '⚪ Cancelled',
    refunded: '↩️ Refunded',
  };

  const statusLabel = statusEmojis[order.status] || order.status.toUpperCase();

  const embed = new EmbedBuilder()
    .setColor(COLORS.SUCCESS)
    .setTitle(`${EMOJIS.STORE} Order Details: ${order.uuid.slice(0, 8)}...`)
    .setDescription(`Your order information has been verified and retrieved.`)
    .addFields(
      { name: 'Status', value: statusLabel, inline: true },
      { name: 'Total', value: formattedTotal, inline: true },
      { name: 'Placed On', value: `<t:${Math.floor(new Date(order.created_at).getTime() / 1000)}:f>`, inline: true }
    )
    .setFooter({ text: 'Visible only to you • STX Enterprises Cloud Platform' })
    .setTimestamp();

  if (order.items.length > 0) {
    const itemsList = order.items
      .map((item) => `• **${item.product_name}** x${item.quantity}` + (item.hostname ? ` (\`${item.hostname}\`)` : ''))
      .join('\n');
    embed.addFields({ name: 'Order Items', value: itemsList });
  }

  const row = new ActionRowBuilder<ButtonBuilder>().addComponents(
    new ButtonBuilder()
      .setLabel('View in Customer Portal')
      .setURL('http://localhost:8000/portal')
      .setStyle(ButtonStyle.Link)
  );

  await interaction.editReply({ embeds: [embed], components: [row] });
}
