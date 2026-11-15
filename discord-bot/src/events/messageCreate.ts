import { Message } from 'discord.js';
import { getGuildConfig } from '../database/repositories/guildConfigRepository.js';
import { handleHoneypotTrigger } from '../services/honeypot.js';
import { checkSpamAndMentions } from '../services/antiSpam.js';
import { checkPhishingAndInvites } from '../services/phishingFilter.js';
import { getTicketByChannel, recordTicketMessage } from '../database/repositories/ticketRepository.js';
import { logger } from '../utils/logger.js';

export async function onMessageCreate(message: Message): Promise<void> {
  // Ignore DMs and bot messages for server filtering
  if (!message.guild || message.author.bot) {
    return;
  }

  const guildId = message.guild.id;
  const config = await getGuildConfig(guildId);
  if (!config) return;

  // 1. HONEYPOT DECOY TRAP CHECK
  const triggeredHoneypot = await handleHoneypotTrigger(message, config);
  if (triggeredHoneypot) {
    return; // Offender handled, stop further processing
  }

  // 2. PHISHING, FAKE NITRO & SCAM DOMAINS CHECK
  const triggeredPhishing = await checkPhishingAndInvites(message, config);
  if (triggeredPhishing) {
    return;
  }

  // 3. RAPID DUPLICATE SPAM & MASS MENTION CHECK
  const triggeredSpam = await checkSpamAndMentions(message, config);
  if (triggeredSpam) {
    return;
  }

  // 4. SUPPORT TICKET AUDIT & TRANSCRIPT LOGGING
  // If message was posted in an active ticket channel, record it
  try {
    const ticket = await getTicketByChannel(message.channel.id);
    if (ticket && ticket.status !== 'closed') {
      const isStaff =
        config.staff_role_ids.some((id) => message.member?.roles.cache.has(id)) ||
        message.member?.permissions.has('ModerateMembers') ||
        false;

      const attachments = Array.from(message.attachments.values()).map((a) => ({
        id: a.id,
        name: a.name,
        url: a.url,
        contentType: a.contentType,
      }));

      await recordTicketMessage({
        ticket_id: ticket.id,
        author_id: message.author.id,
        author_name: message.author.tag,
        content: message.content,
        attachments,
        is_staff: Boolean(isStaff),
        sent_at: message.createdAt.toISOString(),
      });
    }
  } catch (err: any) {
    logger.error({ err: err.message, channelId: message.channel.id }, 'Error recording ticket message');
  }
}
