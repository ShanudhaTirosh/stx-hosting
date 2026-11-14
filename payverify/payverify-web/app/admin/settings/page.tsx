"use client";
import { useEffect, useState } from "react";
import Link from "next/link";
import { api, session } from "@/lib/api";
import { when } from "@/lib/format";

export default function SettingsPage() {
  const isFinance = ["finance_admin", "super_admin"].includes(session.role ?? "");
  const [data, setData] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [err, setErr] = useState("");
  const [msg, setMsg] = useState("");

  // Reveal Modal State
  const [showRevealModal, setShowRevealModal] = useState(false);
  const [revealPassword, setRevealPassword] = useState("");
  const [revealedData, setRevealedData] = useState<{ account_number: string; id_number: string } | null>(null);
  const [revealLoading, setRevealLoading] = useState(false);
  const [revealErr, setRevealErr] = useState("");
  const [revealTimer, setRevealTimer] = useState<number | null>(null);

  // Edit Mode State
  const [editing, setEditing] = useState(false);
  const [form, setForm] = useState({
    full_name: "",
    bank_name: "",
    branch_name: "",
    account_number: "",
    id_number: "",
    mobile_number: "",
    combank_whatsapp_number: "",
    monitored_senders: "",
  });
  const [saving, setSaving] = useState(false);

  async function loadSettings() {
    try {
      setLoading(true);
      setErr("");
      const res = await api("/api/v1/admin/settings/account");
      setData(res);
      setForm({
        full_name: res.full_name || "",
        bank_name: res.bank_name || "",
        branch_name: res.branch_name || "",
        account_number: "",
        id_number: "",
        mobile_number: res.mobile_number || "",
        combank_whatsapp_number: res.combank_whatsapp_number || "",
        monitored_senders: (res.monitored_senders || []).join(", "),
      });
    } catch (e: any) {
      setErr(e.message || "Could not load account settings");
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    loadSettings();
  }, []);

  async function handleReveal(e: React.FormEvent) {
    e.preventDefault();
    setRevealErr("");
    setRevealLoading(true);
    try {
      const res = await api<{ account_number: string; id_number: string }>(
        "/api/v1/admin/settings/account/reveal",
        {
          method: "POST",
          json: { password: revealPassword },
        }
      );
      setRevealedData(res);
      setShowRevealModal(false);
      setRevealPassword("");
      // Auto-hide after 30 seconds for security
      setRevealTimer(30);
    } catch (e: any) {
      setRevealErr(e.message || "Re-authentication failed. Incorrect password.");
    } finally {
      setRevealLoading(false);
    }
  }

  useEffect(() => {
    if (revealTimer == null || revealTimer <= 0) {
      if (revealTimer === 0) setRevealedData(null);
      return;
    }
    const id = setInterval(() => setRevealTimer((t) => (t != null && t > 0 ? t - 1 : null)), 1000);
    return () => clearInterval(id);
  }, [revealTimer]);

  async function handleSave(e: React.FormEvent) {
    e.preventDefault();
    setSaving(true);
    setErr("");
    setMsg("");
    try {
      const payload: any = {
        full_name: form.full_name.trim(),
        bank_name: form.bank_name.trim(),
        branch_name: form.branch_name.trim(),
        mobile_number: form.mobile_number.trim(),
        combank_whatsapp_number: form.combank_whatsapp_number.trim(),
        monitored_senders: form.monitored_senders.split(",").map((s) => s.trim()).filter(Boolean),
      };
      if (form.account_number.trim()) {
        payload.account_number = form.account_number.trim();
      }
      if (form.id_number.trim()) {
        payload.id_number = form.id_number.trim();
      }

      await api("/api/v1/admin/settings/account", {
        method: "PUT",
        json: payload,
      });

      setMsg("Settings successfully updated and encrypted at rest.");
      setEditing(false);
      await loadSettings();
    } catch (e: any) {
      setErr(e.message || "Failed to update settings");
    } finally {
      setSaving(false);
    }
  }

  if (loading) {
    return (
      <div className="stack">
        <h1>Account & Banking Settings</h1>
        <p className="muted">Loading credentials...</p>
      </div>
    );
  }

  return (
    <div className="stack">
      <div className="flex-between">
        <div>
          <h1>Account & Banking Settings</h1>
          <p className="muted">
            Configure your merchant banking credentials, ComBank automation details, and monitored SMS sender IDs.
          </p>
        </div>
        {isFinance && (
          <div style={{ display: "flex", gap: "0.5rem" }}>
            {!editing ? (
              <>
                <button
                  className="btn"
                  onClick={() => {
                    if (revealedData) {
                      setRevealedData(null);
                      setRevealTimer(null);
                    } else {
                      setShowRevealModal(true);
                    }
                  }}
                >
                  {revealedData ? `Hide Secret Fields (${revealTimer}s)` : "Reveal Sensitive Fields"}
                </button>
                <button className="ghost" onClick={() => setEditing(true)}>
                  Edit Details
                </button>
              </>
            ) : (
              <button className="ghost" onClick={() => setEditing(false)}>
                Cancel
              </button>
            )}
          </div>
        )}
      </div>

      {err && <p className="error" role="alert">{err}</p>}
      {msg && <p className="ok" role="status" style={{ padding: "0.75rem", borderRadius: "8px" }}>{msg}</p>}

      {/* Main Settings Card */}
      {!editing ? (
        <section className="glass panel">
          <h2>Merchant & Bank Credentials</h2>
          <div className="grid2" style={{ marginTop: "1rem" }}>
            <div>
              <p className="muted" style={{ fontSize: "0.85rem" }}>Account Holder / Business Name</p>
              <p style={{ fontWeight: 600 }}>{data?.full_name || "—"}</p>
            </div>
            <div>
              <p className="muted" style={{ fontSize: "0.85rem" }}>Bank Name</p>
              <p style={{ fontWeight: 600 }}>{data?.bank_name || "—"}</p>
            </div>
            <div>
              <p className="muted" style={{ fontSize: "0.85rem" }}>Bank Branch</p>
              <p style={{ fontWeight: 600 }}>{data?.branch_name || "—"}</p>
            </div>
            <div>
              <p className="muted" style={{ fontSize: "0.85rem" }}>Bank Account Number</p>
              <p style={{ fontWeight: 600, fontFamily: "var(--mono)" }}>
                {revealedData?.account_number ? (
                  <span style={{ color: "var(--ok)", background: "var(--ok-bg)", padding: "2px 8px", borderRadius: "4px" }}>
                    {revealedData.account_number}
                  </span>
                ) : (
                  data?.account_number_masked || "—"
                )}
                {data?.account_number_tail && (
                  <span className="muted" style={{ marginLeft: "8px", fontSize: "0.8rem" }}>
                    (Tail: {data.account_number_tail})
                  </span>
                )}
              </p>
            </div>
            <div>
              <p className="muted" style={{ fontSize: "0.85rem" }}>NIC / Passport (For ComBank Bot)</p>
              <p style={{ fontWeight: 600, fontFamily: "var(--mono)" }}>
                {revealedData?.id_number ? (
                  <span style={{ color: "var(--ok)", background: "var(--ok-bg)", padding: "2px 8px", borderRadius: "4px" }}>
                    {revealedData.id_number}
                  </span>
                ) : (
                  data?.id_number_masked || "—"
                )}
              </p>
            </div>
            <div>
              <p className="muted" style={{ fontSize: "0.85rem" }}>Registered Mobile Number</p>
              <p style={{ fontWeight: 600 }}>{data?.mobile_number || "—"}</p>
            </div>
            <div>
              <p className="muted" style={{ fontSize: "0.85rem" }}>Commercial Bank Official WhatsApp Number</p>
              <p style={{ fontWeight: 600, fontFamily: "var(--mono)" }}>{data?.combank_whatsapp_number || "—"}</p>
            </div>
            <div>
              <p className="muted" style={{ fontSize: "0.85rem" }}>Last Updated</p>
              <p style={{ fontSize: "0.9rem" }}>{when(data?.updated_at)}</p>
            </div>
          </div>

          <div style={{ marginTop: "1.5rem", borderTop: "1px solid var(--line)", paddingTop: "1rem" }}>
            <p className="muted" style={{ fontSize: "0.85rem" }}>Monitored Senders & Phone Shortcodes</p>
            <div className="chips" style={{ marginTop: "0.5rem" }}>
              {(data?.monitored_senders || []).map((s: string) => (
                <span key={s} className="chip" style={{ fontFamily: "var(--mono)" }}>
                  {s}
                  {s === "2662265" && " (Windows Phone Link / Keypad COMBANK)"}
                </span>
              ))}
            </div>
          </div>
        </section>
      ) : (
        /* Edit Settings Form */
        <form onSubmit={handleSave} className="glass panel stack">
          <h2>Edit Merchant & Bank Credentials</h2>
          <div className="grid2">
            <div>
              <label>Account Holder / Business Name</label>
              <input
                type="text"
                value={form.full_name}
                onChange={(e) => setForm({ ...form, full_name: e.target.value })}
                required
              />
            </div>
            <div>
              <label>Bank Name</label>
              <input
                type="text"
                value={form.bank_name}
                onChange={(e) => setForm({ ...form, bank_name: e.target.value })}
                required
              />
            </div>
            <div>
              <label>Bank Branch</label>
              <input
                type="text"
                value={form.branch_name}
                onChange={(e) => setForm({ ...form, branch_name: e.target.value })}
              />
            </div>
            <div>
              <label>Bank Account Number (Leave blank to keep unchanged)</label>
              <input
                type="text"
                placeholder={data?.account_number_masked || "Enter new account number"}
                value={form.account_number}
                onChange={(e) => setForm({ ...form, account_number: e.target.value })}
              />
            </div>
            <div>
              <label>NIC / Passport Number (Leave blank to keep unchanged)</label>
              <input
                type="text"
                placeholder={data?.id_number_masked || "e.g. 199512345678 or 951234567V"}
                value={form.id_number}
                onChange={(e) => setForm({ ...form, id_number: e.target.value })}
              />
            </div>
            <div>
              <label>Merchant Mobile Number</label>
              <input
                type="text"
                value={form.mobile_number}
                onChange={(e) => setForm({ ...form, mobile_number: e.target.value })}
                placeholder="+94 77 123 4567"
              />
            </div>
            <div>
              <label>ComBank WhatsApp Number</label>
              <input
                type="text"
                value={form.combank_whatsapp_number}
                onChange={(e) => setForm({ ...form, combank_whatsapp_number: e.target.value })}
                placeholder="+94 77 123 4567"
              />
            </div>
            <div>
              <label>Monitored Senders & Phone Numbers (comma-separated)</label>
              <input
                type="text"
                value={form.monitored_senders}
                onChange={(e) => setForm({ ...form, monitored_senders: e.target.value })}
                placeholder="COMBANK, Commercial Bank, 2662265"
              />
              <span className="muted" style={{ fontSize: "0.75rem" }}>
                Tip: Include 2662265 for Windows Phone Link alerts.
              </span>
            </div>
          </div>

          <div style={{ display: "flex", gap: "0.75rem", marginTop: "1rem" }}>
            <button type="submit" className="btn" disabled={saving}>
              {saving ? "Encrypting & Saving..." : "Save Credentials"}
            </button>
            <button type="button" className="ghost" onClick={() => setEditing(false)}>
              Cancel
            </button>
          </div>
        </form>
      )}

      {/* Platform Integration & Usage Badges */}
      <section className="glass panel">
        <h2>Where Your Details Are Used in PayVerify</h2>
        <p className="muted">
          Your configured account credentials directly drive deterministic payment reconciliation and zero-leak automation.
        </p>
        <div className="grid2" style={{ marginTop: "1rem" }}>
          <div style={{ background: "var(--surface-base)", padding: "1rem", borderRadius: "8px", border: "1px solid var(--line)" }}>
            <h3 style={{ display: "flex", alignItems: "center", gap: "0.5rem" }}>
              <span style={{ color: "var(--ok)" }}>●</span> Customer Pay Page & Slips
            </h3>
            <p className="muted" style={{ fontSize: "0.85rem" }}>
              Account Number ({data?.account_number_masked}) and Bank Name are displayed to customers when generating payment instructions, and cross-referenced with OCR extracted recipient accounts.
            </p>
          </div>
          <div style={{ background: "var(--surface-base)", padding: "1rem", borderRadius: "8px", border: "1px solid var(--line)" }}>
            <h3 style={{ display: "flex", alignItems: "center", gap: "0.5rem" }}>
              <span style={{ color: "var(--ok)" }}>●</span> ComBank WhatsApp Bot
            </h3>
            <p className="muted" style={{ fontSize: "0.85rem" }}>
              Your NIC ({data?.id_number_masked}) is supplied to Commercial Bank’s WhatsApp Banking chat when requesting the last 3 statement transactions.
            </p>
          </div>
          <div style={{ background: "var(--surface-base)", padding: "1rem", borderRadius: "8px", border: "1px solid var(--line)" }}>
            <h3 style={{ display: "flex", alignItems: "center", gap: "0.5rem" }}>
              <span style={{ color: "var(--ok)" }}>●</span> Bank Ledger Reconciliation
            </h3>
            <p className="muted" style={{ fontSize: "0.85rem" }}>
              Account Tail ({data?.account_number_tail}) validates incoming WhatsApp statement exports and CSV imports before matching against customer claims.
            </p>
          </div>
          <div style={{ background: "var(--surface-base)", padding: "1rem", borderRadius: "8px", border: "1px solid var(--line)" }}>
            <h3 style={{ display: "flex", alignItems: "center", gap: "0.5rem" }}>
              <span style={{ color: "var(--ok)" }}>●</span> SMS & Notification Filtering
            </h3>
            <p className="muted" style={{ fontSize: "0.85rem" }}>
              Allowlisted sender IDs (including 2662265) ensure non-bank alerts never leave the device, preserving your zero-leak privacy guarantee.
            </p>
          </div>
        </div>
      </section>

      {/* Manual Payment Review Shortcut */}
      <section className="glass panel flex-between" style={{ alignItems: "center" }}>
        <div>
          <h2>Manual Payment Review</h2>
          <p className="muted">
            Inspect pending customer slips and allocate candidate bank credits against open orders.
          </p>
        </div>
        <Link href="/admin/claims?status=manual_review" className="btn">
          Open Review Queue
        </Link>
      </section>

      {/* Re-Authentication Modal for Secret Reveal */}
      {showRevealModal && (
        <div
          style={{
            position: "fixed",
            inset: 0,
            background: "rgba(0, 0, 0, 0.75)",
            backdropFilter: "blur(6px)",
            display: "flex",
            alignItems: "center",
            justifyContent: "center",
            zIndex: 1000,
            padding: "1rem",
          }}
        >
          <div className="glass panel" style={{ maxWidth: "440px", width: "100%" }}>
            <h2>Confirm Staff Password</h2>
            <p className="muted" style={{ fontSize: "0.85rem" }}>
              To view plaintext account numbers and NIC credentials, re-authenticate with your staff password. This action will be recorded in the audit log.
            </p>
            {revealErr && <p className="error" style={{ marginTop: "0.75rem" }}>{revealErr}</p>}
            <form onSubmit={handleReveal} style={{ marginTop: "1rem" }}>
              <label>Your Password</label>
              <input
                type="password"
                value={revealPassword}
                onChange={(e) => setRevealPassword(e.target.value)}
                placeholder="Enter password"
                required
                autoFocus
                style={{ width: "100%", marginTop: "0.25rem" }}
              />
              <div style={{ display: "flex", gap: "0.5rem", marginTop: "1.25rem", justifyContent: "flex-end" }}>
                <button
                  type="button"
                  className="ghost"
                  onClick={() => {
                    setShowRevealModal(false);
                    setRevealPassword("");
                    setRevealErr("");
                  }}
                >
                  Cancel
                </button>
                <button type="submit" className="btn" disabled={revealLoading}>
                  {revealLoading ? "Authenticating..." : "Confirm & Reveal"}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}

