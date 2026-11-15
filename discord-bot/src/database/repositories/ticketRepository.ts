import { getSupabase } from '../supabase.js';
import { logger } from '../../utils/logger.js';

export interface TicketRecord {
  id: string;
  ticket_number: number;
  guild_id: string;
  channel_id: string;
  user_id: string;
  topic: string;
  status: 'open' | 'claimed' | 'closed';
  priority: 'low' | 'standard' | 'high' | 'urgent';
  claimed_by_id: string | null;
  claimed_at: string | null;
  first_response_at: string | null;
  closed_at: string | null;
  closed_by_id: string | null;
  close_reason: string | null;
  transcript_url: string | null;
  transcript_text: string | null;
  rating: number | null;
  rating_feedback: string | null;
  last_activity_at: string;
  created_at: string;
  updated_at: string;
}

export interface TicketMessageRecord {
  id?: string;
  ticket_id: string;
  author_id: string;
  author_name: string;
  content: string;
  attachments?: any[];
  is_staff?: boolean;
  sent_at?: string;
}

export async function createTicket(params: {
  guild_id: string;
  channel_id: string;
  user_id: string;
  topic: string;
  priority?: 'low' | 'standard' | 'high' | 'urgent';
}): Promise<TicketRecord | null> {
  const supabase = getSupabase();
  const { data, error } = await supabase
    .from('discord_tickets')
    .insert({
      guild_id: params.guild_id,
      channel_id: params.channel_id,
      user_id: params.user_id,
      topic: params.topic,
      priority: params.priority ?? 'standard',
      status: 'open',
    })
    .select()
    .single();

  if (error) {
    logger.error({ error, params }, 'Error creating ticket record');
    return null;
  }

  return data as TicketRecord;
}

export async function getTicketByChannel(channelId: string): Promise<TicketRecord | null> {
  const supabase = getSupabase();
  const { data, error } = await supabase
    .from('discord_tickets')
    .select('*')
    .eq('channel_id', channelId)
    .maybeSingle();

  if (error) {
    logger.error({ error, channelId }, 'Error fetching ticket by channel');
    return null;
  }

  return data as TicketRecord | null;
}

export async function getTicketById(ticketId: string): Promise<TicketRecord | null> {
  const supabase = getSupabase();
  const { data, error } = await supabase
    .from('discord_tickets')
    .select('*')
    .eq('id', ticketId)
    .maybeSingle();

  if (error) {
    logger.error({ error, ticketId }, 'Error fetching ticket by ID');
    return null;
  }

  return data as TicketRecord | null;
}

export async function getActiveTicketsCount(guildId: string, userId: string): Promise<number> {
  const supabase = getSupabase();
  const { count, error } = await supabase
    .from('discord_tickets')
    .select('id', { count: 'exact', head: true })
    .eq('guild_id', guildId)
    .eq('user_id', userId)
    .neq('status', 'closed');

  if (error) {
    logger.error({ error, guildId, userId }, 'Error counting active tickets');
    return 0;
  }

  return count ?? 0;
}

export async function updateTicket(
  ticketId: string,
  updates: Partial<TicketRecord>
): Promise<TicketRecord | null> {
  const supabase = getSupabase();
  const { data, error } = await supabase
    .from('discord_tickets')
    .update({
      ...updates,
      updated_at: new Date().toISOString(),
    })
    .eq('id', ticketId)
    .select()
    .single();

  if (error) {
    logger.error({ error, ticketId, updates }, 'Error updating ticket');
    return null;
  }

  return data as TicketRecord;
}

export async function recordTicketMessage(msg: TicketMessageRecord): Promise<void> {
  const supabase = getSupabase();
  const { error } = await supabase.from('discord_ticket_messages').insert({
    ticket_id: msg.ticket_id,
    author_id: msg.author_id,
    author_name: msg.author_name,
    content: msg.content,
    attachments: msg.attachments ?? [],
    is_staff: msg.is_staff ?? false,
    sent_at: msg.sent_at ?? new Date().toISOString(),
  });

  if (error) {
    logger.error({ error, ticketId: msg.ticket_id }, 'Error recording ticket message');
  }
}

export async function getTicketMessages(ticketId: string): Promise<TicketMessageRecord[]> {
  const supabase = getSupabase();
  const { data, error } = await supabase
    .from('discord_ticket_messages')
    .select('*')
    .eq('ticket_id', ticketId)
    .order('sent_at', { ascending: true });

  if (error) {
    logger.error({ error, ticketId }, 'Error fetching ticket messages');
    return [];
  }

  return data as TicketMessageRecord[];
}
