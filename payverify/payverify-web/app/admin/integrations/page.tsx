"use client";
import { useCallback, useEffect, useState } from "react";
import { api } from "@/lib/api";

type TabLanguage = "curl" | "javascript" | "php" | "python";

export default function IntegrationsPage() {
  const [creds, setCreds] = useState<any>(null);
  const [err, setErr] = useState("");
  const [showKey, setShowKey] = useState(false);
  const [showSecret, setShowSecret] = useState(false);
  const [copiedField, setCopiedField] = useState<string | null>(null);
  const [selectedLang, setSelectedLang] = useState<TabLanguage>("curl");
  const [healthStatus, setHealthStatus] = useState<any>(null);
  const [testingHealth, setTestingHealth] = useState(false);

  const load = useCallback(() => {
    return api("/api/v1/external/credentials")
      .then(setCreds)
      .catch((e) => setErr(e.message));
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  function copyText(text: string, fieldId: string) {
    if (!text) return;
    navigator.clipboard.writeText(text);
    setCopiedField(fieldId);
    setTimeout(() => setCopiedField(null), 2000);
  }

  async function testApiHealth() {
    setTestingHealth(true);
    try {
      const res = await api("/api/v1/external/health");
      setHealthStatus(res);
    } catch (e: any) {
      setHealthStatus({ error: e.message });
    } finally {
      setTestingHealth(false);
    }
  }

  const apiKeyVal = creds?.api_key || "";
  const webhookSecretVal = creds?.webhook_secret || "";
  const baseUrl = typeof window !== "undefined" ? window.location.origin : (creds?.site_url || "http://localhost:8000");

  const codeSnippets: Record<TabLanguage, { label: string; code: string }> = {
    curl: {
      label: "cURL",
      code: `# 1. Upload payment proof with details & get instant AI review + bank match
curl -X POST "${baseUrl}/api/v1/external/proofs/upload-and-review" \\
  -H "X-API-Key: ${apiKeyVal || "PV_YOUR_API_KEY"}" \\
  -H "Content-Type: application/json" \\
  -d '{
    "invoice_no": "INV-1094",
    "amount_minor": 350000,
    "customer": {
      "name": "Ruwan Perera",
      "email": "ruwan@customer.com",
      "phone": "+94771234567"
    },
    "payment": {
      "channel": "bank_transfer",
      "reference": "STAN-03269940",
      "notes": "Annual plan renewal"
    },
    "proof_base64": "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==",
    "proof_mime": "image/png",
    "auto_match": true
  }'

# 2. Multipart form upload (for standard HTML forms & plugins)
curl -X POST "${baseUrl}/api/v1/external/proofs/upload-and-review-form" \\
  -H "X-API-Key: ${apiKeyVal || "PV_YOUR_API_KEY"}" \\
  -F "invoice_no=INV-1094" \\
  -F "amount_minor=350000" \\
  -F "customer_name=Ruwan Perera" \\
  -F "customer_email=ruwan@customer.com" \\
  -F "reference=STAN-03269940" \\
  -F "file=@/path/to/bank_slip.jpg;type=image/jpeg"

# 3. Query claim review verdict and forensic results
curl -X GET "${baseUrl}/api/v1/external/claims/45" \\
  -H "X-API-Key: ${apiKeyVal || "PV_YOUR_API_KEY"}"`,
    },
    javascript: {
      label: "JavaScript / TypeScript",
      code: `// Submit order details and slip for real-time review & settlement
async function submitPaymentProof() {
  const response = await fetch("${baseUrl}/api/v1/external/proofs/upload-and-review", {
    method: "POST",
    headers: {
      "X-API-Key": "${apiKeyVal || "PV_YOUR_API_KEY"}",
      "Content-Type": "application/json",
    },
    body: JSON.stringify({
      invoice_no: "INV-1094",
      amount_minor: 350000, // 3,500.00 LKR in cents
      customer: {
        name: "Ruwan Perera",
        email: "ruwan@customer.com",
        phone: "+94771234567",
      },
      payment: {
        channel: "bank_transfer",
        reference: "STAN-03269940",
      },
      proof_base64: "data:image/jpeg;base64,...",
      proof_mime: "image/jpeg",
      auto_match: true,
    }),
  });

  const review = await response.json();
  console.log("Verdict:", review.verdict.decision); // 'verified' | 'awaiting_bank_credit' | 'discrepancy_detected'
  console.log("Status:", review.status);             // 'APPROVED' | 'PENDING_VERIFICATION' | 'MANUAL_REVIEW'
  console.log("AI Extracted:", review.extraction);
  console.log("Forensics:", review.forensics);

  if (review.is_approved) {
    // Payment confirmed against Commercial Bank credit
    return { success: true, message: "Payment verified!" };
  } else {
    // Show bank details or pending instructions
    return { success: false, instructions: review.instructions };
  }
}

// Outbound Webhook Verification (Node.js crypto)
import crypto from "crypto";
function verifyWebhook(payloadRaw, signatureHeader, secret = "${webhookSecretVal || "PV_WEBHOOK_SECRET"}") {
  const expected = "sha256=" + crypto.createHmac("sha256", secret).update(payloadRaw).digest("hex");
  return crypto.timingSafeEqual(Buffer.from(expected), Buffer.from(signatureHeader));
}`,
    },
    php: {
      label: "PHP (WooCommerce / Custom)",
      code: `<?php
// PayVerify Instant Proof Review & Settlement in PHP
$apiUrl = "${baseUrl}/api/v1/external/proofs/upload-and-review";
$apiKey = "${apiKeyVal || "PV_YOUR_API_KEY"}";

$data = [
    "invoice_no"   => "INV-1094",
    "amount_minor" => 350000,
    "customer"     => [
        "name"  => "Ruwan Perera",
        "email" => "ruwan@customer.com",
        "phone" => "+94771234567",
    ],
    "payment"      => [
        "channel"   => "bank_transfer",
        "reference" => "STAN-03269940",
    ],
    "proof_base64" => base64_encode(file_get_contents("/path/to/bank_slip.jpg")),
    "proof_mime"   => "image/jpeg",
    "auto_match"   => true,
];

$ch = curl_init($apiUrl);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        "X-API-Key: " . $apiKey,
        "Content-Type: application/json",
    ],
    CURLOPT_POSTFIELDS     => json_encode($data),
]);

$response = curl_exec($ch);
curl_close($ch);

$review = json_decode($response, true);
if ($review["is_approved"] === true) {
    // Order settled against ComBank ledger credit
    error_log("Order " . $review["invoice_no"] . " approved: " . $review["verdict"]["summary"]);
} else {
    // Order awaiting statement or manual review
    error_log("Status: " . $review["status"] . " - " . $review["verdict"]["summary"]);
}
?>`,
    },
    python: {
      label: "Python (requests / FastAPI)",
      code: `import base64
import requests

API_URL = "${baseUrl}/api/v1/external/proofs/upload-and-review"
API_KEY = "${apiKeyVal || "PV_YOUR_API_KEY"}"

with open("slip.jpg", "rb") as f:
    proof_b64 = base64.b64encode(f.read()).decode("ascii")

payload = {
    "invoice_no": "INV-1094",
    "amount_minor": 350000,
    "customer": {
        "name": "Ruwan Perera",
        "email": "ruwan@customer.com",
        "phone": "+94771234567",
    },
    "payment": {
        "channel": "bank_transfer",
        "reference": "STAN-03269940",
    },
    "proof_base64": proof_b64,
    "proof_mime": "image/jpeg",
    "auto_match": True,
}

headers = {
    "X-API-Key": API_KEY,
    "Content-Type": "application/json",
}

resp = requests.post(API_URL, json=payload, headers=headers)
review = resp.json()

print("Approved:", review.get("is_approved"))
print("Status:", review.get("status"))
print("Verdict:", review.get("verdict", {}).get("decision"))
print("AI Extracted:", review.get("extraction"))
print("Forensics:", review.get("forensics"))`,
    },
  };

  return (
    <div className="stack" style={{ gap: "2rem" }}>
      {/* Header */}
      <div style={{ display: "flex", flexWrap: "wrap", alignItems: "center", justifyContent: "space-between", gap: "1rem" }}>
        <div>
          <h1 style={{ margin: 0, fontSize: "1.8rem" }}>API & Webhooks Integration</h1>
          <p className="muted" style={{ margin: "0.25rem 0 0 0" }}>
            Connect third-party websites, Shopify, WooCommerce, or mobile apps to verify bank payments automatically.
          </p>
        </div>
        <button
          type="button"
          onClick={testApiHealth}
          disabled={testingHealth}
          style={{ backgroundColor: "#ffffff", color: "#000000", fontWeight: 600, padding: "0.55rem 1.25rem" }}
        >
          {testingHealth ? "Testing…" : "Test API Connection"}
        </button>
      </div>

      {err && <p className="error" role="alert">{err}</p>}
      {healthStatus && (
        <div
          style={{
            padding: "0.75rem 1rem",
            borderRadius: "8px",
            background: healthStatus.status === "online" ? "rgba(255, 255, 255, 0.05)" : "rgba(220, 38, 38, 0.15)",
            border: `1px solid ${healthStatus.status === "online" ? "rgba(255, 255, 255, 0.15)" : "rgba(220, 38, 38, 0.3)"}`,
            display: "flex",
            alignItems: "center",
            justifyContent: "space-between",
          }}
        >
          <div>
            <strong>API Health: </strong>
            <span style={{ color: healthStatus.status === "online" ? "#ffffff" : "#ef4444" }}>
              {healthStatus.status === "online" ? "Online & Ready" : (healthStatus.error || "Offline")}
            </span>
            {healthStatus.version && <span className="muted"> (v{healthStatus.version})</span>}
          </div>
          <button type="button" className="sec" style={{ fontSize: "0.75rem", padding: "0.2rem 0.5rem" }} onClick={() => setHealthStatus(null)}>
            Dismiss
          </button>
        </div>
      )}

      {/* Grid: API Key & Outbound Webhooks */}
      <div className="grid2">
        {/* Card 1: API Key & Authentication */}
        <section className="glass panel stack">
          <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center" }}>
            <h2 style={{ margin: 0, fontSize: "1.25rem" }}>API Key Authentication</h2>
            <span
              className="badge"
              style={{
                background: creds?.api_key_configured ? "rgba(255,255,255,0.08)" : "rgba(220,38,38,0.15)",
                color: creds?.api_key_configured ? "#ffffff" : "#ef4444",
                border: "1px solid rgba(255,255,255,0.15)",
              }}
            >
              {creds?.api_key_configured ? "Active" : "Not Configured"}
            </span>
          </div>

          <p className="muted" style={{ fontSize: "0.85rem", margin: 0 }}>
            Authenticate every request with the <code className="mono">X-API-Key</code> HTTP header or <code className="mono">Authorization: Bearer &lt;key&gt;</code>.
          </p>

          <div className="field">
            <label htmlFor="api_key_display">Active External API Key</label>
            <div style={{ display: "flex", gap: "0.5rem" }}>
              <input
                id="api_key_display"
                type={showKey ? "text" : "password"}
                readOnly
                value={apiKeyVal || "PV_EXTERNAL_API_KEY"}
                className="mono"
                style={{ flex: 1, letterSpacing: showKey ? "normal" : "2px" }}
              />
              <button
                type="button"
                className="sec"
                onClick={() => setShowKey(!showKey)}
                style={{ padding: "0.4rem 0.75rem", fontSize: "0.85rem" }}
              >
                {showKey ? "Hide" : "Show"}
              </button>
              <button
                type="button"
                className="sec"
                onClick={() => copyText(apiKeyVal, "api_key")}
                style={{ padding: "0.4rem 0.75rem", fontSize: "0.85rem" }}
              >
                {copiedField === "api_key" ? "Copied!" : "Copy"}
              </button>
            </div>
          </div>

          <div className="field">
            <label htmlFor="base_url_display">Base API Endpoint</label>
            <div style={{ display: "flex", gap: "0.5rem" }}>
              <input
                id="base_url_display"
                type="text"
                readOnly
                value={`${baseUrl}/api/v1/external`}
                className="mono"
                style={{ flex: 1 }}
              />
              <button
                type="button"
                className="sec"
                onClick={() => copyText(`${baseUrl}/api/v1/external`, "base_url")}
                style={{ padding: "0.4rem 0.75rem", fontSize: "0.85rem" }}
              >
                {copiedField === "base_url" ? "Copied!" : "Copy"}
              </button>
            </div>
          </div>
        </section>

        {/* Card 2: Outbound Webhooks */}
        <section className="glass panel stack">
          <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center" }}>
            <h2 style={{ margin: 0, fontSize: "1.25rem" }}>Outbound Webhooks</h2>
            <span
              className="badge"
              style={{
                background: creds?.webhook_configured ? "rgba(255,255,255,0.08)" : "rgba(255,255,255,0.04)",
                color: creds?.webhook_configured ? "#ffffff" : "#71717a",
                border: "1px solid rgba(255,255,255,0.15)",
              }}
            >
              {creds?.webhook_configured ? "Configured" : "Optional"}
            </span>
          </div>

          <p className="muted" style={{ fontSize: "0.85rem", margin: 0 }}>
            PayVerify automatically dispatches signed HTTP POST requests to your server the moment a payment is verified by bank ledger credit or manual override.
          </p>

          <div className="field">
            <label htmlFor="webhook_url_display">Target Webhook URL</label>
            <div style={{ display: "flex", gap: "0.5rem" }}>
              <input
                id="webhook_url_display"
                type="text"
                readOnly
                value={creds?.webhook_url || "https://your-store.com/api/webhooks/payverify"}
                className="mono"
                style={{ flex: 1 }}
              />
              <button
                type="button"
                className="sec"
                onClick={() => copyText(creds?.webhook_url || "", "webhook_url")}
                style={{ padding: "0.4rem 0.75rem", fontSize: "0.85rem" }}
              >
                {copiedField === "webhook_url" ? "Copied!" : "Copy"}
              </button>
            </div>
          </div>

          <div className="field">
            <label htmlFor="webhook_secret_display">Signing Secret (HMAC-SHA256)</label>
            <div style={{ display: "flex", gap: "0.5rem" }}>
              <input
                id="webhook_secret_display"
                type={showSecret ? "text" : "password"}
                readOnly
                value={webhookSecretVal || "PV_EXTERNAL_WEBHOOK_SECRET"}
                className="mono"
                style={{ flex: 1, letterSpacing: showSecret ? "normal" : "2px" }}
              />
              <button
                type="button"
                className="sec"
                onClick={() => setShowSecret(!showSecret)}
                style={{ padding: "0.4rem 0.75rem", fontSize: "0.85rem" }}
              >
                {showSecret ? "Hide" : "Show"}
              </button>
              <button
                type="button"
                className="sec"
                onClick={() => copyText(webhookSecretVal, "webhook_secret")}
                style={{ padding: "0.4rem 0.75rem", fontSize: "0.85rem" }}
              >
                {copiedField === "webhook_secret" ? "Copied!" : "Copy"}
              </button>
            </div>
          </div>
        </section>
      </div>

      {/* Code Snippets Tabs */}
      <section className="glass panel stack">
        <div style={{ display: "flex", flexWrap: "wrap", alignItems: "center", justifyContent: "space-between", gap: "1rem" }}>
          <div>
            <h2 style={{ margin: 0, fontSize: "1.3rem" }}>Integration Code Samples</h2>
            <p className="muted" style={{ fontSize: "0.85rem", margin: "0.25rem 0 0 0" }}>
              Single-request checkout order registration with payment slip upload.
            </p>
          </div>
          <button
            type="button"
            className="sec"
            onClick={() => copyText(codeSnippets[selectedLang].code, "snippet")}
            style={{ padding: "0.45rem 1rem", fontSize: "0.85rem" }}
          >
            {copiedField === "snippet" ? "Copied Code!" : "Copy Snippet"}
          </button>
        </div>

        {/* Tab Buttons */}
        <div style={{ display: "flex", gap: "0.5rem", borderBottom: "1px solid rgba(255,255,255,0.08)", paddingBottom: "0.75rem" }}>
          {(Object.keys(codeSnippets) as TabLanguage[]).map((lang) => (
            <button
              key={lang}
              type="button"
              className={selectedLang === lang ? "" : "sec"}
              onClick={() => setSelectedLang(lang)}
              style={{
                background: selectedLang === lang ? "#ffffff" : "transparent",
                color: selectedLang === lang ? "#000000" : "#a1a1aa",
                fontWeight: selectedLang === lang ? 700 : 400,
                padding: "0.35rem 0.85rem",
                fontSize: "0.85rem",
                borderRadius: "6px",
              }}
            >
              {codeSnippets[lang].label}
            </button>
          ))}
        </div>

        {/* Code Block Surface */}
        <pre
          className="mono"
          style={{
            background: "#080808",
            padding: "1.25rem",
            borderRadius: "8px",
            border: "1px solid rgba(255,255,255,0.08)",
            overflowX: "auto",
            fontSize: "0.85rem",
            lineHeight: "1.6",
            color: "#e4e4e7",
            margin: 0,
          }}
        >
          {codeSnippets[selectedLang].code}
        </pre>
      </section>

      {/* Webhook Payload Specifications Card */}
      <section className="glass panel stack">
        <h2 style={{ margin: 0, fontSize: "1.3rem" }}>Outbound Webhook Event Specification</h2>
        <p className="muted" style={{ fontSize: "0.85rem", margin: 0 }}>
          When a payment is matched and settled, PayVerify issues a POST request with headers <code className="mono">X-PayVerify-Signature: sha256=&lt;hmac&gt;</code> and <code className="mono">X-PayVerify-Event: payment.verified</code>.
        </p>

        <pre
          className="mono"
          style={{
            background: "#080808",
            padding: "1.25rem",
            borderRadius: "8px",
            border: "1px solid rgba(255,255,255,0.08)",
            overflowX: "auto",
            fontSize: "0.85rem",
            lineHeight: "1.6",
            color: "#a1a1aa",
            margin: 0,
          }}
        >{`{
  "event": "payment.verified",
  "order_id": 1094,
  "invoice_no": "INV-1094",
  "payment_code": "PV-8F3A2B",
  "status": "APPROVED",
  "amount_minor": 350000,
  "amount_formatted": "Rs. 3,500.00",
  "verification_basis": "bank_match",
  "verified_at": "2026-11-12T17:30:00Z",
  "reference": "STAN-03269940",
  "customer": {
    "id": 12,
    "name": "Ruwan Perera",
    "email": "ruwan@customer.com"
  }
}`}</pre>
      </section>
    </div>
  );
}

