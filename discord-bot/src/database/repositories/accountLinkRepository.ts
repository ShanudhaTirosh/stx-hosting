import crypto from 'node:crypto';
import { getSupabase } from '../supabase.js';
import { logger } from '../../utils/logger.js';

export interface AccountLinkRecord {
  id?: string;
  discord_user_id: string;
  platform_user_id: string | null;
  link_code: string | null;
  code_expires_at: string | null;
  verified_at: string | null;
}

export async function createLinkCode(discordUserId: string): Promise<string> {
  const supabase = getSupabase();
  // Generate secure 6-character uppercase alphanumeric code (excluding ambiguous chars 0, O, 1, I)
  const chars = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
  let code = '';
  const randomBytes = crypto.randomBytes(6);
  for (let i = 0; i < 6; i++) {
    code += chars[randomBytes[i] % chars.length];
  }

  const expiresAt = new Date(Date.now() + 10 * 60 * 1000).toISOString(); // 10 minutes

  const { error } = await supabase.from('discord_account_links').upsert(
    {
      discord_user_id: discordUserId,
      link_code: code,
      code_expires_at: expiresAt,
      updated_at: new Date().toISOString(),
    },
    { onConflict: 'discord_user_id' }
  );

  if (error) {
    logger.error({ error, discordUserId }, 'Error creating link code');
    throw new Error('Failed to generate account link code.');
  }

  return code;
}

export async function verifyLinkCode(code: string, platformUserId: string): Promise<boolean> {
  const supabase = getSupabase();
  const normalizedCode = code.trim().toUpperCase();

  const { data: record, error } = await supabase
    .from('discord_account_links')
    .select('*')
    .eq('link_code', normalizedCode)
    .gt('code_expires_at', new Date().toISOString())
    .maybeSingle();

  if (error || !record) {
    return false;
  }

  const { error: updateError } = await supabase
    .from('discord_account_links')
    .update({
      platform_user_id: platformUserId,
      link_code: null,
      code_expires_at: null,
      verified_at: new Date().toISOString(),
      updated_at: new Date().toISOString(),
    })
    .eq('id', record.id);

  if (updateError) {
    logger.error({ updateError, code }, 'Error updating verified link code');
    return false;
  }

  return true;
}

export async function getDiscordIdByPlatformUserId(platformUserId: string): Promise<string | null> {
  const supabase = getSupabase();

  // 1. Check direct bot link
  const { data: link } = await supabase
    .from('discord_account_links')
    .select('discord_user_id')
    .eq('platform_user_id', platformUserId)
    .not('verified_at', 'is', null)
    .maybeSingle();

  if (link?.discord_user_id) {
    return link.discord_user_id;
  }

  // 2. Fall back to oauth_identities table if user logged in via Discord Socialite
  const { data: oauth } = await supabase
    .from('oauth_identities')
    .select('provider_user_id')
    .eq('user_id', platformUserId)
    .eq('provider', 'discord')
    .maybeSingle();

  return oauth?.provider_user_id ?? null;
}

export async function deleteAccountLink(discordUserId: string): Promise<boolean> {
  const supabase = getSupabase();
  const { error } = await supabase
    .from('discord_account_links')
    .delete()
    .eq('discord_user_id', discordUserId);

  if (error) {
    logger.error({ error, discordUserId }, 'Error deleting account link');
    return false;
  }

  return true;
}
