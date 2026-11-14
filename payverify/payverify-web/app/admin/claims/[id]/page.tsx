"use client";
import { useParams, useRouter } from "next/navigation";
import { useCallback, useEffect, useState } from "react";
import StatusBadge from "@/components/StatusBadge";
import { api, fetchBlobUrl, session } from "@/lib/api";
import { money, toMinor, when } from "@/lib/format";

const FINAL_STATUSES = ["verified", "rejected"];

const REJECTION_REASONS = [
  {
    code: "amount_mismatch",
    title: "Amount Mismatch",
    customerNotice: "The payment amount received in our bank does not match your order total. Please ensure the exact amount is transferred or contact support.",
  },
  {
    code: "illegible_proof",
    title: "Illegible or Cropped Receipt",
    customerNotice: "The uploaded receipt image is blurry, cropped, or unreadable. Please upload a clear photo or official electronic PDF deposit advice.",
  },
  {
    code: "duplicate_proof",
    title: "Duplicate Receipt",
    customerNotice: "This receipt or transaction reference has already been submitted and processed for another order.",
  },
  {
    code: "wrong_recipient",
    title: "Transferred to Wrong Account",
    customerNotice: "The transfer was made to an incorrect bank account number. Please check our official Commercial Bank details and verify with your bank.",
  },
  {
    code: "altered_document",
    title: "Document Alteration Detected",
    customerNotice: "Our automated forensics system detected signs of document editing or manipulation on the submitted receipt.",
  },
  {
    code: "other",
    title: "Other Reason",
    customerNotice: "We were unable to verify your deposit. Please upload a valid bank deposit slip or contact customer support.",
  },
];

interface ProofProps {
  file: {
    id: number;
    mime: string;
    size: number;
    sha256: string;
    dhash?: string;
  };
}

