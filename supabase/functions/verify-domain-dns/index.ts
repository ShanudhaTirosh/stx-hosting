// supabase/functions/verify-domain-dns/index.ts
// Verifies custom domain DNS records (A/CNAME/TXT) and updates verification status in Supabase.

import { serve } from "https://deno.land/std@0.168.0/http/server.ts";
import { createClient } from "https://esm.sh/@supabase/supabase-js@2.39.0";

const corsHeaders = {
  "Access-Control-Allow-Origin": "*",
  "Access-Control-Allow-Headers": "authorization, x-client-info, apikey, content-type",
};

interface VerifyRequest {
  domain_id?: string;
  domain?: string;
}

serve(async (req: Request) => {
  if (req.method === "OPTIONS") {
    return new Response("ok", { headers: corsHeaders });
  }

  try {
    const adminClient = createClient(
      Deno.env.get("SUPABASE_URL") ?? "",
      Deno.env.get("SUPABASE_SERVICE_ROLE_KEY") ?? ""
    );

    let domainsToVerify: any[] = [];

    if (req.method === "POST") {
      const body: VerifyRequest = await req.json().catch(() => ({}));
      if (body.domain_id) {
        const { data } = await adminClient.from("custom_domains").select("*").eq("id", body.domain_id);
        domainsToVerify = data || [];
      } else if (body.domain) {
        const { data } = await adminClient.from("custom_domains").select("*").eq("domain", body.domain.toLowerCase().trim());
        domainsToVerify = data || [];
      }
    }

    // If no specific domain requested, query pending domains for periodic polling
    if (domainsToVerify.length === 0) {
      const { data } = await adminClient
        .from("custom_domains")
        .select("*")
        .eq("status", "pending_dns")
        .lt("check_attempts", 20)
        .limit(25);
      domainsToVerify = data || [];
    }

    const results = [];

    for (const record of domainsToVerify) {
      let isVerified = false;
      let errorMsg: string | null = null;
      const targetHost = record.domain;

      try {
        // 1. Try TXT record verification: _stx-verify.<domain>
        try {
          const txtRecords = await Deno.resolveDns(`_stx-verify.${targetHost}`, "TXT");
          const flatTxt = txtRecords.flat();
          if (flatTxt.some((t) => t.includes(record.token))) {
            isVerified = true;
          }
        } catch {
          // TXT lookup failed or not found, proceed to CNAME/A check
        }

        // 2. Try CNAME / A record matching expected target
        if (!isVerified) {
          if (record.record_type === "CNAME") {
            try {
              const cnames = await Deno.resolveDns(targetHost, "CNAME");
              if (cnames.some((c) => c.toLowerCase().includes(record.expected_target.toLowerCase()))) {
                isVerified = true;
              }
            } catch (err: unknown) {
              errorMsg = err instanceof Error ? err.message : "CNAME lookup failed";
            }
          } else {
            try {
              const aRecords = await Deno.resolveDns(targetHost, "A");
              if (aRecords.includes(record.expected_target)) {
                isVerified = true;
              }
            } catch (err: unknown) {
              errorMsg = err instanceof Error ? err.message : "A record lookup failed";
            }
          }
        }

        // 3. Update database record
        const updatePayload: Record<string, any> = {
          check_attempts: (record.check_attempts || 0) + 1,
          last_checked_at: new Date().toISOString(),
          last_error_message: isVerified ? null : errorMsg,
        };

        if (isVerified) {
          updatePayload.status = "verified";
          updatePayload.verified_at = new Date().toISOString();
        }

        await adminClient.from("custom_domains").update(updatePayload).eq("id", record.id);

        // 4. Trigger Realtime notification if status changed to verified
        if (isVerified) {
          const channel = adminClient.channel(`realtime:domains:team_id=eq.${record.team_id}`);
          await channel.send({
            type: "broadcast",
            event: "domain_verified",
            payload: {
              domain_id: record.id,
              domain: record.domain,
              status: "verified",
              verified_at: updatePayload.verified_at,
            },
          });
        }

        results.push({
          domain: record.domain,
          verified: isVerified,
          error: errorMsg,
        });
      } catch (checkErr: unknown) {
        results.push({
          domain: record.domain,
          verified: false,
          error: checkErr instanceof Error ? checkErr.message : "Check error",
        });
      }
    }

    return new Response(JSON.stringify({ success: true, processed: results.length, results }), {
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

