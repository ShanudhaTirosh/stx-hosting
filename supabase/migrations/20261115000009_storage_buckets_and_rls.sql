-- 20261115000009_storage_buckets_and_rls.sql
-- Create Supabase Storage buckets and configure storage RLS policies for logos, template assets, invoices, and user uploads.

-- 1. Insert Storage Buckets into storage.buckets
INSERT INTO storage.buckets (id, name, public, file_size_limit, allowed_mime_types)
VALUES
    ('logos', 'logos', true, 5242880, ARRAY['image/svg+xml', 'image/png', 'image/webp']::text[]),
    ('template-assets', 'template-assets', true, 10485760, ARRAY['image/png', 'image/jpeg', 'image/svg+xml', 'application/json', 'text/yaml', 'text/plain']::text[]),
    ('invoices', 'invoices', false, 10485760, ARRAY['application/pdf']::text[]),
    ('user-uploads', 'user-uploads', false, 15728640, ARRAY['image/jpeg', 'image/png', 'image/webp', 'application/pdf']::text[])
ON CONFLICT (id) DO UPDATE SET
    public = EXCLUDED.public,
    file_size_limit = EXCLUDED.file_size_limit,
    allowed_mime_types = EXCLUDED.allowed_mime_types;

-- 2. Storage Objects RLS Policies

-- Logos Bucket Policies (Public read, Admin/Owner write)
CREATE POLICY "Public can view logos" ON storage.objects
    FOR SELECT TO anon, authenticated
    USING (bucket_id = 'logos');

CREATE POLICY "Admins can upload logos" ON storage.objects
    FOR INSERT TO authenticated
    WITH CHECK (bucket_id = 'logos' AND auth.is_admin());

CREATE POLICY "Admins can update logos" ON storage.objects
    FOR UPDATE TO authenticated
    USING (bucket_id = 'logos' AND auth.is_admin());

CREATE POLICY "Admins can delete logos" ON storage.objects
    FOR DELETE TO authenticated
    USING (bucket_id = 'logos' AND auth.is_admin());

-- Template Assets Bucket Policies (Public read, Admin/Owner write)
CREATE POLICY "Public can view template assets" ON storage.objects
    FOR SELECT TO anon, authenticated
    USING (bucket_id = 'template-assets');

CREATE POLICY "Admins can manage template assets" ON storage.objects
    FOR ALL TO authenticated
    USING (bucket_id = 'template-assets' AND auth.is_admin())
    WITH CHECK (bucket_id = 'template-assets' AND auth.is_admin());

-- Invoices Bucket Policies (Private, Team member and staff read only)
CREATE POLICY "Team members and staff can view invoices" ON storage.objects
    FOR SELECT TO authenticated
    USING (
        bucket_id = 'invoices' AND (
            auth.is_admin() OR
            -- Expected object path: {team_id}/{invoice_uuid}.pdf
            auth.belongs_to_team((storage.foldername(name))[1]::uuid)
        )
    );

CREATE POLICY "Staff and system can insert invoices" ON storage.objects
    FOR INSERT TO authenticated
    WITH CHECK (bucket_id = 'invoices' AND auth.is_admin());

-- User Uploads Bucket Policies (Private, Receipt proofs, avatars, chat attachments)
CREATE POLICY "Users can upload their team files" ON storage.objects
    FOR INSERT TO authenticated
    WITH CHECK (
        bucket_id = 'user-uploads' AND (
            auth.is_admin() OR
            -- Expected object path: {team_id}/{filename} or {user_id}/{filename}
            auth.belongs_to_team((storage.foldername(name))[1]::uuid) OR
            (storage.foldername(name))[1] = auth.uid()::text
        )
    );

CREATE POLICY "Users and staff can view their uploaded files" ON storage.objects
    FOR SELECT TO authenticated
    USING (
        bucket_id = 'user-uploads' AND (
            auth.is_admin() OR
            auth.belongs_to_team((storage.foldername(name))[1]::uuid) OR
            (storage.foldername(name))[1] = auth.uid()::text
        )
    );

CREATE POLICY "Users can delete their own uploaded files" ON storage.objects
    FOR DELETE TO authenticated
    USING (
        bucket_id = 'user-uploads' AND (
            auth.is_admin() OR
            (storage.foldername(name))[1] = auth.uid()::text
        )
    );

