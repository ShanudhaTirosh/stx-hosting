import { getSupabase } from '../supabase.js';
import { logger } from '../../utils/logger.js';

export interface FaqRecord {
  id?: string;
  guild_id: string;
  keyword: string;
  question: string;
  answer: string;
  created_by?: string;
}

export interface SuggestionRecord {
  id: string;
  guild_id: string;
  message_id: string;
  author_id: string;
  author_name: string;
  suggestion: string;
  status: 'pending' | 'approved' | 'rejected' | 'implemented';
  staff_response?: string;
  upvotes: number;
  downvotes: number;
}

export async function getFaq(guildId: string, keyword: string): Promise<FaqRecord | null> {
  const supabase = getSupabase();
  const { data, error } = await supabase
    .from('discord_faqs')
    .select('*')
    .eq('guild_id', guildId)
    .eq('keyword', keyword.toLowerCase().trim())
    .maybeSingle();

  if (error) {
    logger.error({ error, guildId, keyword }, 'Error fetching FAQ');
    return null;
  }

  return data as FaqRecord | null;
}

export async function listFaqs(guildId: string): Promise<FaqRecord[]> {
  const supabase = getSupabase();
  const { data, error } = await supabase
    .from('discord_faqs')
    .select('keyword, question, answer')
    .eq('guild_id', guildId)
    .order('keyword', { ascending: true });

  if (error) {
    logger.error({ error, guildId }, 'Error listing FAQs');
    return [];
  }

  return (data as FaqRecord[]) || [];
}

export async function upsertFaq(faq: FaqRecord): Promise<boolean> {
  const supabase = getSupabase();
  const { error } = await supabase.from('discord_faqs').upsert(
    {
      guild_id: faq.guild_id,
      keyword: faq.keyword.toLowerCase().trim(),
      question: faq.question,
      answer: faq.answer,
      created_by: faq.created_by,
      updated_at: new Date().toISOString(),
    },
    { onConflict: 'guild_id,keyword' }
  );

  if (error) {
    logger.error({ error, faq }, 'Error upserting FAQ');
    return false;
  }

  return true;
}

export async function createSuggestion(record: {
  guild_id: string;
  message_id: string;
  author_id: string;
  author_name: string;
  suggestion: string;
}): Promise<SuggestionRecord | null> {
  const supabase = getSupabase();
  const { data, error } = await supabase
    .from('discord_suggestions')
    .insert({
      guild_id: record.guild_id,
      message_id: record.message_id,
      author_id: record.author_id,
      author_name: record.author_name,
      suggestion: record.suggestion,
      status: 'pending',
      upvotes: 0,
      downvotes: 0,
    })
    .select()
    .single();

  if (error) {
    logger.error({ error, record }, 'Error creating suggestion');
    return null;
  }

  return data as SuggestionRecord;
}

export async function updateSuggestionStatus(
  messageId: string,
  status: 'pending' | 'approved' | 'rejected' | 'implemented',
  staffResponse?: string
): Promise<SuggestionRecord | null> {
  const supabase = getSupabase();
  const { data, error } = await supabase
    .from('discord_suggestions')
    .update({
      status,
      staff_response: staffResponse,
      updated_at: new Date().toISOString(),
    })
    .eq('message_id', messageId)
    .select()
    .single();

  if (error) {
    logger.error({ error, messageId, status }, 'Error updating suggestion status');
    return null;
  }

  return data as SuggestionRecord;
}
