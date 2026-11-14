"""Generate complete SVG vector diagrams for PayVerify documentation.
Renders natively across all Markdown viewers, VS Code, GitHub, and mobile.
"""
import os

os.makedirs("docs/diagrams", exist_ok=True)

# 1. Multi-Vector Architecture
svg_multi_vector = """<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 700" width="100%" height="100%">
  <defs>
    <style>
      .bg { fill: #09090b; }
      .group-box { fill: #121215; stroke: #27272a; stroke-width: 1.5; rx: 12px; }
      .group-title { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; font-size: 13px; font-weight: 700; fill: #71717a; text-transform: uppercase; letter-spacing: 1.2px; }
      .card { fill: #18181b; stroke: #3f3f46; stroke-width: 1.5; rx: 8px; }
      .card-highlight { fill: #1c1917; stroke: #e4e4e7; stroke-width: 2; rx: 8px; }
      .card-title { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; font-size: 14px; font-weight: 600; fill: #fafafa; }
      .card-sub { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; font-size: 11px; fill: #a1a1aa; }
      .arrow { stroke: #71717a; stroke-width: 1.8; fill: none; stroke-linecap: round; stroke-linejoin: round; }
      .arrow-hl { stroke: #fafafa; stroke-width: 2.2; fill: none; stroke-linecap: round; stroke-linejoin: round; }
      .badge { fill: #27272a; stroke: #3f3f46; stroke-width: 1; rx: 4px; }
      .badge-text { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 10px; fill: #e4e4e7; }
    </style>
    <marker id="arrowhead" markerWidth="8" markerHeight="6" refX="7" refY="3" orient="auto">
      <polygon points="0 0, 8 3, 0 6" fill="#71717a" />
    </marker>
    <marker id="arrowhead-hl" markerWidth="8" markerHeight="6" refX="7" refY="3" orient="auto">
      <polygon points="0 0, 8 3, 0 6" fill="#fafafa" />
    </marker>
  </defs>

  <rect width="1000" height="700" class="bg" rx="16" />
  <text x="40" y="45" font-family="-apple-system, BlinkMacSystemFont, sans-serif" font-size="20" font-weight="700" fill="#ffffff">PAYVERIFY MULTI-VECTOR ARCHITECTURE</text>
  <text x="40" y="68" font-family="-apple-system, BlinkMacSystemFont, sans-serif" font-size="12" fill="#a1a1aa">Deterministic Reconciliation Engine with Independent Evidence Vectors</text>

  <rect x="40" y="100" width="280" height="560" class="group-box" />
  <text x="60" y="130" class="group-title">1. Evidence Vectors</text>

  <rect x="60" y="150" width="240" height="85" class="card" />
  <text x="75" y="175" class="card-title">Vector 1: Customer Proofs</text>
  <text x="75" y="195" class="card-sub">JPEG, PNG, Multi-page PDF slips</text>
  <rect x="75" y="205" width="115" height="20" class="badge" />
  <text x="82" y="219" class="badge-text">POST /orders/upload</text>

  <rect x="60" y="250" width="240" height="85" class="card" />
  <text x="75" y="275" class="card-title">Vector 2: WhatsApp Banking</text>
  <text x="75" y="295" class="card-sub">ComBank WhatsApp Daemon</text>
  <rect x="75" y="305" width="125" height="20" class="badge" />
  <text x="82" y="319" class="badge-text">Baileys + Pairing Code</text>

  <rect x="60" y="350" width="240" height="85" class="card" />
  <text x="75" y="375" class="card-title">Vector 3: Android SMS Relay</text>
  <text x="75" y="395" class="card-sub">Hardware Key ECDSA P-256</text>
  <rect x="75" y="405" width="125" height="20" class="badge" />
  <text x="82" y="419" class="badge-text">vas.combank.net URLs</text>

  <rect x="60" y="450" width="240" height="85" class="card" />
  <text x="75" y="475" class="card-title">Vector 4: Bank Statements</text>
  <text x="75" y="495" class="card-sub">CSV, Excel XLSX imports</text>
  <rect x="75" y="505" width="125" height="20" class="badge" />
  <text x="82" y="519" class="badge-text">statement-imports API</text>

  <rect x="60" y="550" width="240" height="85" class="card" />
  <text x="75" y="575" class="card-title">Vector 5: External Checkout</text>
  <text x="75" y="595" class="card-sub">Shopify, WooCommerce, POS</text>
  <rect x="75" y="605" width="125" height="20" class="badge" />
  <text x="82" y="619" class="badge-text">/api/v1/external/*</text>

  <rect x="360" y="100" width="280" height="560" class="group-box" />
  <text x="380" y="130" class="group-title">2. Processing &amp; Forensics</text>

  <rect x="380" y="150" width="240" height="95" class="card" />
  <text x="395" y="175" class="card-title">Multimodal AI Vision</text>
  <text x="395" y="195" class="card-sub">Google Gemini 1.5 Flash / Ollama</text>
  <text x="395" y="215" class="card-sub">Amount, Ref, Account OCR</text>
  <rect x="395" y="222" width="115" height="18" class="badge" />
  <text x="402" y="235" class="badge-text">Advisory Evidence E2</text>

  <rect x="380" y="260" width="240" height="85" class="card" />
  <text x="395" y="285" class="card-title">Forensics Engine</text>
  <text x="395" y="305" class="card-sub">SHA-256 duplicate detection</text>
  <text x="395" y="325" class="card-sub">Perceptual dHash &amp; EXIF analysis</text>

  <rect x="380" y="360" width="240" height="85" class="card" />
  <text x="395" y="385" class="card-title">Zero-Leak OTP Buffer</text>
  <text x="395" y="405" class="card-sub">In-Memory 60s TTL relay</text>
  <text x="395" y="425" class="card-sub">Never persisted to disk or DB</text>

  <rect x="380" y="460" width="240" height="85" class="card" />
  <text x="395" y="485" class="card-title">ComBank Receipt Advice</text>
  <text x="395" y="505" class="card-sub">Official STAN &amp; Notes breakdown</text>
  <text x="395" y="525" class="card-sub">Encrypted Deposit Advice PDF</text>

  <rect x="380" y="560" width="240" height="80" class="card" />
  <text x="395" y="585" class="card-title">Encrypted Storage</text>
  <text x="395" y="605" class="card-sub">Fernet 256-bit AES encryption</text>
  <text x="395" y="625" class="card-sub">/srv/storage encrypted at rest</text>

  <rect x="680" y="100" width="280" height="560" class="group-box" />
  <text x="700" y="130" class="group-title">3. Deterministic Settlement</text>

  <rect x="700" y="150" width="240" height="150" class="card-highlight" />
  <text x="715" y="180" class="card-title">Reconciliation Matcher</text>
  <text x="715" y="205" class="card-sub">Exact amount matching</text>
  <text x="715" y="225" class="card-sub">STAN / Remarks Payment Code</text>
  <text x="715" y="245" class="card-sub">Booking window enforcement</text>
  <rect x="715" y="260" width="145" height="24" class="badge" style="fill:#22c55e22; stroke:#22c55e;" />
  <text x="723" y="276" class="badge-text" style="fill:#4ade80;">basis="bank_match"</text>

  <rect x="700" y="320" width="240" height="100" class="card" />
  <text x="715" y="345" class="card-title">Cryptographic Audit Chain</text>
  <text x="715" y="365" class="card-sub">Immutable SHA-256 hash log</text>
  <text x="715" y="385" class="card-sub">hash_prev / hash_self verification</text>
  <text x="715" y="405" class="card-sub">Zero-tamper guarantee</text>

  <rect x="700" y="440" width="240" height="90" class="card" />
  <text x="715" y="465" class="card-title">Outbound Webhooks</text>
  <text x="715" y="485" class="card-sub">HMAC-SHA256 signed events</text>
  <text x="715" y="505" class="card-sub">Replay prevention timestamp</text>
  <text x="715" y="520" class="card-sub">payment.verified dispatch</text>

  <rect x="700" y="550" width="240" height="90" class="card" />
  <text x="715" y="575" class="card-title">Manual Review Workbench</text>
  <text x="715" y="595" class="card-sub">High-contrast split view</text>
  <text x="715" y="615" class="card-sub">One-click staff override</text>
  <text x="715" y="630" class="card-sub">basis="admin_override"</text>

  <path d="M 300 190 L 340 190 L 340 195 L 380 195" class="arrow" marker-end="url(#arrowhead)" />
  <path d="M 300 200 L 340 200 L 340 300 L 380 300" class="arrow" marker-end="url(#arrowhead)" />
  <path d="M 300 290 L 380 290" class="arrow" marker-end="url(#arrowhead)" />
  <path d="M 300 390 L 380 390" class="arrow" marker-end="url(#arrowhead)" />
  <path d="M 620 200 L 660 200 L 660 210 L 700 210" class="arrow-hl" marker-end="url(#arrowhead-hl)" />
  <path d="M 620 300 L 660 300 L 660 230 L 700 230" class="arrow-hl" marker-end="url(#arrowhead-hl)" />
  <path d="M 620 500 L 660 500 L 660 250 L 700 250" class="arrow-hl" marker-end="url(#arrowhead-hl)" />
  <path d="M 820 300 L 820 320" class="arrow" marker-end="url(#arrowhead)" />
  <path d="M 820 420 L 820 440" class="arrow" marker-end="url(#arrowhead)" />
</svg>
"""

