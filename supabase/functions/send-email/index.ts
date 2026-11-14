// supabase/functions/send-email/index.ts
// Secure transactional email dispatcher via Resend / Postmark without exposing API keys to clients.

import { serve } from "https://deno.land/std@0.168.0/http/server.ts";

const corsHeaders = {
  "Access-Control-Allow-Origin": "*",
  "Access-Control-Allow-Headers": "authorization, x-client-info, apikey, content-type",
};

interface EmailPayload {
  to: string | string[];
  subject: string;
  html?: string;
  text?: string;
  from?: string;
}

serve(async (req: Request) => {
  if (req.method === "OPTIONS") {
    return new Response("ok", { headers: corsHeaders });
  }

  try {
    const RESEND_API_KEY = Deno.env.get("RESEND_API_KEY") ?? "";
    const DEFAULT_FROM = Deno.env.get("EMAIL_FROM") ?? "STX Hosting <notifications@stxapp.io>";

    const body: EmailPayload = await req.json();
    const { to, subject, html, text, from = DEFAULT_FROM } = body;

    if (!to || !subject || (!html && !text)) {
      return new Response(JSON.stringify({ error: "Missing required email parameters (to, subject, html/text)" }), {
        status: 400,
        headers: { ...corsHeaders, "Content-Type": "application/json" },
      });
    }

    if (!RESEND_API_KEY) {
      // In local dev without API key, log to console
      console.log(`[MOCK EMAIL SENT] To: ${to} | Subject: ${subject}`);
      return new Response(JSON.stringify({ success: true, mocked: true }), {
        status: 200,
        headers: { ...corsHeaders, "Content-Type": "application/json" },
      });
    }

    const resendResponse = await fetch("https://api.resend.com/emails", {
      method: "POST",
      headers: {
        Authorization: `Bearer ${RESEND_API_KEY}`,
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        from,
        to: Array.isArray(to) ? to : [to],
        subject,
        html: html || text,
        text,
      }),
    });

    const result = await resendResponse.json();

    if (!resendResponse.ok) {
      return new Response(JSON.stringify({ error: result }), {
        status: resendResponse.status,
        headers: { ...corsHeaders, "Content-Type": "application/json" },
      });
    }

    return new Response(JSON.stringify({ success: true, data: result }), {
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

