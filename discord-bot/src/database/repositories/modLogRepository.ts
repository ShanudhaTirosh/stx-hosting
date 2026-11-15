import { getSupabase } from '../supabase.js';
import { logger } from '../../utils/logger.js';

export interface ModLogRecord {
  id?: string;
  guild_id: string;
  target_user_id: string;
  moderator_id: string;
  action: 'warn' | 'timeout' | 'kick' | 'ban' | 'unban' | 'honeypot_trigger' | 'raid_lockdown' | 'phishing_block' | 'purge';
  reason?: string;
  metadata?: Record<string, any>;
  created_at?: string;
}

export interface UserNoteRecord {
  id?: string;
  guild_id: string;
  target_user_id: string;
  moderator_id: string;
  note: string;
  created_at?: string;
}

export async function logModAction(log: ModLogRecord): Promise<void> {
  const supabase = getSupabase();
  const { error } = await supabase.from('discord_mod_logs').insert({
    guild_id: log.guild_id,
    target_user_id: log.target_user_id,
    moderator_id: log.moderator_id,
    action: log.action,
    reason: log.reason ?? null,
    metadata: log.metadata ?? {},
    created_at: new Date().toISOString(),
  });

  if (error) {
    logger.error({ error, log }, 'Error logging moderation action');
  }
}

export async function getModLogsForUser(guildId: string, targetUserId: string): Promise<ModLogRecord[]> {
  const supabase = getSupabase();
  const { data, error } = await supabase
    .from('discord_mod_logs')
    .select('*')
    .eq('guild_id', guildId)
    .eq('target_user_id', targetUserId)
    .order('created_at', { ascending: false })
    .limit(50);

  if (error) {
    logger.error({ error, guildId, targetUserId }, 'Error fetching mod logs for user');
    return [];
  }

  return (data as ModLogRecord[]) ?? [];
}

export async function addUserNote(guildId: string, targetUserId: string, moderatorId: string, note: string): Promise<boolean> {
  const supabase = getSupabase();
  const { error } = await supabase.from('discord_user_notes').insert({
    guild_id: guildId,
    target_user_id: targetUserId,
    moderator_id: moderatorId,
    note,
    created_at: new Date().toISOString(),
  });

  if (error) {
    logger.error({ error, guildId, targetUserId }, 'Error adding user note');
    return false;
  }

  return true;
}

export async function getUserNotes(guildId: string, targetUserId: string): Promise<UserNoteRecord[]> {
  const supabase = getSupabase();
  const { data, error } = await supabase
    .from('discord_user_notes')
    .select('*')
    .eq('guild_id', guildId)
    .eq('target_user_id', targetUserId)
    .order('created_at', { ascending: false });

  if (error) {
    logger.error({ error, guildId, targetUserId }, 'Error fetching user notes');
    return [];
  }

  return (data as UserNoteRecord[]) ?? [];
}
