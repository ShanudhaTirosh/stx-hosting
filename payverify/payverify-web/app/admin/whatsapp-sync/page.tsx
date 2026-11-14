"use client";
import { useCallback, useEffect, useState } from "react";
import { api, session } from "@/lib/api";
import { when } from "@/lib/format";

export default function WhatsAppSyncPage() {
  const canSync = ["finance_admin", "super_admin"].includes(session.role ?? "");
  const [data, setData] = useState<any>(null);
  const [qrData, setQrData] = useState<{ connected: boolean; qr: string | null; qr_image?: string | null } | null>(null);
  const [err, setErr] = useState("");
  const [syncResult, setSyncResult] = useState<any>(null);
  const [busy, setBusy] = useState(false);

  // Pairing Code states
  const [pairingMethod, setPairingMethod] = useState<"qr" | "code">("code");
  const [phoneNumber, setPhoneNumber] = useState("+94");
  const [pairingCode, setPairingCode] = useState<string | null>(null);
  const [pairingLoading, setPairingLoading] = useState(false);
  const [pairingErr, setPairingErr] = useState("");
  const [copied, setCopied] = useState(false);

  const loadStatus = useCallback(async () => {
    try {
      setErr("");
      const res = await api("/api/v1/bank-sync/status");
      setData(res);
      if (!res.bot?.connected && canSync) {
        const qrRes = await api("/api/v1/bank-sync/qr");
        setQrData(qrRes);
      } else {
        setQrData(null);
      }
    } catch (e: any) {
      setErr(e.message);
    }
  }, [canSync]);

  useEffect(() => {
    loadStatus();
    const timer = setInterval(loadStatus, 10000);
    return () => clearInterval(timer);
  }, [loadStatus]);

  async function triggerSync() {
    setErr("");
    setSyncResult(null);
    setBusy(true);
    try {
      const res = await api("/api/v1/bank-sync/trigger", { method: "POST" });
      setSyncResult(res);
      await loadStatus();
    } catch (e: any) {
      setErr(e.message);
    } finally {
      setBusy(false);
    }
  }

  async function handleRequestPairingCode(e: React.FormEvent) {
    e.preventDefault();
    setPairingErr("");
    setPairingCode(null);
    setPairingLoading(true);
    try {
      const res = await api("/api/v1/bank-sync/pairing-code", {
        method: "POST",
        json: { phone_number: phoneNumber.trim() },
      });
      if (res.code) {
        setPairingCode(res.code);
      } else {
        setPairingErr("Failed to receive pairing code from WhatsApp service.");
      }
    } catch (e: any) {
      setPairingErr(e.message || "Failed to request pairing code.");
    } finally {
      setPairingLoading(false);
    }
  }

  function handleCopyCode() {
    if (!pairingCode) return;
    navigator.clipboard.writeText(pairingCode.replace("-", ""));
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  }

  const isConnected = data?.bot?.connected;
  const isAwaitingOtp = data?.bot?.awaitingOtp;
  const state = data?.bot?.state || "UNKNOWN";

  return (
    <div className="stack">
      <div className="flex-between">
        <div>
          <h1>Commercial Bank WhatsApp Banking</h1>
          <p className="muted">
            Automated hands-free statement retrieval and credit reconciliation via Commercial Bank official WhatsApp Banking.
          </p>
        </div>
        <div>
          <button className="ghost" onClick={loadStatus} disabled={busy}>
            Refresh Status
          </button>
        </div>
      </div>

      {err && <div className="error" role="alert">{err}</div>}

      {/* Connection & Telemetry Status Panel */}
      <section className="glass panel">
        <div className="flex-between">
          <h2>Bot Telemetry & Connection</h2>
          <span
            className="badge"
            style={{
              backgroundColor: isConnected ? "rgba(52, 211, 153, 0.12)" : isAwaitingOtp ? "rgba(251, 191, 36, 0.12)" : "rgba(255, 255, 255, 0.08)",
              color: isConnected ? "#34d399" : isAwaitingOtp ? "#fbbf24" : "#ffffff",
              border: `1px solid ${isConnected ? "rgba(52, 211, 153, 0.3)" : isAwaitingOtp ? "rgba(251, 191, 36, 0.3)" : "rgba(255, 255, 255, 0.2)"}`
            }}
          >
            {isConnected ? "CONNECTED" : isAwaitingOtp ? "AWAITING SMS OTP" : "NOT PAIRED"}
          </span>
        </div>

        <div className="grid3" style={{ marginTop: "1.2rem" }}>
          <div className="tile">
            <p>Session State</p>
            <div className="num" style={{ fontSize: "1.25rem", marginTop: "0.25rem" }}>
              <code>{state}</code>
            </div>
          </div>
          <div className="tile">
            <p>Last Bank Sync</p>
            <div className="num" style={{ fontSize: "1.25rem", marginTop: "0.25rem" }}>
              {data?.bot?.lastSyncAt ? when(data.bot.lastSyncAt) : "Never"}
            </div>
          </div>
          <div className="tile">
            <p>Background Cadence</p>
            <div className="num" style={{ fontSize: "1.25rem", marginTop: "0.25rem" }}>
              Every 1 Hour
            </div>
          </div>
        </div>

        {/* Device Pairing Section (Shown when bot is disconnected) */}
        {!isConnected && (
          <div
            style={{
              marginTop: "1.6rem",
              padding: "1.4rem",
              backgroundColor: "rgba(0, 0, 0, 0.4)",
              borderRadius: "14px",
              border: "1px solid var(--line-strong)"
            }}
          >
            <div className="flex-between">
              <div>
                <h3 style={{ margin: 0 }}>Pair WhatsApp Banking Device</h3>
                <p className="muted" style={{ fontSize: "0.88rem" }}>
                  Connect the phone number registered with Commercial Bank of Ceylon.
                </p>
              </div>

              {/* Method Switcher Chips */}
              <div className="chips">
                <button
                  type="button"
                  className={`chip ${pairingMethod === "code" ? "on" : ""}`}
                  onClick={() => setPairingMethod("code")}
                >
                  Pairing Code (Phone Number)
                </button>
                <button
                  type="button"
                  className={`chip ${pairingMethod === "qr" ? "on" : ""}`}
                  onClick={() => setPairingMethod("qr")}
                >
                  Scan QR Code
                </button>
              </div>
            </div>

            {/* Method A: 8-Digit Pairing Code */}
            {pairingMethod === "code" && (
              <div style={{ marginTop: "1.4rem" }}>
                <form onSubmit={handleRequestPairingCode} style={{ maxWidth: "460px" }}>
                  <label htmlFor="wa-phone">Merchant Bank-Registered WhatsApp Number</label>
                  <div style={{ display: "flex", gap: "0.6rem", marginTop: "0.3rem" }}>
                    <input
                      id="wa-phone"
                      type="text"
                      placeholder="+94771234567"
                      value={phoneNumber}
                      onChange={(e) => setPhoneNumber(e.target.value)}
                      required
                      style={{ flex: 1 }}
                    />
                    <button type="submit" disabled={pairingLoading || phoneNumber.length < 8}>
                      {pairingLoading ? "Generating…" : "Get Code"}
                    </button>
                  </div>
                </form>

                {pairingErr && <div className="error" style={{ maxWidth: "560px" }}>{pairingErr}</div>}

                {pairingCode && (
                  <div
                    style={{
                      marginTop: "1.4rem",
                      padding: "1.2rem 1.4rem",
                      backgroundColor: "rgba(255, 255, 255, 0.05)",
                      borderRadius: "12px",
                      border: "1px solid rgba(255, 255, 255, 0.15)",
                      display: "inline-block"
                    }}
                  >
                    <div className="muted" style={{ fontSize: "0.82rem", textTransform: "uppercase", letterSpacing: "0.06em" }}>
                      WhatsApp Pairing Code
                    </div>
                    <div style={{ display: "flex", alignItems: "center", gap: "1rem", marginTop: "0.4rem" }}>
                      <span
                        style={{
                          fontFamily: "var(--mono)",
                          fontSize: "2rem",
                          fontWeight: 700,
                          letterSpacing: "0.15em",
                          color: "#ffffff"
                        }}
                      >
                        {pairingCode}
                      </span>
                      <button type="button" className="ghost" onClick={handleCopyCode} style={{ padding: "0.45rem 0.9rem" }}>
                        {copied ? "Copied" : "Copy Code"}
                      </button>
                    </div>

                    <div style={{ marginTop: "1rem", fontSize: "0.88rem", color: "var(--text-body)" }}>
                      <strong>How to enter this code on your phone:</strong>
                      <ol style={{ paddingLeft: "1.2rem", margin: "0.4rem 0 0" }}>
                        <li>Open WhatsApp on the merchant phone registered with Commercial Bank.</li>
                        <li>Tap <strong>Settings</strong> (or Menu) &gt; <strong>Linked Devices</strong>.</li>
                        <li>Tap <strong>Link a Device</strong>.</li>
                        <li>Tap <strong>Link with phone number instead</strong> at the bottom of the screen.</li>
                        <li>Type the 8-character code shown above.</li>
                      </ol>
                    </div>
                  </div>
                )}
              </div>
            )}

            {/* Method B: QR Code Scanner */}
            {pairingMethod === "qr" && (
              <div style={{ marginTop: "1.4rem" }}>
                <p className="muted" style={{ fontSize: "0.9rem" }}>
                  Scan this code using the WhatsApp application on the bank-registered phone:
                </p>
                <ol className="muted" style={{ paddingLeft: "1.2rem", margin: "0.5rem 0 1rem" }}>
                  <li>Open WhatsApp on the bank-registered phone.</li>
                  <li>Tap <strong>Settings</strong> or <strong>Menu</strong> &gt; <strong>Linked Devices</strong>.</li>
                  <li>Tap <strong>Link a Device</strong> and point your camera at the QR code below.</li>
                </ol>

                {qrData?.qr_image ? (
                  <div
                    style={{
                      padding: "1rem",
                      backgroundColor: "#ffffff",
                      display: "inline-block",
                      borderRadius: "14px",
                      boxShadow: "0 8px 30px rgba(0, 0, 0, 0.6)"
                    }}
                  >
                    {/* eslint-disable-next-line @next/next/no-img-element */}
                    <img
                      src={qrData.qr_image}
                      alt="WhatsApp Linking QR Code"
                      style={{ width: "240px", height: "240px", display: "block" }}
                    />
                  </div>
                ) : qrData?.qr ? (
                  <div style={{ padding: "1rem", backgroundColor: "#fff", display: "inline-block", borderRadius: "8px" }}>
                    <pre style={{ color: "#000", margin: 0, fontSize: "8px", lineHeight: "8px", fontFamily: "monospace" }}>
                      {qrData.qr}
                    </pre>
                  </div>
                ) : (
                  <div className="muted" style={{ fontStyle: "italic" }}>
                    Waiting for WhatsApp service to emit QR code…
                  </div>
                )}
              </div>
            )}
          </div>
        )}

        {/* Sync Trigger Controls */}
        {canSync && (
          <div style={{ marginTop: "1.8rem" }}>
            <button onClick={triggerSync} disabled={busy || !isConnected}>
              {busy ? "Syncing Statement with ComBank WhatsApp…" : "Sync Bank History Now"}
            </button>
            <span className="muted" style={{ marginLeft: "1.2rem", fontSize: "0.88rem" }}>
              Queries Commercial Bank WhatsApp Banking, intercepts SMS OTP via merchant phone, and reconciles pending payments.
            </span>
          </div>
        )}

        {syncResult && (
          <div className="okmsg" role="status" style={{ marginTop: "1.2rem" }}>
            Sync completed successfully! Retrieved {syncResult.count} transactions from Commercial Bank WhatsApp Banking.
          </div>
        )}
      </section>

      {/* Recent WhatsApp Statement Imports */}
      <section className="glass panel scroll">
        <h2>Recent WhatsApp Statements Ingested</h2>
        {!data ? (
          <p className="muted">Loading recent sync history…</p>
        ) : data.recent_imports?.length === 0 ? (
          <p className="muted">No WhatsApp statements imported yet. Click &apos;Sync Bank History Now&apos; above.</p>
        ) : (
          <table>
            <thead>
              <tr>
                <th>Import ID</th>
                <th>Import Date</th>
                <th>New Transactions</th>
                <th>Total Rows</th>
              </tr>
            </thead>
            <tbody>
              {data.recent_imports.map((imp: any) => (
                <tr key={imp.id}>
                  <td><span className="ref">#{imp.id}</span></td>
                  <td>{when(imp.created_at)}</td>
                  <td>
                    <span style={{ color: imp.rows_new > 0 ? "#34d399" : "inherit", fontWeight: 600 }}>
                      +{imp.rows_new}
                    </span>
                  </td>
                  <td>{imp.rows_total}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </section>
    </div>
  );
}