# 2. Single-Request Order & Proof Review Sequence Diagram
svg_order_review = """<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 950 640" width="100%" height="100%">
  <defs>
    <style>
      .bg { fill: #09090b; }
      .lifeline { stroke: #27272a; stroke-width: 1.5; stroke-dasharray: 4 4; }
      .header-box { fill: #18181b; stroke: #3f3f46; stroke-width: 1.5; rx: 6px; }
      .header-text { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; font-size: 13px; font-weight: 600; fill: #fafafa; text-anchor: middle; }
      .msg-line { stroke: #71717a; stroke-width: 1.5; fill: none; }
      .msg-line-hl { stroke: #38bdf8; stroke-width: 1.8; fill: none; }
      .msg-line-success { stroke: #4ade80; stroke-width: 1.8; fill: none; }
      .msg-line-warn { stroke: #fbbf24; stroke-width: 1.8; fill: none; }
      .msg-line-err { stroke: #f87171; stroke-width: 1.8; fill: none; }
      .msg-text { font-family: -apple-system, BlinkMacSystemFont, sans-serif; font-size: 11px; fill: #d4d4d8; }
      .msg-code { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 10px; fill: #38bdf8; }
      .note-box { fill: #1c1917; stroke: #78716c; stroke-width: 1; rx: 4px; }
      .note-text { font-family: -apple-system, BlinkMacSystemFont, sans-serif; font-size: 10.5px; fill: #d6d3d1; }
      .alt-box { fill: #121215; stroke: #27272a; stroke-width: 1; rx: 6px; }
      .alt-label { font-family: ui-monospace, monospace; font-size: 10px; font-weight: 700; fill: #a1a1aa; }
    </style>
    <marker id="arrow-gray" markerWidth="8" markerHeight="6" refX="7" refY="3" orient="auto">
      <polygon points="0 0, 8 3, 0 6" fill="#71717a" />
    </marker>
    <marker id="arrow-cyan" markerWidth="8" markerHeight="6" refX="7" refY="3" orient="auto">
      <polygon points="0 0, 8 3, 0 6" fill="#38bdf8" />
    </marker>
    <marker id="arrow-green" markerWidth="8" markerHeight="6" refX="7" refY="3" orient="auto">
      <polygon points="0 0, 8 3, 0 6" fill="#4ade80" />
    </marker>
    <marker id="arrow-red" markerWidth="8" markerHeight="6" refX="7" refY="3" orient="auto">
      <polygon points="0 0, 8 3, 0 6" fill="#f87171" />
    </marker>
  </defs>

  <rect width="950" height="640" class="bg" rx="16" />
  <text x="40" y="40" font-family="-apple-system, BlinkMacSystemFont, sans-serif" font-size="18" font-weight="700" fill="#ffffff">ORDER &amp; PROOF INSTANT REVIEW SEQUENCE</text>
  <text x="40" y="60" font-family="-apple-system, BlinkMacSystemFont, sans-serif" font-size="11" fill="#a1a1aa">Single-Request AI Extraction, Forensic Analysis, and Deterministic Bank Settlement</text>

  <!-- Lifeline Headers -->
  <!-- Col 1: Merchant Client (x=100) -->
  <rect x="30" y="85" width="140" height="35" class="header-box" />
  <text x="100" y="107" class="header-text">Merchant Client</text>
  <line x1="100" y1="120" x2="100" y2="600" class="lifeline" />

  <!-- Col 2: PayVerify API (x=300) -->
  <rect x="230" y="85" width="140" height="35" class="header-box" />
  <text x="300" y="107" class="header-text">PayVerify API</text>
  <line x1="300" y1="120" x2="300" y2="600" class="lifeline" />

  <!-- Col 3: Forensics & DB (x=500) -->
  <rect x="430" y="85" width="140" height="35" class="header-box" />
  <text x="500" y="107" class="header-text">Forensics &amp; DB</text>
  <line x1="500" y1="120" x2="500" y2="600" class="lifeline" />

  <!-- Col 4: Multimodal AI (x=700) -->
  <rect x="630" y="85" width="140" height="35" class="header-box" />
  <text x="700" y="107" class="header-text">AI Vision (Gemini)</text>
  <line x1="700" y1="120" x2="700" y2="600" class="lifeline" />

  <!-- Col 5: Bank Matcher (x=860) -->
  <rect x="790" y="85" width="140" height="35" class="header-box" />
  <text x="860" y="107" class="header-text">Bank Matcher</text>
  <line x1="860" y1="120" x2="860" y2="600" class="lifeline" />

  <!-- Step 1: POST request -->
  <line x1="100" y1="150" x2="295" y2="150" class="msg-line-hl" marker-end="url(#arrow-cyan)" />
  <text x="110" y="142" class="msg-code">1. POST /proofs/upload-and-review (invoice, amount, base64)</text>

  <!-- Step 2: Fernet encrypt -->
  <rect x="230" y="165" width="150" height="30" class="note-box" />
  <text x="238" y="184" class="note-text">Fernet encrypt proof to disk</text>

  <!-- Step 3: Check dupes -->
  <line x1="300" y1="215" x2="495" y2="215" class="msg-line" marker-end="url(#arrow-gray)" />
  <text x="310" y="208" class="msg-text">2. Check SHA-256 and dHash duplicate</text>

  <!-- Step 4: AI Extraction -->
  <line x1="300" y1="250" x2="695" y2="250" class="msg-line-hl" marker-end="url(#arrow-cyan)" />
  <text x="310" y="242" class="msg-text">3. Extract amount, ref, account tail, date</text>

  <!-- Step 5: AI Return -->
  <line x1="700" y1="280" x2="305" y2="280" class="msg-line" stroke-dasharray="3 3" marker-end="url(#arrow-gray)" />
  <text x="430" y="274" class="msg-text">Extracted metadata &amp; confidence</text>

  <!-- Step 6: Discrepancy eval -->
  <line x1="300" y1="315" x2="495" y2="315" class="msg-line" marker-end="url(#arrow-gray)" />
  <text x="310" y="308" class="msg-text">4. Evaluate discrepancies against order</text>

  <!-- Step 7: Match bank credits -->
  <line x1="300" y1="350" x2="855" y2="350" class="msg-line-hl" marker-end="url(#arrow-cyan)" />
  <text x="510" y="342" class="msg-text">5. Query unsettled bank credits</text>

  <!-- ALT Box: Settlement Verdicts -->
  <rect x="50" y="380" width="860" height="200" class="alt-box" />
  <text x="65" y="400" class="alt-label">[ALT: DETERMINISTIC SETTLEMENT VERDICTS]</text>

  <!-- Branch 1: Auto Verified -->
  <text x="65" y="425" font-family="-apple-system, sans-serif" font-size="11" font-weight="600" fill="#4ade80">[1] Exact Bank Credit Found:</text>
  <line x1="300" y1="440" x2="105" y2="440" class="msg-line-success" stroke-dasharray="4 2" marker-end="url(#arrow-green)" />
  <text x="110" y="434" class="msg-code" style="fill:#4ade80;">200 OK { is_paid: true, status: "auto_verified", basis: "bank_match" }</text>

  <!-- Divider line -->
  <line x1="50" y1="460" x2="910" y2="460" stroke="#27272a" stroke-dasharray="3 3" />

  <!-- Branch 2: Awaiting bank confirmation -->
  <text x="65" y="485" font-family="-apple-system, sans-serif" font-size="11" font-weight="600" fill="#38bdf8">[2] Credit Not Yet Settled in Bank Statement:</text>
  <line x1="300" y1="500" x2="105" y2="500" class="msg-line-hl" stroke-dasharray="4 2" marker-end="url(#arrow-cyan)" />
  <text x="110" y="494" class="msg-code" style="fill:#38bdf8;">200 OK { is_paid: false, status: "awaiting_bank_confirmation" }</text>

  <!-- Divider line -->
  <line x1="50" y1="520" x2="910" y2="520" stroke="#27272a" stroke-dasharray="3 3" />

  <!-- Branch 3: Manual review discrepancy -->
  <text x="65" y="545" font-family="-apple-system, sans-serif" font-size="11" font-weight="600" fill="#fbbf24">[3] Amount / Reference Discrepancy or Duplicate:</text>
  <line x1="300" y1="560" x2="105" y2="560" class="msg-line-warn" stroke-dasharray="4 2" marker-end="url(#arrow-gray)" />
  <text x="110" y="554" class="msg-code" style="fill:#fbbf24;">200 OK { is_paid: false, status: "manual_review", discrepancies: [...] }</text>
</svg>
"""

