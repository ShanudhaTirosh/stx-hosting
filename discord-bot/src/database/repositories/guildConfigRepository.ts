import { getSupabase } from '../supabase.js';
import { logger } from '../../utils/logger.js';

export interface GuildConfig {
  guild_id: string;
  name: string;
  welcome_channel_id: string | null;
  welcome_message: string | null;
  welcome_embed_enabled: boolean;
  rules_channel_id: string | null;
  goodbye_channel_id: string | null;
  goodbye_message: string | null;
  default_role_id: string | null;
  verified_role_id: string | null;
  verification_type: 'none' | 'button' | 'captcha';
  min_account_age_days: number;
  honeypot_channel_id: string | null;
  honeypot_action: 'ban' | 'timeout';
  honeypot_delete_days: number;
  mod_log_channel_id: string | null;
  ticket_category_id: string | null;
  ticket_log_channel_id: string | null;
  ticket_panel_channel_id: string | null;
  ticket_panel_message_id: string | null;
  staff_role_ids: string[];
  admin_role_ids: string[];
  raid_mode_enabled: boolean;
  raid_threshold_joins: number;
  raid_threshold_seconds: number;
  spam_filter_enabled: boolean;
  phishing_filter_enabled: boolean;
  invite_filter_enabled: boolean;
  suggestion_channel_id: string | null;
  created_at?: string;
  updated_at?: string;
}

// In-memory hot cache for high throughput Discord event handling
const configCache = new Map<string, { config: GuildConfig; cachedAt: number }>();
const CACHE_TTL_MS = 60_000; // 60 seconds

export async function getGuildConfig(guildId: string): Promise<GuildConfig | null> {
  const cached = configCache.get(guildId);
  if (cached && Date.now() - cached.cachedAt < CACHE_TTL_MS) {
    return cached.config;
  }

  const supabase = getSupabase();
  const { data, error } = await supabase
    .from('discord_guild_configs')
    .select('*')
    .eq('guild_id', guildId)
    .maybeSingle();

  if (error) {
    logger.error({ error, guildId }, 'Failed to fetch discord_guild_configs');
    return null;
  }

  if (data) {
    const config = data as GuildConfig;
    configCache.set(guildId, { config, cachedAt: Date.now() });
    return config;
  }

  return null;
}

export async function upsertGuildConfig(
  guildId: string,
  updates: Partial<GuildConfig> & { name?: string }
): Promise<GuildConfig | null> {
  const supabase = getSupabase();
  const payload = {
    guild_id: guildId,
    name: updates.name ?? 'Server',
    ...updates,
    updated_at: new Date().toISOString(),
  };

  const { data, error } = await supabase
    .from('discord_guild_configs')
    .upsert(payload, { onConflict: 'guild_id' })
    .select()
    .single();

  if (error) {
    logger.error({ error, guildId }, 'Failed to upsert discord_guild_configs');
    return null;
  }

  const config = data as GuildConfig;
  configCache.set(guildId, { config, cachedAt: Date.now() });
  return config;
}

export function invalidateGuildConfig(guildId: string): void {
  configCache.delete(guildId);
}
