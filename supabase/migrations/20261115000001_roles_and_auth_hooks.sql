-- 20261115000001_roles_and_auth_hooks.sql
-- Role definitions, profiles, teams, team_members, custom JWT claims hook, and automatic profile sync.

-- 1. Create Role Enum
CREATE TYPE public.app_role AS ENUM ('customer', 'admin', 'owner');

-- 2. Teams Table
CREATE TABLE IF NOT EXISTS public.teams (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name TEXT NOT NULL,
    slug TEXT UNIQUE,
    is_personal BOOLEAN NOT NULL DEFAULT true,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- Index for team lookups
CREATE INDEX IF NOT EXISTS idx_teams_slug ON public.teams (slug);

-- 3. Profiles Table (linked to auth.users)
CREATE TABLE IF NOT EXISTS public.profiles (
    id UUID PRIMARY KEY REFERENCES auth.users (id) ON DELETE CASCADE,
    email TEXT UNIQUE NOT NULL,
    name TEXT,
    avatar_url TEXT,
    role public.app_role NOT NULL DEFAULT 'customer'::public.app_role,
    default_team_id UUID REFERENCES public.teams (id) ON DELETE SET NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_profiles_email ON public.profiles (email);
CREATE INDEX IF NOT EXISTS idx_profiles_role ON public.profiles (role);

-- 4. Team Members Table
CREATE TABLE IF NOT EXISTS public.team_members (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    team_id UUID NOT NULL REFERENCES public.teams (id) ON DELETE CASCADE,
    user_id UUID NOT NULL REFERENCES public.profiles (id) ON DELETE CASCADE,
    role TEXT NOT NULL DEFAULT 'member', -- owner, admin, member
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (team_id, user_id)
);

CREATE INDEX IF NOT EXISTS idx_team_members_team_id ON public.team_members (team_id);
CREATE INDEX IF NOT EXISTS idx_team_members_user_id ON public.team_members (user_id);

-- 5. Helper Functions for Role Checking in RLS
CREATE OR REPLACE FUNCTION auth.role() RETURNS text AS $$
    SELECT COALESCE(
        auth.jwt() -> 'app_metadata' ->> 'role',
        (SELECT role::text FROM public.profiles WHERE id = auth.uid()),
        'customer'
    );
$$ LANGUAGE sql STABLE SECURITY DEFINER;

CREATE OR REPLACE FUNCTION auth.is_admin() RETURNS boolean AS $$
    SELECT auth.role() IN ('admin', 'owner');
$$ LANGUAGE sql STABLE SECURITY DEFINER;

CREATE OR REPLACE FUNCTION auth.is_owner() RETURNS boolean AS $$
    SELECT auth.role() = 'owner';
$$ LANGUAGE sql STABLE SECURITY DEFINER;

-- Check if current authenticated user belongs to team
CREATE OR REPLACE FUNCTION auth.belongs_to_team(check_team_id UUID) RETURNS boolean AS $$
    SELECT EXISTS (
        SELECT 1 FROM public.team_members
        WHERE team_id = check_team_id
        AND user_id = auth.uid()
    );
$$ LANGUAGE sql STABLE SECURITY DEFINER;

-- 6. Custom Access Token Hook for Supabase Auth (injects role & team into JWT)
CREATE OR REPLACE FUNCTION public.custom_access_token_hook(event jsonb)
RETURNS jsonb LANGUAGE plpgsql STABLE AS $$
DECLARE
    claims jsonb;
    user_role public.app_role;
    user_team_id uuid;
BEGIN
    SELECT role, default_team_id INTO user_role, user_team_id
    FROM public.profiles
    WHERE id = (event->>'user_id')::uuid;

    claims := event->'claims';
    IF claims IS NULL THEN
        claims := '{}'::jsonb;
    END IF;

    claims := jsonb_set(
        claims,
        '{app_metadata, role}',
        to_jsonb(COALESCE(user_role::text, 'customer'))
    );

    IF user_team_id IS NOT NULL THEN
        claims := jsonb_set(
            claims,
            '{app_metadata, team_id}',
            to_jsonb(user_team_id::text)
        );
    END IF;

    event := jsonb_set(event, '{claims}', claims);
    RETURN event;
END;
$$;

-- Grant permissions for auth hook
GRANT EXECUTE ON FUNCTION public.custom_access_token_hook(jsonb) TO supabase_auth_admin;
REVOKE EXECUTE ON FUNCTION public.custom_access_token_hook(jsonb) FROM authenticated, anon, public;

-- 7. Automatic Profile & Team Provisioning on auth.users Sign Up
CREATE OR REPLACE FUNCTION public.handle_new_user()
RETURNS trigger LANGUAGE plpgsql SECURITY DEFINER AS $$
DECLARE
    new_team_id UUID;
    user_name TEXT;
    assigned_role public.app_role;
BEGIN
    user_name := COALESCE(
        new.raw_user_meta_data->>'name',
        split_part(new.email, '@', 1)
    );

    -- Check if first user on platform; if so, assign 'owner' role
    IF NOT EXISTS (SELECT 1 FROM public.profiles) THEN
        assigned_role := 'owner'::public.app_role;
    ELSE
        assigned_role := 'customer'::public.app_role;
    END IF;

    -- Create personal team
    INSERT INTO public.teams (name, slug, is_personal)
    VALUES (
        user_name || '''s Team',
        'team-' || substr(md5(random()::text || clock_timestamp()::text), 1, 10),
        true
    )
    RETURNING id INTO new_team_id;

    -- Create profile
    INSERT INTO public.profiles (id, email, name, avatar_url, role, default_team_id)
    VALUES (
        new.id,
        new.email,
        user_name,
        new.raw_user_meta_data->>'avatar_url',
        assigned_role,
        new_team_id
    );

    -- Link user as owner of their personal team
    INSERT INTO public.team_members (team_id, user_id, role)
    VALUES (new_team_id, new.id, 'owner');

    RETURN new;
END;
$$;

-- Trigger firing on every new GoTrue user creation
DROP TRIGGER IF EXISTS on_auth_user_created ON auth.users;
CREATE TRIGGER on_auth_user_created
    AFTER INSERT ON auth.users
    FOR EACH ROW EXECUTE FUNCTION public.handle_new_user();