# 3. Outbound Webhook Lifecycle Diagram
svg_webhook = """<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 950 500" width="100%" height="100%">
  <defs>
    <style>
      .bg { fill: #09090b; }
      .lifeline { stroke: #27272a; stroke-width: 1.5; stroke-dasharray: 4 4; }
      .header-box { fill: #18181b; stroke: #3f3f46; stroke-width: 1.5; rx: 6px; }
      .header-text { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; font-size: 13px; font-weight: 600; fill: #fafafa; text-anchor: middle; }
      .msg-line-hl { stroke: #38bdf8; stroke-width: 1.8; fill: none; }
      .msg-line-success { stroke: #4ade80; stroke-width: 1.8; fill: none; }
      .msg-text { font-family: -apple-system, BlinkMacSystemFont, sans-serif; font-size: 11px; fill: #d4d4d8; }
      .msg-code { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 10px; fill: #38bdf8; }
      .note-box { fill: #1c1917; stroke: #78716c; stroke-width: 1; rx: 4px; }
      .note-text { font-family: -apple-system, BlinkMacSystemFont, sans-serif; font-size: 10.5px; fill: #d6d3d1; }
    </style>
    <marker id="arrow-cyan" markerWidth="8" markerHeight="6" refX="7" refY="3" orient="auto">
      <polygon points="0 0, 8 3, 0 6" fill="#38bdf8" />
    </marker>
    <marker id="arrow-green" markerWidth="8" markerHeight="6" refX="7" refY="3" orient="auto">
      <polygon points="0 0, 8 3, 0 6" fill="#4ade80" />
    </marker>
  </defs>

  <rect width="950" height="500" class="bg" rx="16" />
  <text x="40" y="40" font-family="-apple-system, BlinkMacSystemFont, sans-serif" font-size="18" font-weight="700" fill="#ffffff">OUTBOUND WEBHOOK LIFECYCLE &amp; CRYPTOGRAPHIC VERIFICATION</text>
  <text x="40" y="60" font-family="-apple-system, BlinkMacSystemFont, sans-serif" font-size="11" fill="#a1a1aa">HMAC-SHA256 Signature Verification and Replay Attack Mitigation</text>

  <!-- Lifelines -->
  <!-- Col 1: Core Engine (x=150) -->
  <rect x="70" y="85" width="160" height="35" class="header-box" />
  <text x="150" y="107" class="header-text">PayVerify Engine</text>
  <line x1="150" y1="120" x2="150" y2="460" class="lifeline" />

  <!-- Col 2: Webhook Worker (x=450) -->
  <rect x="370" y="85" width="160" height="35" class="header-box" />
  <text x="450" y="107" class="header-text">Webhook Worker</text>
  <line x1="450" y1="120" x2="450" y2="460" class="lifeline" />

  <!-- Col 3: Merchant Webhook (x=780) -->
  <rect x="700" y="85" width="160" height="35" class="header-box" />
  <text x="780" y="107" class="header-text">Merchant Server</text>
  <line x1="780" y1="120" x2="780" y2="460" class="lifeline" />

  <!-- Step 1: Transition to verified -->
  <line x1="150" y1="160" x2="445" y2="160" class="msg-line-hl" marker-end="url(#arrow-cyan)" />
  <text x="160" y="152" class="msg-text">1. Claim transitioned to VERIFIED (basis="bank_match")</text>

  <!-- Step 2: Compute HMAC -->
  <rect x="360" y="180" width="220" height="48" class="note-box" />
  <text x="370" y="200" class="note-text">Generate Unix timestamp</text>
  <text x="370" y="218" class="note-text">signature = HMAC-SHA256(secret, t + "." + body)</text>

  <!-- Step 3: Dispatch Webhook -->
  <line x1="450" y1="260" x2="775" y2="260" class="msg-line-hl" marker-end="url(#arrow-cyan)" />
  <text x="460" y="245" class="msg-code">POST /api/payverify-webhook</text>
  <text x="460" y="258" class="msg-text" style="font-size:10px; fill:#a1a1aa;">Headers: X-PayVerify-Signature, X-PayVerify-Timestamp</text>

  <!-- Step 4: Validate -->
  <rect x="680" y="280" width="210" height="60" class="note-box" />
  <text x="690" y="300" class="note-text">1. Check |now - timestamp| &lt; 300s</text>
  <text x="690" y="318" class="note-text">2. Recalculate HMAC-SHA256</text>
  <text x="690" y="335" class="note-text">3. crypto.timingSafeEqual()</text>

  <!-- Step 5: Mark paid & return -->
  <line x1="780" y1="375" x2="455" y2="375" class="msg-line-success" stroke-dasharray="4 2" marker-end="url(#arrow-green)" />
  <text x="560" y="367" class="msg-code" style="fill:#4ade80;">HTTP 200 OK (Event Acknowledged)</text>

  <rect x="680" y="400" width="210" height="30" class="header-box" style="fill:#14532d; stroke:#22c55e;" />
  <text x="785" y="420" font-family="-apple-system, sans-serif" font-size="11" font-weight="600" fill="#4ade80" text-anchor="middle">Order Marked as PAID</text>
</svg>
"""

