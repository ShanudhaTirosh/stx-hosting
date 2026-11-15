-- 20261115000011_discord_bot_schema.sql
-- STX Enterprises Cloud Platform - Discord Bot Database Schema
-- Provides multi-guild configs, account linking, tickets, mod logs, FAQs, suggestions, and audit trails.

-- 1. Discord Guild Configurations Table
CREATE TABLE IF NOT EXISTS public.discord_guild_configs (
    guild_id TEXT PRIMARY KEY,
    name TEXT NOT NULL,
    welcome_channel_id TEXT,
    welcome_message TEXT DEFAULT 'Welcome to STX Enterprises Cloud Platform, {user}! Please check out our rules and get verified.',
    welcome_embed_enabled BOOLEAN NOT NULL DEFAULT true,
    rules_channel_id TEXT,
    goodbye_channel_id TEXT,
    goodbye_message TEXT DEFAULT '{user} has left the server. We wish them the best!',
    default_role_id TEXT,
    verified_role_id TEXT,
    verification_type TEXT NOT NULL DEFAULT 'button', -- 'none', 'button', 'captcha'
    min_account_age_days INTEGER NOT NULL DEFAULT 0,
    honeypot_channel_id TEXT,
    honeypot_action TEXT NOT NULL DEFAULT 'ban', -- 'ban', 'timeout'
    honeypot_delete_days INTEGER NOT NULL DEFAULT 7,
    mod_log_channel_id TEXT,
    ticket_category_id TEXT,
    ticket_log_channel_id TEXT,
    ticket_panel_channel_id TEXT,
    ticket_panel_message_id TEXT,
    staff_role_ids TEXT[] NOT NULL DEFAULT '{}',
    admin_role_ids TEXT[] NOT NULL DEFAULT '{}',
    raid_mode_enabled BOOLEAN NOT NULL DEFAULT false,
    raid_threshold_joins INTEGER NOT NULL DEFAULT 10,
    raid_threshold_seconds INTEGER NOT NULL DEFAULT 10,
    spam_filter_enabled BOOLEAN NOT NULL DEFAULT true,
    phishing_filter_enabled BOOLEAN NOT NULL DEFAULT true,
    invite_filter_enabled BOOLEAN NOT NULL DEFAULT true,
    suggestion_channel_id TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_discord_guild_configs_honeypot ON public.discord_guild_configs (honeypot_channel_id);
CREATE INDEX IF NOT EXISTS idx_discord_guild_configs_ticket_cat ON public.discord_guild_configs (ticket_category_id);

-- 2. Discord Account Links Table (maps Discord Snowflake to platform user profile)
CREATE TABLE IF NOT EXISTS public.discord_account_links (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    discord_user_id TEXT UNIQUE NOT NULL,
    platform_user_id UUID REFERENCES public.profiles (id) ON DELETE CASCADE,
    link_code TEXT,
    code_expires_at TIMESTAMPTZ,
    verified_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_discord_account_links_discord_user ON public.discord_account_links (discord_user_id);
CREATE INDEX IF NOT EXISTS idx_discord_account_links_platform_user ON public.discord_account_links (platform_user_id);
CREATE INDEX IF NOT EXISTS idx_discord_account_links_code ON public.discord_account_links (link_code);

-- 3. Discord Support Tickets Table
CREATE TABLE IF NOT EXISTS public.discord_tickets (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    ticket_number SERIAL,
    guild_id TEXT NOT NULL REFERENCES public.discord_guild_configs (guild_id) ON DELETE CASCADE,
    channel_id TEXT NOT NULL,
    user_id TEXT NOT NULL,
    topic TEXT NOT NULL DEFAULT 'other', -- 'billing', 'vps', 'game_hosting', 'web_hosting', 'mail', 'other'
    status TEXT NOT NULL DEFAULT 'open', -- 'open', 'claimed', 'closed'
    priority TEXT NOT NULL DEFAULT 'standard', -- 'low', 'standard', 'high', 'urgent'
    claimed_by_id TEXT,
    claimed_at TIMESTAMPTZ,
    first_response_at TIMESTAMPTZ,
    closed_at TIMESTAMPTZ,
    closed_by_id TEXT,
    close_reason TEXT,
    transcript_url TEXT,
    transcript_text TEXT,
    rating INTEGER CHECK (rating >= 1 AND rating <= 5),
    rating_feedback TEXT,
    last_activity_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_discord_tickets_guild_status ON public.discord_tickets (guild_id, status);
CREATE INDEX IF NOT EXISTS idx_discord_tickets_channel ON public.discord_tickets (channel_id);
CREATE INDEX IF NOT EXISTS idx_discord_tickets_user ON public.discord_tickets (user_id);
CREATE INDEX IF NOT EXISTS idx_discord_tickets_number ON public.discord_tickets (ticket_number);

-- 4. Discord Ticket Messages (history storage for audit trails & transcripts)
CREATE TABLE IF NOT EXISTS public.discord_ticket_messages (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    ticket_id UUID NOT NULL REFERENCES public.discord_tickets (id) ON DELETE CASCADE,
    author_id TEXT NOT NULL,
    author_name TEXT NOT NULL,
    content TEXT NOT NULL DEFAULT '',
    attachments JSONB DEFAULT '[]'::jsonb,
    is_staff BOOLEAN NOT NULL DEFAULT false,
    sent_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_discord_ticket_messages_ticket ON public.discord_ticket_messages (ticket_id, sent_at ASC);

-- 5. Discord Moderation Audit Logs
CREATE TABLE IF NOT EXISTS public.discord_mod_logs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    guild_id TEXT NOT NULL REFERENCES public.discord_guild_configs (guild_id) ON DELETE CASCADE,
    target_user_id TEXT NOT NULL,
    moderator_id TEXT NOT NULL,
    action TEXT NOT NULL, -- 'warn', 'timeout', 'kick', 'ban', 'unban', 'honeypot_trigger', 'raid_lockdown', 'phishing_block', 'purge'
    reason TEXT,
    metadata JSONB DEFAULT '{}'::jsonb,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_discord_mod_logs_guild ON public.discord_mod_logs (guild_id, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_discord_mod_logs_target ON public.discord_mod_logs (target_user_id);

-- 6. Discord User Notes (internal staff notes)
CREATE TABLE IF NOT EXISTS public.discord_user_notes (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    guild_id TEXT NOT NULL REFERENCES public.discord_guild_configs (guild_id) ON DELETE CASCADE,
    target_user_id TEXT NOT NULL,
    moderator_id TEXT NOT NULL,
    note TEXT NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_discord_user_notes_target ON public.discord_user_notes (guild_id, target_user_id);

-- 7. Discord FAQs (store and knowledgebase)
CREATE TABLE IF NOT EXISTS public.discord_faqs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    guild_id TEXT NOT NULL REFERENCES public.discord_guild_configs (guild_id) ON DELETE CASCADE,
    keyword TEXT NOT NULL,
    question TEXT NOT NULL,
    answer TEXT NOT NULL,
    created_by TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (guild_id, keyword)
);

CREATE INDEX IF NOT EXISTS idx_discord_faqs_guild_keyword ON public.discord_faqs (guild_id, keyword);

-- 8. Discord Suggestions
CREATE TABLE IF NOT EXISTS public.discord_suggestions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    guild_id TEXT NOT NULL REFERENCES public.discord_guild_configs (guild_id) ON DELETE CASCADE,
    message_id TEXT NOT NULL,
    author_id TEXT NOT NULL,
    author_name TEXT NOT NULL,
    suggestion TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'pending', -- 'pending', 'approved', 'rejected', 'implemented'
    staff_response TEXT,
    upvotes INTEGER NOT NULL DEFAULT 0,
    downvotes INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_discord_suggestions_guild_status ON public.discord_suggestions (guild_id, status);
CREATE INDEX IF NOT EXISTS idx_discord_suggestions_message ON public.discord_suggestions (message_id);

-- 9. Row Level Security Policies
ALTER TABLE public.discord_guild_configs ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.discord_account_links ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.discord_tickets ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.discord_ticket_messages ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.discord_mod_logs ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.discord_user_notes ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.discord_faqs ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.discord_suggestions ENABLE ROW LEVEL SECURITY;

-- Allow service_role key full unrestricted access for bot operations
DROP POLICY IF EXISTS "service_role_full_guild_configs" ON public.discord_guild_configs;
CREATE POLICY "service_role_full_guild_configs" ON public.discord_guild_configs FOR ALL TO service_role USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS "service_role_full_account_links" ON public.discord_account_links;
CREATE POLICY "service_role_full_account_links" ON public.discord_account_links FOR ALL TO service_role USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS "service_role_full_tickets" ON public.discord_tickets;
CREATE POLICY "service_role_full_tickets" ON public.discord_tickets FOR ALL TO service_role USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS "service_role_full_ticket_messages" ON public.discord_ticket_messages;
CREATE POLICY "service_role_full_ticket_messages" ON public.discord_ticket_messages FOR ALL TO service_role USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS "service_role_full_mod_logs" ON public.discord_mod_logs;
CREATE POLICY "service_role_full_mod_logs" ON public.discord_mod_logs FOR ALL TO service_role USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS "service_role_full_user_notes" ON public.discord_user_notes;
CREATE POLICY "service_role_full_user_notes" ON public.discord_user_notes FOR ALL TO service_role USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS "service_role_full_faqs" ON public.discord_faqs;
CREATE POLICY "service_role_full_faqs" ON public.discord_faqs FOR ALL TO service_role USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS "service_role_full_suggestions" ON public.discord_suggestions;
CREATE POLICY "service_role_full_suggestions" ON public.discord_suggestions FOR ALL TO service_role USING (true) WITH CHECK (true);

-- Authenticated customers can read their own account link
DROP POLICY IF EXISTS "customers_read_own_account_link" ON public.discord_account_links;
CREATE POLICY "customers_read_own_account_link" ON public.discord_account_links FOR SELECT TO authenticated USING (platform_user_id = auth.uid());