function ProofViewer({ file }: ProofProps) {
  const [f, setF] = useState<{ url: string; type: string } | null>(null);
  const [err, setErr] = useState("");
  const [zoom, setZoom] = useState(1);
  const [rotation, setRotation] = useState(0);
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [copied, setCopied] = useState(false);

  useEffect(() => {
    let u = "";
    fetchBlobUrl(`/api/v1/admin/files/${file.id}`)
      .then((r) => {
        u = r.url;
        setF(r);
      })
      .catch((e) => setErr(e.message));
    return () => {
      if (u) URL.revokeObjectURL(u);
    };
  }, [file.id]);

  function copyHash() {
    navigator.clipboard.writeText(file.sha256);
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  }

  function rotateClockwise() {
    setRotation((r) => (r + 90) % 360);
  }

  function resetView() {
    setZoom(1);
    setRotation(0);
  }

  const isPdf = file.mime === "application/pdf" || f?.type === "application/pdf";
  const formattedSize = file.size > 1024 * 1024
    ? `${(file.size / (1024 * 1024)).toFixed(2)} MB`
    : `${Math.round(file.size / 1024)} KB`;

  return (
    <div className="proof-viewer stack" style={{ gap: "0.75rem", marginBottom: "1.5rem" }}>
      {/* File Metadata Badges */}
      <div style={{ display: "flex", flexWrap: "wrap", alignItems: "center", justifyContent: "space-between", gap: "0.5rem" }}>
        <div style={{ display: "flex", flexWrap: "wrap", alignItems: "center", gap: "0.5rem", fontSize: "0.8rem" }}>
          <span className="badge" style={{ background: "rgba(255,255,255,0.06)", border: "1px solid rgba(255,255,255,0.12)", color: "#e4e4e7" }}>
            {isPdf ? "PDF Document" : file.mime.replace("image/", "").toUpperCase()}
          </span>
          <span className="badge" style={{ background: "rgba(255,255,255,0.06)", border: "1px solid rgba(255,255,255,0.12)", color: "#a1a1aa" }}>
            {formattedSize}
          </span>
          {file.dhash && (
            <span className="badge" style={{ background: "rgba(255,255,255,0.04)", border: "1px solid rgba(255,255,255,0.08)", color: "#71717a", fontFamily: "var(--font-mono)" }}>
              dHash: {file.dhash.slice(0, 8)}…
            </span>
          )}
        </div>
        <button
          type="button"
          onClick={copyHash}
          className="sec"
          style={{ fontSize: "0.75rem", padding: "0.25rem 0.6rem" }}
          title={file.sha256}
        >
          {copied ? "Copied SHA-256" : `SHA-256: ${file.sha256.slice(0, 10)}…`}
        </button>
      </div>

      {err && <p className="error" role="alert">{err}</p>}
      {!f && !err && (
        <div style={{ padding: "3rem 1rem", textAlign: "center", background: "rgba(255,255,255,0.02)", borderRadius: "8px" }}>
          <p className="muted" style={{ margin: 0 }}>Decrypting and loading proof file…</p>
        </div>
      )}

      {f && (
        <>
          {/* Controls Bar */}
          <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", gap: "0.5rem", background: "rgba(255,255,255,0.03)", padding: "0.4rem 0.6rem", borderRadius: "6px", border: "1px solid rgba(255,255,255,0.08)" }}>
            {!isPdf && (
              <div style={{ display: "flex", alignItems: "center", gap: "0.35rem" }}>
                <button
                  type="button"
                  className="sec"
                  style={{ padding: "0.2rem 0.5rem", fontSize: "0.8rem" }}
                  onClick={() => setZoom((z) => Math.min(3, +(z + 0.25).toFixed(2)))}
                >
                  + Zoom
                </button>
                <button
                  type="button"
                  className="sec"
                  style={{ padding: "0.2rem 0.5rem", fontSize: "0.8rem" }}
                  onClick={() => setZoom((z) => Math.max(0.5, +(z - 0.25).toFixed(2)))}
                >
                  - Zoom
                </button>
                <span className="muted" style={{ fontSize: "0.75rem", minWidth: "3rem", textAlign: "center" }}>
                  {Math.round(zoom * 100)}%
                </span>
                <button
                  type="button"
                  className="sec"
                  style={{ padding: "0.2rem 0.5rem", fontSize: "0.8rem" }}
                  onClick={rotateClockwise}
                  title="Rotate 90 degrees"
                >
                  Rotate ({rotation}°)
                </button>
                <button
                  type="button"
                  className="sec"
                  style={{ padding: "0.2rem 0.5rem", fontSize: "0.8rem" }}
                  onClick={resetView}
                >
                  Reset
                </button>
              </div>
            )}
            <div style={{ display: "flex", alignItems: "center", gap: "0.5rem", marginLeft: "auto" }}>
              <button
                type="button"
                className="sec"
                style={{ padding: "0.2rem 0.6rem", fontSize: "0.8rem" }}
                onClick={() => setIsModalOpen(true)}
              >
                Fullscreen
              </button>
              <a
                href={f.url}
                target="_blank"
                rel="noreferrer"
                className="sec"
                style={{ padding: "0.2rem 0.6rem", fontSize: "0.8rem", textDecoration: "none" }}
              >
                Open Tab
              </a>
              <a
                href={f.url}
                download={`payverify_proof_${file.id}.${isPdf ? "pdf" : "jpg"}`}
                className="sec"
                style={{ padding: "0.2rem 0.6rem", fontSize: "0.8rem", textDecoration: "none" }}
              >
                Download
              </a>
            </div>
          </div>

          {/* Proof Render Surface */}
          <div
            style={{
              position: "relative",
              width: "100%",
              minHeight: isPdf ? "480px" : "340px",
              maxHeight: "560px",
              overflow: "auto",
              background: "#080808",
              borderRadius: "8px",
              border: "1px solid rgba(255,255,255,0.08)",
              display: "flex",
              alignItems: "center",
              justifyContent: "center",
              padding: "0.5rem",
            }}
          >
            {isPdf ? (
              <iframe
                src={f.url}
                title="Payment proof (PDF)"
                style={{ width: "100%", height: "480px", border: "none", borderRadius: "4px" }}
              />
            ) : (
              // eslint-disable-next-line @next/next/no-img-element
              <img
                src={f.url}
                alt="Payment proof slip"
                style={{
                  maxWidth: zoom <= 1 ? "100%" : "none",
                  maxHeight: zoom <= 1 ? "520px" : "none",
                  transform: `scale(${zoom}) rotate(${rotation}deg)`,
                  transformOrigin: "center center",
                  transition: "transform 0.15s ease-out",
                  borderRadius: "4px",
                  boxShadow: "0 4px 20px rgba(0,0,0,0.5)",
                }}
              />
            )}
          </div>

          {/* Fullscreen Modal Lightbox */}
          {isModalOpen && (
            <div
              style={{
                position: "fixed",
                inset: 0,
                zIndex: 9999,
                background: "rgba(0,0,0,0.92)",
                backdropFilter: "blur(12px)",
                display: "flex",
                flexDirection: "column",
                padding: "1rem",
              }}
            >
              <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "0.75rem" }}>
                <span style={{ color: "#ffffff", fontWeight: 600 }}>Proof Inspection #{file.id}</span>
                <div style={{ display: "flex", gap: "0.5rem" }}>
                  {!isPdf && (
                    <>
                      <button type="button" className="sec" onClick={() => setZoom((z) => Math.min(4, +(z + 0.25).toFixed(2)))}>+ Zoom</button>
                      <button type="button" className="sec" onClick={() => setZoom((z) => Math.max(0.5, +(z - 0.25).toFixed(2)))}>- Zoom</button>
                      <button type="button" className="sec" onClick={rotateClockwise}>Rotate</button>
                      <button type="button" className="sec" onClick={resetView}>Reset</button>
                    </>
                  )}
                  <button
                    type="button"
                    style={{ background: "#ffffff", color: "#000000", fontWeight: "bold" }}
                    onClick={() => setIsModalOpen(false)}
                  >
                    Close (Esc)
                  </button>
                </div>
              </div>
              <div
                style={{
                  flex: 1,
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "center",
                  overflow: "auto",
                }}
              >
                {isPdf ? (
                  <iframe src={f.url} title="Proof Lightbox" style={{ width: "95%", height: "90%", border: "none" }} />
                ) : (
                  // eslint-disable-next-line @next/next/no-img-element
                  <img
                    src={f.url}
                    alt="Proof Lightbox"
                    style={{
                      transform: `scale(${zoom}) rotate(${rotation}deg)`,
                      transformOrigin: "center center",
                      transition: "transform 0.15s ease-out",
                      maxWidth: zoom <= 1 ? "90vw" : "none",
                      maxHeight: zoom <= 1 ? "85vh" : "none",
                    }}
                  />
                )}
              </div>
            </div>
          )}
        </>
      )}
    </div>
  );
}

