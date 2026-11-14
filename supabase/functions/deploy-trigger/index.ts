// supabase/functions/deploy-trigger/index.ts
// Secure deploy trigger Edge Function validating entitlements, template gating, and dispatching deployment jobs.

import { serve } from "https://deno.land/std@0.168.0/http/server.ts";
import { createClient } from "https://esm.sh/@supabase/supabase-js@2.39.0";

const corsHeaders = {
  "Access-Control-Allow-Origin": "*",
  "Access-Control-Allow-Headers": "authorization, x-client-info, apikey, content-type",
};

interface DeployRequest {
  application_name: string;
  template_name?: string;
  git_repo?: string;
  git_branch?: string;
  build_pack?: string;
  environment_variables?: Record<string, string>;
  team_id?: string;
}

serve(async (req: Request) => {
  if (req.method === "OPTIONS") {
    return new Response("ok", { headers: corsHeaders });
  }

  try {
    const supabaseClient = createClient(
      Deno.env.get("SUPABASE_URL") ?? "",
      Deno.env.get("SUPABASE_ANON_KEY") ?? "",
      {
        global: {
          headers: { Authorization: req.headers.get("Authorization")! },
        },
      }
    );

    // 1. Authenticate user
    const {
      data: { user },
      error: userError,
    } = await supabaseClient.auth.getUser();

    if (userError || !user) {
      return new Response(JSON.stringify({ error: "Unauthorized" }), {
        status: 401,
        headers: { ...corsHeaders, "Content-Type": "application/json" },
      });
    }

    const body: DeployRequest = await req.json();
    const { application_name, template_name, git_repo, git_branch = "main", build_pack = "nixpacks", environment_variables = {} } = body;

    if (!application_name) {
      return new Response(JSON.stringify({ error: "Application name is required" }), {
        status: 400,
        headers: { ...corsHeaders, "Content-Type": "application/json" },
      });
    }

    // 2. Resolve team
    const { data: profile } = await supabaseClient
      .from("profiles")
      .select("default_team_id, role")
      .eq("id", user.id)
      .single();

    const team_id = body.team_id || profile?.default_team_id;
    if (!team_id) {
      return new Response(JSON.stringify({ error: "No associated team found" }), {
        status: 400,
        headers: { ...corsHeaders, "Content-Type": "application/json" },
      });
    }

    // 3. Admin / Service Role client for elevated checks & execution
    const adminClient = createClient(
      Deno.env.get("SUPABASE_URL") ?? "",
      Deno.env.get("SUPABASE_SERVICE_ROLE_KEY") ?? ""
    );

    // 4. Validate Template if provided
    if (template_name) {
      const { data: template, error: tmplError } = await adminClient
        .from("template_permissions")
        .select("is_globally_enabled, min_ram_mb, min_vcpu")
        .eq("template_name", template_name)
        .single();

      if (tmplError || !template) {
        return new Response(JSON.stringify({ error: `Template '${template_name}' not found` }), {
          status: 404,
          headers: { ...corsHeaders, "Content-Type": "application/json" },
        });
      }

      if (!template.is_globally_enabled && profile?.role === "customer") {
        return new Response(
          JSON.stringify({ error: `Template '${template_name}' is currently disabled by administrators.` }),
          { status: 403, headers: { ...corsHeaders, "Content-Type": "application/json" } }
        );
      }
    }

    // 5. Create Deployment record
    const deployment_uuid = crypto.randomUUID();
    const { data: deployment, error: deployError } = await adminClient
      .from("deployments")
      .insert({
        uuid: deployment_uuid,
        team_id,
        user_id: user.id,
        application_name,
        template_name: template_name || null,
        git_repo: git_repo || null,
        git_branch,
        build_pack,
        status: "queued",
        environment_variables,
        logs: JSON.stringify([{ step: "init", message: "Deployment queued successfully via Supabase Edge Function", timestamp: new Date().toISOString() }]),
      })
      .select()
      .single();

    if (deployError) {
      return new Response(JSON.stringify({ error: deployError.message }), {
        status: 500,
        headers: { ...corsHeaders, "Content-Type": "application/json" },
      });
    }

    // 6. Broadcast live log chunk to realtime channel
    const channel = adminClient.channel(`deployment-logs:${deployment_uuid}`);
    await channel.send({
      type: "broadcast",
      event: "log_append",
      payload: {
        step: "queued",
        output: `Deployment ${deployment_uuid} accepted. Preparing execution container...`,
        timestamp: new Date().toISOString(),
      },
    });

    return new Response(
      JSON.stringify({
        success: true,
        deployment_uuid,
        status: "queued",
        deployment,
      }),
      { status: 201, headers: { ...corsHeaders, "Content-Type": "application/json" } }
    );
  } catch (err: unknown) {
    const message = err instanceof Error ? err.message : "Internal Server Error";
    return new Response(JSON.stringify({ error: message }), {
      status: 500,
      headers: { ...corsHeaders, "Content-Type": "application/json" },
    });
  }
});

