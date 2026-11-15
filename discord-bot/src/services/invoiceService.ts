import { Client, EmbedBuilder, ActionRowBuilder, ButtonBuilder, ButtonStyle, TextChannel } from 'discord.js';
import { getDiscordIdByPlatformUserId } from '../database/repositories/accountLinkRepository.js';
import { getSupabase } from '../database/supabase.js';
import { COLORS, EMOJIS } from '../config/constants.js';
import { logger } from '../utils/logger.js';

export interface InvoicePaidPayload {
  invoice_number: string;
  order_uuid?: string;
  platform_user_id: string;
  total_cents: number;
  currency: string;
  pdf_url?: string;
  fulfillment_note?: string;
}

export async function processPaidInvoiceNotification(
  client: Client,
  payload: InvoicePaidPayload
): Promise<{ delivered: boolean; channel: 'dm' | 'ticket' | 'dashboard_fallback' | 'not_linked' }> {
  const { invoice_number, platform_user_id, total_cents, currency, fulfillment_note } = payload;

  logger.info({ invoice_number, platform_user_id }, 'Processing invoice paid notification for Discord user');

  // 1. Look up Discord user Snowflake ID
  const discordUserId = await getDiscordIdByPlatformUserId(platform_user_id);
  if (!discordUserId) {
    logger.info({ platform_user_id }, 'No Discord account linked for this platform user');
    return { delivered: false, channel: 'not_linked' };
  }

  // 2. Resolve Discord user
  let discordUser;
  try {
    discordUser = await client.users.fetch(discordUserId);
  } catch (err) {
    logger.error({ err, discordUserId }, 'Failed to fetch Discord user from API');
    return { delivered: false, channel: 'dashboard_fallback' };
  }

  // 3. Format formatted money amount
  const formattedAmount = (total_cents / 100).toLocaleString('en-US', {
    style: 'currency',
    currency: currency || 'USD',
  });

  const embed = new EmbedBuilder()
    .setColor(COLORS.SUCCESS)
    .setTitle(`${EMOJIS.SUCCESS} Payment Received: Invoice #${invoice_number}`)
    .setDescription(
      `Hello ${discordUser.username}! We have verified and approved your payment.\n\n` +
        `**Amount Paid**: ${formattedAmount}\n` +
        `**Invoice Number**: \`${invoice_number}\`\n` +
        (fulfillment_note ? `\n**Fulfillment Status**:\n${fulfillment_note}\n` : '') +
        `\nYou can manage your services and download official PDF receipts from your client portal.`
    )
    .setFooter({ text: 'STX Enterprises Cloud Platform • Automatic Billing' })
    .setTimestamp();

  const portalBtn = new ButtonBuilder()
    .setLabel('Open Customer Portal')
    .setURL('http://localhost:8000/portal/billing')
    .setStyle(ButtonStyle.Link);

  const row = new ActionRowBuilder<ButtonBuilder>().addComponents(portalBtn);

  // 4. Attempt DM delivery
  try {
    await discordUser.send({ embeds: [embed], components: [row] });
    logger.info({ discordUserId, invoice_number }, 'Invoice successfully delivered via DM');
    return { delivered: true, channel: 'dm' };
  } catch (err: any) {
    logger.warn({ err: err.message, code: err.code, discordUserId }, 'User DMs are closed or blocked');

    // 5. Closed DM Fallback: Check for an active support ticket
    const supabase = getSupabase();
    const { data: activeTicket } = await supabase
      .from('discord_tickets')
      .select('channel_id, guild_id')
      .eq('user_id', discordUserId)
      .neq('status', 'closed')
      .order('created_at', { ascending: false })
      .limit(1)
      .maybeSingle();

    if (activeTicket?.channel_id) {
      try {
        const ticketChannel = client.channels.cache.get(activeTicket.channel_id) as TextChannel;
        if (ticketChannel) {
          const fallbackEmbed = new EmbedBuilder()
            .setColor(COLORS.SUCCESS)
            .setTitle(`${EMOJIS.BILLING} Payment Approved: Invoice #${invoice_number}`)
            .setDescription(
              `Hello <@${discordUserId}>, your payment for invoice **#${invoice_number}** (${formattedAmount}) was approved!\n\n` +
                `*(Note: This notification was sent to your active private ticket because your direct messages are closed.)*`
            )
            .setTimestamp();

          await ticketChannel.send({ embeds: [fallbackEmbed], components: [row] });
          logger.info({ channelId: activeTicket.channel_id }, 'Delivered invoice notice to active ticket channel fallback');
          return { delivered: true, channel: 'ticket' };
        }
      } catch (ticketErr) {
        logger.error({ ticketErr }, 'Failed to deliver invoice to active ticket channel');
      }
    }

    // 6. Safe Dashboard Notice (Never disclose invoice in public channels!)
    logger.info({ discordUserId, invoice_number }, 'Queued private invoice alert in customer dashboard notice');
    return { delivered: false, channel: 'dashboard_fallback' };
  }
}