export default function ClaimDetail() {
  const { id } = useParams<{ id: string }>();
  const router = useRouter();
  const role = session.role ?? "";
  const canDecide = ["reviewer", "finance_admin", "super_admin"].includes(role);
  const canOverride = ["finance_admin", "super_admin"].includes(role);

  const [d, setD] = useState<any>(null);
  const [err, setErr] = useState("");
  const [msg, setMsg] = useState("");
  const [busy, setBusy] = useState(false);

  // Decision state
  const [pick, setPick] = useState<number | null>(null);
  const [reason, setReason] = useState("");
  const [just, setJust] = useState("");

  // Rejection Dialog State
  const [showRejectModal, setShowRejectModal] = useState(false);
  const [selectedRejectReason, setSelectedRejectReason] = useState(REJECTION_REASONS[0].code);
  const [customerRejectMsg, setCustomerRejectMsg] = useState(REJECTION_REASONS[0].customerNotice);
  const [internalRejectNote, setInternalRejectNote] = useState("");

  const load = useCallback(() => {
    return api(`/api/v1/admin/claims/${id}`)
      .then(setD)
      .catch((e) => setErr(e.message));
  }, [id]);

  useEffect(() => {
    load();
  }, [load]);

  function handleSelectRejectReason(code: string) {
    setSelectedRejectReason(code);
    const item = REJECTION_REASONS.find((r) => r.code === code);
    if (item) {
      setCustomerRejectMsg(item.customerNotice);
    }
  }

  async function executeRejection() {
    setErr("");
    setMsg("");
    setBusy(true);
    try {
      await api(`/api/v1/admin/claims/${id}/decision`, {
        json: {
          decision: "reject",
          reason: internalRejectNote || `Rejected: ${selectedRejectReason}`,
          rejection_code: selectedRejectReason,
          customer_message: customerRejectMsg,
        },
      });
      setMsg("Payment claim rejected. Customer notification recorded.");
      setShowRejectModal(false);
      await load();
    } catch (x: any) {
      setErr(x.message);
    } finally {
      setBusy(false);
    }
  }

  async function quickVerify() {
    if (!confirm("Are you sure you want to verify this payment now?")) return;
    setErr("");
    setMsg("");
    setBusy(true);
    try {
      await api(`/api/v1/admin/claims/${id}/quick-verify`, {
        json: { reason: reason || "Manual verification confirmed by staff" },
      });
      setMsg("Payment manually verified successfully.");
      await load();
    } catch (x: any) {
      setErr(x.message);
    } finally {
      setBusy(false);
    }
  }

  async function approveWithCredit() {
    if (!pick) return;
    setErr("");
    setMsg("");
    setBusy(true);
    try {
      await api(`/api/v1/admin/claims/${id}/decision`, {
        json: {
          decision: "approve",
          bank_transaction_id: pick,
          reason: reason || "Approved and linked to matching Commercial Bank credit",
        },
      });
      setMsg("Payment approved and allocated to bank transaction.");
      await load();
    } catch (x: any) {
      setErr(x.message);
    } finally {
      setBusy(false);
    }
  }

  async function approveByOverride() {
    if (just.trim().length < 20) {
      setErr("Override justification must be at least 20 characters.");
      return;
    }
    setErr("");
    setMsg("");
    setBusy(true);
    try {
      await api(`/api/v1/admin/claims/${id}/decision`, {
        json: {
          decision: "approve",
          reason: reason || "Finance admin override",
          override_justification: just,
        },
      });
      setMsg("Payment approved via manual administrative override. Cryptographic audit entry saved.");
      await load();
    } catch (x: any) {
      setErr(x.message);
    } finally {
      setBusy(false);
    }
  }

  if (!d) {
    return err ? <p className="error" role="alert">{err}</p> : <p className="muted">Loading payment details…</p>;
  }

  const c = d.claim;
  const order = d.order;
  const ai = d.ai_analyses?.[0]?.extracted;
  const open = !FINAL_STATUSES.includes(c.status);
  const rAmt = ai?.amount ? toMinor(ai.amount) : null;
  const hasAmountMismatch = rAmt != null && rAmt !== order.expected_pay_amount_minor;

  return (
    <div className="stack" style={{ gap: "1.75rem" }}>
      {/* Top Header Bar */}
      <div style={{ display: "flex", flexWrap: "wrap", alignItems: "center", justifyContent: "space-between", gap: "1rem" }}>
        <div style={{ display: "flex", alignItems: "center", gap: "1rem" }}>
          <button
            type="button"
            className="sec"
            onClick={() => router.push("/admin/claims")}
            style={{ padding: "0.3rem 0.75rem" }}
          >
            ← Back
          </button>
          <h1 style={{ margin: 0, fontSize: "1.6rem" }}>Payment Claim #{c.id}</h1>
          <StatusBadge status={c.status} />
          {c.basis && (
            <span className="badge" style={{ background: "rgba(255,255,255,0.06)", border: "1px solid rgba(255,255,255,0.12)", color: "#a1a1aa" }}>
              Basis: {c.basis}
            </span>
          )}
        </div>

        {open && canDecide && (
          <div style={{ display: "flex", alignItems: "center", gap: "0.75rem" }}>
            <button
              type="button"
              className="danger"
              disabled={busy}
              onClick={() => setShowRejectModal(true)}
              style={{ padding: "0.55rem 1.25rem" }}
            >
              ✕ Reject Payment
            </button>
            <button
              type="button"
              disabled={busy}
              onClick={quickVerify}
              style={{
                backgroundColor: "#ffffff",
                color: "#000000",
                fontWeight: 700,
                padding: "0.55rem 1.4rem",
              }}
            >
              {busy ? "Verifying…" : "✓ Quick Verify"}
            </button>
          </div>
        )}
      </div>

      {msg && <p className="okmsg" role="status">{msg}</p>}
      {err && <p className="error" role="alert">{err}</p>}

      {/* Main Two-Column Layout: Proof vs Verification Matrix */}
      <div className="grid2" style={{ alignItems: "start" }}>
        {/* Left Column: Proof Viewer & Forensics */}
        <div className="stack" style={{ gap: "1.5rem" }}>
          <section className="glass panel stack">
            <h2 style={{ margin: "0 0 1rem 0" }}>Payment Proof Documents</h2>
            {d.files.length === 0 ? (
              <p className="muted">No proof documents uploaded for this claim.</p>
            ) : (
              d.files.map((file: any) => <ProofViewer key={file.id} file={file} />)
            )}
          </section>

          {/* Forensics & Anti-Fraud Flags */}
          <section className="glass panel stack">
            <h3 style={{ margin: "0 0 0.75rem 0", fontSize: "1.1rem" }}>Forensics & Fraud Evaluation</h3>
            {d.fraud_signals.length === 0 ? (
              <div style={{ display: "flex", alignItems: "center", gap: "0.5rem" }}>
                <span style={{ color: "#ffffff", fontSize: "1.2rem" }}>✓</span>
                <span className="muted">No fraud flags or anomalies detected.</span>
              </div>
            ) : (
              d.fraud_signals.map((s: any, i: number) => (
                <div
                  key={i}
                  style={{
                    display: "flex",
                    alignItems: "flex-start",
                    gap: "0.75rem",
                    padding: "0.6rem 0.8rem",
                    borderRadius: "6px",
                    background: s.severity === "block" ? "rgba(220, 38, 38, 0.1)" : "rgba(255, 255, 255, 0.04)",
                    border: `1px solid ${s.severity === "block" ? "rgba(220, 38, 38, 0.3)" : "rgba(255, 255, 255, 0.08)"}`,
                  }}
                >
                  <span className={`s-${s.severity}`} style={{ textTransform: "uppercase", fontSize: "0.75rem", fontWeight: 700, padding: "0.15rem 0.4rem", borderRadius: "4px" }}>
                    {s.severity}
                  </span>
                  <div>
                    <strong style={{ display: "block", color: "#f4f4f5", fontSize: "0.9rem" }}>{s.rule.replace(/_/g, " ")}</strong>
                    {s.detail && <span className="muted" style={{ fontSize: "0.8rem" }}>{typeof s.detail === "string" ? s.detail : JSON.stringify(s.detail)}</span>}
                  </div>
                </div>
              ))
            )}
          </section>
        </div>

        {/* Right Column: 4-Column Discrepancy Matrix & Customer Info */}
        <div className="stack" style={{ gap: "1.5rem" }}>
          {/* Side-by-Side Verification Matrix */}
          <section className="glass panel stack">
            <h2 style={{ margin: "0 0 1rem 0" }}>Discrepancy Matrix</h2>
            <div style={{ overflowX: "auto" }}>
              <table style={{ width: "100%", fontSize: "0.88rem" }}>
                <thead>
                  <tr>
                    <th style={{ width: "22%" }}>Field</th>
                    <th style={{ width: "26%" }}>Order Expected</th>
                    <th style={{ width: "26%" }}>Customer Claimed</th>
                    <th style={{ width: "26%" }}>AI Vision Read</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td><strong>Amount</strong></td>
                    <td><strong className="mono">{money(order.expected_pay_amount_minor)}</strong></td>
                    <td className={c.claimed.amount_minor !== order.expected_pay_amount_minor ? "diff" : ""}>
                      <span className="mono">{money(c.claimed.amount_minor)}</span>
                    </td>
                    <td className={hasAmountMismatch ? "diff" : ""}>
                      <span className="mono">{rAmt == null ? "-" : money(rAmt)}</span>
                    </td>
                  </tr>
                  <tr>
                    <td><strong>Payment Code</strong></td>
                    <td><span className="ref">{order.payment_code}</span></td>
                    <td><span className="ref">{c.claimed.reference ?? "-"}</span></td>
                    <td><span className="ref">{ai?.reference ?? "-"}</span></td>
                  </tr>
                  <tr>
                    <td><strong>Date / Time</strong></td>
                    <td className="muted">-</td>
                    <td>{when(c.claimed.paid_at)}</td>
                    <td>{ai?.datetime ?? "-"}</td>
                  </tr>
                  <tr>
                    <td><strong>Sender / Remarks</strong></td>
                    <td className="muted">{order.invoice_no}</td>
                    <td>{c.claimed.sender ?? "-"}</td>
                    <td>{ai?.sender_name ?? "-"}</td>
                  </tr>
                </tbody>
              </table>
            </div>

            {d.ai_analyses?.[0] && (
              <p className="muted" style={{ fontSize: "0.8rem", margin: "0.5rem 0 0 0" }}>
                AI Model: {d.ai_analyses[0].model || "Vision"} ({Math.round((d.ai_analyses[0].confidence || 0) * 100)}% extraction confidence). Per Invariant 2, AI reading is strictly supporting evidence and never settles a payment.
              </p>
            )}
          </section>

          {/* Customer & Order Profile */}
          <section className="glass panel stack">
            <h3 style={{ margin: "0 0 0.75rem 0", fontSize: "1.1rem" }}>Order & Customer Profile</h3>
            <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(180px, 1fr))", gap: "1rem" }}>
              <div>
                <span className="muted" style={{ display: "block", fontSize: "0.8rem" }}>Invoice No</span>
                <strong style={{ fontSize: "1rem" }}>{order.invoice_no}</strong>
              </div>
              <div>
                <span className="muted" style={{ display: "block", fontSize: "0.8rem" }}>Customer Name</span>
                <strong>{d.customer?.name || "Guest Customer"}</strong>
              </div>
              <div>
                <span className="muted" style={{ display: "block", fontSize: "0.8rem" }}>Customer Email</span>
                <span className="mono" style={{ fontSize: "0.85rem" }}>{d.customer?.email || "-"}</span>
              </div>
              <div>
                <span className="muted" style={{ display: "block", fontSize: "0.8rem" }}>Customer Phone</span>
                <span className="mono" style={{ fontSize: "0.85rem" }}>{d.customer?.phone || "-"}</span>
              </div>
            </div>
          </section>

          {/* Bank Ledger Candidate Matches */}
          <section className="glass panel stack">
            <h3 style={{ margin: "0 0 0.75rem 0", fontSize: "1.1rem" }}>Commercial Bank Ledger Evidence</h3>
            {d.allocated_bank_transaction_id ? (
              <div style={{ padding: "0.75rem", background: "rgba(255,255,255,0.05)", borderRadius: "6px", border: "1px solid rgba(255,255,255,0.12)" }}>
                <span style={{ color: "#ffffff", fontWeight: 600 }}>Matched & Allocated: </span>
                <span>Bank Transaction #{d.allocated_bank_transaction_id}</span>
              </div>
            ) : d.candidate_bank_transactions.length === 0 ? (
              <p className="muted" style={{ margin: 0 }}>
                No unallocated credit of {money(order.expected_pay_amount_minor)} found within the ±3 days window. Sync the bank statement or check the WhatsApp Bot feed.
              </p>
            ) : (
              <div style={{ overflowX: "auto" }}>
                <table style={{ width: "100%", fontSize: "0.85rem" }}>
                  <thead>
                    <tr>
                      {open && canDecide && <th style={{ width: "36px" }} />}
                      <th>Date</th>
                      <th>Amount</th>
                      <th>Bank Ref / STAN</th>
                      <th>Remarks</th>
                    </tr>
                  </thead>
                  <tbody>
                    {d.candidate_bank_transactions.map((t: any) => (
                      <tr key={t.id} style={{ background: pick === t.id ? "rgba(255,255,255,0.06)" : undefined }}>
                        {open && canDecide && (
                          <td>
                            <input
                              type="radio"
                              name="bank_candidate"
                              checked={pick === t.id}
                              onChange={() => setPick(t.id)}
                              style={{ width: "auto", cursor: "pointer" }}
                            />
                          </td>
                        )}
                        <td>{when(t.value_dt)}</td>
                        <td className="mono"><strong>{money(t.amount_minor)}</strong></td>
                        <td className="ref">{t.ref ?? "-"}</td>
                        <td>{t.remarks}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </section>

          {/* Staff Decision Section */}
          {open && canDecide && (
            <section className="glass panel stack" style={{ border: "1px solid rgba(255,255,255,0.18)" }}>
              <h3 style={{ margin: 0, fontSize: "1.15rem" }}>Manual Review Decision</h3>
              <div className="field">
                <label htmlFor="reason_note">Audit Log Note / Justification</label>
                <textarea
                  id="reason_note"
                  placeholder="Optional internal review note (saved in the tamper-evident cryptographic audit ledger)..."
                  value={reason}
                  onChange={(e) => setReason(e.target.value)}
                  style={{ minHeight: "60px" }}
                />
              </div>

              <div style={{ display: "flex", flexWrap: "wrap", gap: "0.75rem", alignItems: "center" }}>
                <button
                  type="button"
                  disabled={busy || pick == null}
                  onClick={approveWithCredit}
                  style={{ backgroundColor: "#ffffff", color: "#000000", fontWeight: 600 }}
                >
                  Approve with Selected Bank Credit
                </button>
                <button
                  type="button"
                  className="danger"
                  disabled={busy}
                  onClick={() => setShowRejectModal(true)}
                >
                  Reject Claim…
                </button>
              </div>

              {/* Finance Admin Manual Override Accordion */}
              {canOverride && (
                <details style={{ marginTop: "1rem", borderTop: "1px solid rgba(255,255,255,0.08)", paddingTop: "0.75rem" }}>
                  <summary style={{ cursor: "pointer", color: "#e4e4e7", fontWeight: 600 }}>
                    Approve by Finance Staff Override (Without Bank Credit)
                  </summary>
                  <p className="muted" style={{ fontSize: "0.82rem", margin: "0.5rem 0" }}>
                    Only use this if you verified the deposit in branch, via ATM terminal slip, or external ledger. A mandatory 20+ character justification is permanently sealed in the audit chain.
                  </p>
                  <div className="field">
                    <label htmlFor="override_just">Mandatory Written Justification (20+ chars)</label>
                    <textarea
                      id="override_just"
                      placeholder="e.g. Verified deposit directly on branch CRM terminal receipt #032699400..."
                      value={just}
                      onChange={(e) => setJust(e.target.value)}
                      style={{ minHeight: "70px" }}
                    />
                  </div>
                  <button
                    type="button"
                    className="sec"
                    disabled={busy || just.trim().length < 20}
                    onClick={approveByOverride}
                  >
                    Confirm Administrative Override
                  </button>
                </details>
              )}
            </section>
          )}
        </div>
      </div>

      {/* Cryptographic Audit Ledger History */}
      <section className="glass panel stack">
        <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between" }}>
          <div>
            <h2 style={{ margin: 0 }}>Cryptographic Audit Ledger</h2>
            <p className="muted" style={{ fontSize: "0.85rem", margin: "0.25rem 0 0 0" }}>
              Tamper-evident SHA-256 blockchain hash chain (Invariant 4). Every change is sealed permanently.
            </p>
          </div>
          <span className="badge" style={{ background: "rgba(255,255,255,0.06)", border: "1px solid rgba(255,255,255,0.12)", color: "#ffffff" }}>
            Hash-Chained
          </span>
        </div>

        {d.audit_trail?.length === 0 ? (
          <p className="muted">No audit entries recorded yet.</p>
        ) : (
          <div style={{ overflowX: "auto" }}>
            <table style={{ width: "100%", fontSize: "0.85rem" }}>
              <thead>
                <tr>
                  <th>Timestamp</th>
                  <th>Action</th>
                  <th>Actor</th>
                  <th>Detail / Note</th>
                  <th>Hash Self</th>
                  <th>Hash Prev</th>
                </tr>
              </thead>
              <tbody>
                {d.audit_trail?.map((log: any) => (
                  <tr key={log.id}>
                    <td className="mono" style={{ whiteSpace: "nowrap" }}>{when(log.ts)}</td>
                    <td><strong className="mono">{log.action}</strong></td>
                    <td>{log.actor_type}: #{log.actor_id || "system"}</td>
                    <td style={{ maxWidth: "260px", overflow: "hidden", textOverflow: "ellipsis", whiteSpace: "nowrap" }}>
                      {log.detail ? JSON.stringify(log.detail) : "-"}
                    </td>
                    <td className="mono" style={{ fontSize: "0.75rem", color: "#a1a1aa" }}>
                      {log.hash_self?.slice(0, 10)}…
                    </td>
                    <td className="mono" style={{ fontSize: "0.75rem", color: "#71717a" }}>
                      {log.hash_prev?.slice(0, 10)}…
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </section>

      {/* Structured Rejection Modal */}
      {showRejectModal && (
        <div
          style={{
            position: "fixed",
            inset: 0,
            zIndex: 9999,
            background: "rgba(0,0,0,0.85)",
            backdropFilter: "blur(12px)",
            display: "flex",
            alignItems: "center",
            justifyContent: "center",
            padding: "1rem",
          }}
        >
          <div
            className="glass panel stack"
            style={{
              width: "100%",
              maxWidth: "520px",
              background: "#0a0a0a",
              border: "1px solid rgba(255,255,255,0.18)",
              padding: "1.75rem",
              borderRadius: "12px",
            }}
          >
            <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center" }}>
              <h2 style={{ margin: 0, fontSize: "1.3rem" }}>Reject Payment Claim</h2>
              <button
                type="button"
                className="sec"
                onClick={() => setShowRejectModal(false)}
                style={{ padding: "0.2rem 0.5rem" }}
              >
                ✕
              </button>
            </div>

            <p className="muted" style={{ fontSize: "0.85rem", margin: 0 }}>
              Select a standardized reason code and review the customer notification message. The customer can submit a new slip.
            </p>

            <div className="field">
              <label htmlFor="reject_reason_select">Rejection Reason Code</label>
              <select
                id="reject_reason_select"
                value={selectedRejectReason}
                onChange={(e) => handleSelectRejectReason(e.target.value)}
                style={{
                  width: "100%",
                  padding: "0.55rem 0.75rem",
                  background: "rgba(255,255,255,0.05)",
                  color: "#ffffff",
                  border: "1px solid rgba(255,255,255,0.15)",
                  borderRadius: "6px",
                }}
              >
                {REJECTION_REASONS.map((r) => (
                  <option key={r.code} value={r.code} style={{ background: "#111", color: "#fff" }}>
                    {r.title}
                  </option>
                ))}
              </select>
            </div>

            <div className="field">
              <label htmlFor="customer_msg">Customer Facing Message</label>
              <textarea
                id="customer_msg"
                value={customerRejectMsg}
                onChange={(e) => setCustomerRejectMsg(e.target.value)}
                rows={3}
                style={{ width: "100%", resize: "vertical" }}
              />
            </div>

            <div className="field">
              <label htmlFor="internal_note">Internal Staff Audit Note</label>
              <input
                id="internal_note"
                type="text"
                placeholder="Optional internal justification for audit trail..."
                value={internalRejectNote}
                onChange={(e) => setInternalRejectNote(e.target.value)}
                style={{ width: "100%" }}
              />
            </div>

            <div style={{ display: "flex", justifyContent: "flex-end", gap: "0.75rem", marginTop: "0.5rem" }}>
              <button
                type="button"
                className="sec"
                onClick={() => setShowRejectModal(false)}
                disabled={busy}
              >
                Cancel
              </button>
              <button
                type="button"
                className="danger"
                onClick={executeRejection}
                disabled={busy}
                style={{ fontWeight: 600 }}
              >
                {busy ? "Rejecting…" : "Confirm Rejection"}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
