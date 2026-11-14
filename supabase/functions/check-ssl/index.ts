// supabase/functions/check-ssl/index.ts
// Validates SSL/TLS certificate handshakes for verified custom domains and updates SSL status in Supabase.

import { serve } from "https://deno.land/std@0.168.0/http/server.ts";
import { createClient } from "https://esm.sh/@supabase/supabase-js@2.39.0";

const corsHeaders = {
  "Access-Control-Allow-Origin": "*",
  "Access-Control-Allow-Headers": "authorization, x-client-info, apikey, content-type",
};

serve(async (req: Request) => {
  if (req.method === "OPTIONS") {
    return new Response("ok", { headers: corsHeaders });
  }

  try {
    const adminClient = createClient(
      Deno.env.get("SUPABASE_URL") ?? "",
      Deno.env.get("SUPABASE_SERVICE_ROLE_KEY") ?? ""
    );

    let domainId: string | null = null;
    if (req.method === "POST") {
      const body = await req.json().catch(() => ({}));
      domainId = body.domain_id || null;
    }

    let query = adminClient
      .from("custom_domains")
      .select("*")
      .eq("status", "verified");

    if (domainId) {
      query = query.eq("id", domainId);
    } else {
      query = query.in("ssl_status", ["pending", "expired"]).limit(10);
    }

    const { data: domains } = await query;
    const results = [];

    for (const record of domains || []) {
      const hostname = record.domain;
      let sslActive = false;
      let errorMsg: string | null = null;

      try {
        const conn = await Deno.connectTls({
          hostname,
          port: 443,
        });

        // Handshake succeeded
        sslActive = true;
        conn.close();
      } catch (err: unknown) {
        sslActive = false;
        errorMsg = err instanceof Error ? err.message : "TLS handshake failed";
      }

      const updateData: Record<string, any> = {
        ssl_status: sslActive ? "active" : "failed",
        last_checked_at: new Date().toISOString(),
      };

      if (sslActive) {
        updateData.ssl_issued_at = new Date().toISOString();
        // Typically Let's Encrypt certificates are 90 days
        const expires = new Date();
        expires.setDate(expires.getDate() + 90);
        updateData.ssl_expires_at = expires.toISOString();
      } else {
        updateData.last_error_message = errorMsg;
      }

      await adminClient.from("custom_domains").update(updateData).eq("id", record.id);

      results.push({
        domain: hostname,
        ssl_status: updateData.ssl_status,
        error: errorMsg,
      });
    }

    return new Response(JSON.stringify({ success: true, count: results.length, results }), {
      status: 200,
      headers: { ...corsHeaders, "Content-Type": "application/json" },
    });
  } catch (err: unknown) {
    const message = err instanceof Error ? err.message : "Internal Server Error";
    return new Response(JSON.stringify({ error: message }), {
      status: 500,
      headers: { ...corsHeaders, "Content-Type": "application/json" },
    });
  }
});

