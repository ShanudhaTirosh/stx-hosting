// supabase/functions/stripe-webhook/index.ts
// Handles Stripe payment webhooks with cryptographic HMAC signature verification and activates customer entitlements.

import { serve } from "https://deno.land/std@0.168.0/http/server.ts";
import { createClient } from "https://esm.sh/@supabase/supabase-js@2.39.0";

const STRIPE_WEBHOOK_SECRET = Deno.env.get("STRIPE_WEBHOOK_SECRET") ?? "";

// Verify Stripe webhook HMAC-SHA256 signature
async function verifyStripeSignature(payload: string, header: string, secret: string): Promise<boolean> {
  if (!header || !secret) return false;

  const parts = header.split(",").reduce((acc: Record<string, string>, item) => {
    const [k, v] = item.split("=");
    if (k && v) acc[k.trim()] = v.trim();
    return acc;
  }, {});

  const timestamp = parts["t"];
  const signature = parts["v1"];

  if (!timestamp || !signature) return false;

  // Protect against replay attacks (5 minute threshold)
  const currentTs = Math.floor(Date.now() / 1000);
  if (Math.abs(currentTs - parseInt(timestamp, 10)) > 300) {
    return false;
  }

  const signedPayload = `${timestamp}.${payload}`;
  const encoder = new TextEncoder();
  const key = await crypto.subtle.importKey(
    "raw",
    encoder.encode(secret),
    { name: "HMAC", hash: "SHA-256" },
    false,
    ["sign"]
  );

  const signedBuffer = await crypto.subtle.sign("HMAC", key, encoder.encode(signedPayload));
  const hexHash = Array.from(new Uint8Array(signedBuffer))
    .map((b) => b.toString(16).padStart(2, "0"))
    .join("");

  return hexHash === signature;
}

serve(async (req: Request) => {
  if (req.method !== "POST") {
    return new Response("Method not allowed", { status: 405 });
  }

  try {
    const signatureHeader = req.headers.get("stripe-signature") ?? "";
    const rawBody = await req.text();

    // Verify webhook signature if secret configured
    if (STRIPE_WEBHOOK_SECRET) {
      const isValid = await verifyStripeSignature(rawBody, signatureHeader, STRIPE_WEBHOOK_SECRET);
      if (!isValid) {
        return new Response(JSON.stringify({ error: "Invalid signature" }), { status: 400 });
      }
    }

    const event = JSON.parse(rawBody);
    const eventType = event.type;
    const dataObject = event.data?.object;

    const adminClient = createClient(
      Deno.env.get("SUPABASE_URL") ?? "",
      Deno.env.get("SUPABASE_SERVICE_ROLE_KEY") ?? ""
    );

    if (eventType === "checkout.session.completed" || eventType === "invoice.payment_succeeded") {
      const invoiceNumber = dataObject.metadata?.invoice_number || dataObject.metadata?.invoice_id;
      const orderUuid = dataObject.metadata?.order_uuid || dataObject.client_reference_id;
      const txnId = dataObject.payment_intent || dataObject.id;
      const amountCents = dataObject.amount_total || dataObject.amount_paid || 0;
      const currency = (dataObject.currency || "USD").toUpperCase();

      let invoice = null;

      if (invoiceNumber) {
        const { data } = await adminClient
          .from("invoices")
          .select("*, order:orders(*)")
          .or(`invoice_number.eq.${invoiceNumber},uuid.eq.${invoiceNumber}`)
          .single();
        invoice = data;
      } else if (orderUuid) {
        const { data } = await adminClient
          .from("invoices")
          .select("*, order:orders(*)")
          .eq("order_id", orderUuid)
          .single();
        invoice = data;
      }

      if (invoice) {
        // 1. Record payment transaction
        await adminClient.from("payment_transactions").insert({
          invoice_id: invoice.id,
          gateway: "stripe",
          transaction_id: txnId,
          amount_cents: amountCents,
          currency,
          status: "successful",
          gateway_payload: event,
        });

        // 2. Mark invoice as paid (triggers automatic entitlement grant via database trigger)
        await adminClient
          .from("invoices")
          .update({
            status: "paid",
            paid_at: new Date().toISOString(),
          })
          .eq("id", invoice.id);

        // 3. Send admin broadcast notification
        const adminChannel = adminClient.channel("realtime:admin:feed");
        await adminChannel.send({
          type: "broadcast",
          event: "order_paid",
          payload: {
            invoice_number: invoice.invoice_number,
            amount_cents: amountCents,
            currency,
            gateway: "stripe",
          },
        });
      }
    }

    return new Response(JSON.stringify({ received: true }), {
      status: 200,
      headers: { "Content-Type": "application/json" },
    });
  } catch (err: unknown) {
    const message = err instanceof Error ? err.message : "Webhook handler exception";
    return new Response(JSON.stringify({ error: message }), { status: 500 });
  }
});