# 4. WhatsApp Bot & Android OTP Relay Sequence
svg_whatsapp = """<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 950 560" width="100%" height="100%">
  <defs>
    <style>
      .bg { fill: #09090b; }
      .lifeline { stroke: #27272a; stroke-width: 1.5; stroke-dasharray: 4 4; }
      .header-box { fill: #18181b; stroke: #3f3f46; stroke-width: 1.5; rx: 6px; }
      .header-text { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; font-size: 13px; font-weight: 600; fill: #fafafa; text-anchor: middle; }
      .msg-line { stroke: #71717a; stroke-width: 1.5; fill: none; }
      .msg-line-hl { stroke: #38bdf8; stroke-width: 1.8; fill: none; }
      .msg-line-green { stroke: #4ade80; stroke-width: 1.8; fill: none; }
      .msg-text { font-family: -apple-system, BlinkMacSystemFont, sans-serif; font-size: 11px; fill: #d4d4d8; }
      .msg-code { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 10px; fill: #38bdf8; }
      .note-box { fill: #1c1917; stroke: #78716c; stroke-width: 1; rx: 4px; }
      .note-text { font-family: -apple-system, BlinkMacSystemFont, sans-serif; font-size: 10.5px; fill: #d6d3d1; }
    </style>
    <marker id="arrow-gray" markerWidth="8" markerHeight="6" refX="7" refY="3" orient="auto">
      <polygon points="0 0, 8 3, 0 6" fill="#71717a" />
    </marker>
    <marker id="arrow-cyan" markerWidth="8" markerHeight="6" refX="7" refY="3" orient="auto">
      <polygon points="0 0, 8 3, 0 6" fill="#38bdf8" />
    </marker>
    <marker id="arrow-green" markerWidth="8" markerHeight="6" refX="7" refY="3" orient="auto">
      <polygon points="0 0, 8 3, 0 6" fill="#4ade80" />
    </marker>
  </defs>

  <rect width="950" height="560" class="bg" rx="16" />
  <text x="40" y="40" font-family="-apple-system, BlinkMacSystemFont, sans-serif" font-size="18" font-weight="700" fill="#ffffff">COMBANK WHATSAPP BOT &amp; ZERO-LEAK OTP RELAY</text>
  <text x="40" y="60" font-family="-apple-system, BlinkMacSystemFont, sans-serif" font-size="11" fill="#a1a1aa">Automated Bank Statement Fetching with In-Memory Ephemeral OTP Relay</text>

  <!-- Lifelines -->
  <!-- Col 1: WhatsApp Bot (x=120) -->
  <rect x="40" y="85" width="160" height="35" class="header-box" />
  <text x="120" y="107" class="header-text">WhatsApp Bot</text>
  <line x1="120" y1="120" x2="120" y2="520" class="lifeline" />

  <!-- Col 2: ComBank WhatsApp (x=380) -->
  <rect x="300" y="85" width="160" height="35" class="header-box" />
  <text x="380" y="107" class="header-text">ComBank WhatsApp</text>
  <line x1="380" y1="120" x2="380" y2="520" class="lifeline" />

  <!-- Col 3: Android Phone (x=640) -->
  <rect x="560" y="85" width="160" height="35" class="header-box" />
  <text x="640" y="107" class="header-text">Android Relay Phone</text>
  <line x1="640" y1="120" x2="640" y2="520" class="lifeline" />

  <!-- Col 4: PayVerify API (x=850) -->
  <rect x="770" y="85" width="160" height="35" class="header-box" />
  <text x="850" y="107" class="header-text">PayVerify API</text>
  <line x1="850" y1="120" x2="850" y2="520" class="lifeline" />

  <!-- Step 1: Bot triggers menu -->
  <line x1="120" y1="150" x2="375" y2="150" class="msg-line" marker-end="url(#arrow-gray)" />
  <text x="140" y="142" class="msg-text">1. 'hi' -&gt; English (1) -&gt; Accept (1) -&gt; Account History (2)</text>

  <!-- Step 2: Bank sends SMS OTP -->
  <line x1="380" y1="185" x2="125" y2="185" class="msg-line" stroke-dasharray="3 3" marker-end="url(#arrow-gray)" />
  <text x="140" y="177" class="msg-text">2. Prompt: "Please enter Merchant NIC"</text>

  <line x1="120" y1="215" x2="375" y2="215" class="msg-line" marker-end="url(#arrow-gray)" />
  <text x="140" y="207" class="msg-text">3. Sends NIC (199512345678)</text>

  <line x1="380" y1="250" x2="635" y2="250" class="msg-line-hl" marker-end="url(#arrow-cyan)" />
  <text x="410" y="242" class="msg-text">4. ComBank dispatches SMS with 6-digit OTP</text>

  <!-- Step 3: Android intercept in memory -->
  <rect x="550" y="265" width="180" height="40" class="note-box" />
  <text x="560" y="282" class="note-text">Intercept SMS in memory</text>
  <text x="560" y="296" class="note-text">Never written to SQLite or disk</text>

  <!-- Step 4: Phone relays to API -->
  <line x1="640" y1="325" x2="845" y2="325" class="msg-line-hl" marker-end="url(#arrow-cyan)" />
  <text x="650" y="317" class="msg-text">5. POST /devices/otp-relay (ECDSA signed)</text>

  <!-- Step 5: API relays to bot -->
  <line x1="850" y1="365" x2="125" y2="365" class="msg-line-green" marker-end="url(#arrow-green)" />
  <text x="350" y="357" class="msg-code">6. In-memory buffer injects OTP (60s TTL)</text>

  <!-- Step 6: Bot sends OTP to ComBank -->
  <line x1="120" y1="405" x2="375" y2="405" class="msg-line-green" marker-end="url(#arrow-green)" />
  <text x="140" y="397" class="msg-text">7. Sends 6-digit OTP to WhatsApp session</text>

  <rect x="60" y="420" width="180" height="30" class="note-box" />
  <text x="70" y="440" class="note-text">Wipe OTP from memory immediately</text>

  <!-- Step 7: Bank sends statement table -->
  <line x1="380" y1="475" x2="125" y2="475" class="msg-line-hl" stroke-dasharray="3 3" marker-end="url(#arrow-cyan)" />
  <text x="150" y="467" class="msg-text">8. ComBank returns Statement History Table</text>

  <!-- Step 8: Parse and ingest -->
  <line x1="120" y1="505" x2="845" y2="505" class="msg-line-green" marker-end="url(#arrow-green)" />
  <text x="350" y="497" class="msg-code">9. POST /api/v1/bank-sync/whatsapp-history (Parsed rows)</text>
</svg>
"""

with open("docs/diagrams/multi-vector-architecture.svg", "w", encoding="utf-8") as f:
    f.write(svg_multi_vector)
print("Saved multi-vector-architecture.svg")

with open("docs/diagrams/order-proof-review.svg", "w", encoding="utf-8") as f:
    f.write(svg_order_review)
print("Saved order-proof-review.svg")

with open("docs/diagrams/webhook-lifecycle.svg", "w", encoding="utf-8") as f:
    f.write(svg_webhook)
print("Saved webhook-lifecycle.svg")

with open("docs/diagrams/whatsapp-otp-relay.svg", "w", encoding="utf-8") as f:
    f.write(svg_whatsapp)
print("Saved whatsapp-otp-relay.svg")
