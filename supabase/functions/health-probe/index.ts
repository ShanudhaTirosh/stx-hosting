// supabase/functions/health-probe/index.ts
// Automated health probe measuring HTTP/TCP latency, updating monitor metrics, and broadcasting public status changes.

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

    // Query active monitors
    const { data: monitors, error: fetchErr } = await adminClient
      .from("monitors")
      .select("*")
      .eq("is_active", true);

    if (fetchErr) {
      return new Response(JSON.stringify({ error: fetchErr.message }), { status: 500 });
    }

    const probeResults = [];

    for (const monitor of monitors || []) {
      const startTime = performance.now();
      let status: "up" | "down" | "degraded" = "down";
      let latencyMs = 0;
      let errorMsg: string | null = null;

      try {
        if (monitor.type === "http" || monitor.target_url) {
          const targetUrl = monitor.target_url || `http://${monitor.target_host}:${monitor.port}`;
          const controller = new AbortController();
          const timeoutId = setTimeout(() => controller.abort(), monitor.timeout_ms || 4000);

          const response = await fetch(targetUrl, {
            method: "GET",
            headers: monitor.custom_headers || {},
            signal: controller.signal,
          });

          clearTimeout(timeoutId);
          latencyMs = Math.round(performance.now() - startTime);

          const expectedCode = monitor.expected_status_code || 200;
          if (response.status === expectedCode || (expectedCode === 200 && response.ok)) {
            status = latencyMs > 800 ? "degraded" : "up";
          } else {
            status = "down";
            errorMsg = `HTTP status ${response.status} (expected ${expectedCode})`;
          }
        } else if (monitor.type === "tcp") {
          // TCP Socket Connect
          const conn = await Deno.connect({
            hostname: monitor.target_host,
            port: monitor.port || 22,
          });
          latencyMs = Math.round(performance.now() - startTime);
          conn.close();
          status = latencyMs > 600 ? "degraded" : "up";
        }
      } catch (err: unknown) {
        latencyMs = Math.round(performance.now() - startTime);
        status = "down";
        errorMsg = err instanceof Error ? err.message : "Connection failed";
      }

      // 1. Record monitor check in monitor_checks table
      await adminClient.from("monitor_checks").insert({
        monitor_id: monitor.id,
        status,
        response_time_ms: latencyMs,
        error_message: errorMsg,
        checked_at: new Date().toISOString(),
      });

      // 2. Map status to current_status string
      const current_status = status === "up" ? "operational" : (status === "degraded" ? "degraded" : "down");
      const statusChanged = monitor.current_status !== current_status;

      // 3. Update monitor record
      await adminClient
        .from("monitors")
        .update({
          current_status,
          last_latency_ms: latencyMs,
          last_checked_at: new Date().toISOString(),
        })
        .eq("id", monitor.id);

      // 4. If status changed, broadcast immediately to public status channel
      if (statusChanged) {
        const statusChannel = adminClient.channel("realtime:public:status");
        await statusChannel.send({
          type: "broadcast",
          event: "monitor_status_changed",
          payload: {
            monitor_id: monitor.id,
            name: monitor.public_label || monitor.name,
            old_status: monitor.current_status,
            new_status: current_status,
            latency_ms: latencyMs,
            checked_at: new Date().toISOString(),
          },
        });
      }

      probeResults.push({
        id: monitor.id,
        name: monitor.name,
        status: current_status,
        latency_ms: latencyMs,
        error: errorMsg,
      });
    }

    return new Response(JSON.stringify({ success: true, count: probeResults.length, results: probeResults }), {
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

