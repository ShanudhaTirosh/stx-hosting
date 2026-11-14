"use client";
import Link from "next/link";
import { useEffect, useState } from "react";
import { api } from "@/lib/api";
import { money, STATUS_LABEL, when } from "@/lib/format";

const ORDER = ["manual_review", "suspicious", "awaiting_bank_confirmation", "processing", "verified", "rejected"];

export default function Overview() {
  const [d, setD] = useState<any>(null);
  const [botData, setBotData] = useState<any>(null);
  const [devices, setDevices] = useState<any[]>([]);
  const [txns, setTxns] = useState<any[]>([]);
  const [smsEvents, setSmsEvents] = useState<any[]>([]);
  const [err, setErr] = useState("");
  const [syncing, setSyncing] = useState(false);

  async function loadAll() {
    try {
      setErr("");
      const [dashRes, botRes, devRes, txnRes, smsRes] = await Promise.allSettled([
        api("/api/v1/admin/dashboard"),
        api("/api/v1/bank-sync/status"),
        api("/api/v1/admin/devices"),
        api("/api/v1/admin/bank/transactions?limit=3"),
        api("/api/v1/admin/sms-events?limit=4"),
      ]);

      if (dashRes.status === "fulfilled") setD(dashRes.value);
      if (botRes.status === "fulfilled") setBotData(botRes.value);
      if (devRes.status === "fulfilled") setDevices(devRes.value);
      if (txnRes.status === "fulfilled") setTxns(txnRes.value);
      if (smsRes.status === "fulfilled") setSmsEvents(smsRes.value);
    } catch (e: any) {
      setErr(e.message || "Failed to load dashboard data");
    }
  }

  useEffect(() => {
    loadAll();
    const timer = setInterval(loadAll, 15000);
    return () => clearInterval(timer);
  }, []);

  async function handleQuickSync() {
    setSyncing(true);
    try {
      await api("/api/v1/bank-sync/trigger", { method: "POST" });
      await loadAll();
    } catch (e: any) {
      alert(`Sync failed: ${e.message}`);
    } finally {
      setSyncing(false);
    }
  }

  if (err && !d) return <p className="error" role="alert">{err}</p>;
  if (!d) return <p className="muted">Loading dashboard…</p>;

  const n = (s: string) => d.by_status[s] ?? 0;
  const needs = n("manual_review") + n("suspicious");

  const isBotConnected = botData?.bot?.connected;
  const activeDevices = devices.filter((dev) => !dev.revoked && !dev.stale);
  const lastSyncAt = botData?.bot?.lastSyncAt || botData?.recent_imports?.[0]?.created_at;

  return (
    <div className="stack">
      <div className="flex-between">
        <div>
          <h1>Operations Overview</h1>
          <p className="muted">Real-time payment verification, ComBank WhatsApp bot, and device telemetry.</p>
        </div>
        <div style={{ display: "flex", gap: "0.5rem" }}>
          <button className="btn" onClick={handleQuickSync} disabled={syncing || !isBotConnected}>
            {syncing ? "Syncing Bank..." : "Sync WhatsApp Bank"}
          </button>
          <Link href="/admin/settings" className="ghost">
            Account Settings
          </Link>
        </div>
      </div>

      {/* Platform Health Bar */}
      <section className="glass panel" style={{ padding: "1rem" }}>
        <div className="grid3" style={{ alignItems: "center" }}>
          <div>
            <p className="muted" style={{ fontSize: "0.8rem", textTransform: "uppercase", letterSpacing: "0.05em" }}>
              ComBank WhatsApp Bot
            </p>
            <div style={{ display: "flex", alignItems: "center", gap: "0.5rem", marginTop: "0.25rem" }}>
              <span
                style={{
                  display: "inline-block",
                  width: "10px",
                  height: "10px",
                  borderRadius: "50%",
                  background: isBotConnected ? "var(--ok)" : "var(--bad)",
                }}
              />
              <span style={{ fontWeight: 600 }}>{isBotConnected ? "Connected" : "Disconnected"}</span>
              <span className="muted" style={{ fontSize: "0.8rem" }}>
                ({botData?.bot?.state || "OFFLINE"})
              </span>
            </div>
          </div>

          <div>
            <p className="muted" style={{ fontSize: "0.8rem", textTransform: "uppercase", letterSpacing: "0.05em" }}>
              Relay Phones (SMS & Push)
            </p>
            <div style={{ display: "flex", alignItems: "center", gap: "0.5rem", marginTop: "0.25rem" }}>
              <span
                style={{
                  display: "inline-block",
                  width: "10px",
                  height: "10px",
                  borderRadius: "50%",
                  background: activeDevices.length > 0 ? "var(--ok)" : "var(--warn)",
                }}
              />
              <span style={{ fontWeight: 600 }}>{activeDevices.length} Online</span>
              <span className="muted" style={{ fontSize: "0.8rem" }}>
                ({devices.length} enrolled)
              </span>
            </div>
          </div>

          <div>
            <p className="muted" style={{ fontSize: "0.8rem", textTransform: "uppercase", letterSpacing: "0.05em" }}>
              Last Bank Statement Fetch
            </p>
            <div style={{ marginTop: "0.25rem", fontWeight: 600 }}>
              {lastSyncAt ? when(lastSyncAt) : "No sync yet"}
            </div>
          </div>
        </div>
      </section>

      {/* Review Banner */}
      <section className="glass panel">
        <h2>{needs > 0 ? `${needs} payment${needs > 1 ? "s" : ""} need your review` : "Nothing needs review right now"}</h2>
        <p className="muted">{needs > 0 ? "Start with these. Customers are waiting." : "New payments appear here when they need a human decision."}</p>
        {needs > 0 && <p><Link href="/admin/claims?status=manual_review" className="btn">Open review queue</Link></p>}
      </section>

      {/* Metric Tiles */}
      <div className="grid3">
        <div className="glass panel tile"><div className="num">{d.total_claims}</div><p>Payments submitted</p></div>
        <div className="glass panel tile"><div className="num">{money(d.verified_value_minor)}</div><p>Verified value</p></div>
        <div className="glass panel tile"><div className="num">{d.unmatched_bank_credits}</div><p><Link href="/admin/bank">Bank credits not matched yet</Link></p></div>
      </div>

      {/* Recent Activity Split View */}
      <div className="grid2">
        {/* Latest Bank Transactions from Bot */}
        <section className="glass panel">
          <div className="flex-between">
            <h2>Latest ComBank Transactions</h2>
            <Link href="/admin/bank" className="ghost" style={{ fontSize: "0.85rem" }}>View all</Link>
          </div>
          {txns.length === 0 ? (
            <p className="muted" style={{ marginTop: "0.75rem" }}>No bank credits recorded yet.</p>
          ) : (
            <div style={{ marginTop: "0.75rem", display: "flex", flexDirection: "column", gap: "0.5rem" }}>
              {txns.map((t) => (
                <div
                  key={t.id}
                  style={{
                    background: "var(--surface-base)",
                    padding: "0.75rem",
                    borderRadius: "6px",
                    border: "1px solid var(--line)",
                    display: "flex",
                    justifyContent: "space-between",
                    alignItems: "center",
                  }}
                >
                  <div>
                    <p style={{ fontWeight: 600, fontSize: "0.9rem" }}>{t.remarks || "Bank Credit"}</p>
                    <p className="muted" style={{ fontSize: "0.75rem" }}>{when(t.value_dt)}</p>
                  </div>
                  <span style={{ fontWeight: 700, color: "var(--ok)", fontFamily: "var(--mono)" }}>
                    +{money(t.amount_minor)}
                  </span>
                </div>
              ))}
            </div>
          )}
        </section>

        {/* Recent SMS Alerts from Relay Device */}
        <section className="glass panel">
          <div className="flex-between">
            <h2>Recent Bank Alerts (Relay)</h2>
            <Link href="/admin/sms" className="ghost" style={{ fontSize: "0.85rem" }}>View all</Link>
          </div>
          {smsEvents.length === 0 ? (
            <p className="muted" style={{ marginTop: "0.75rem" }}>No SMS alerts received yet.</p>
          ) : (
            <div style={{ marginTop: "0.75rem", display: "flex", flexDirection: "column", gap: "0.5rem" }}>
              {smsEvents.map((e) => (
                <div
                  key={e.id}
                  style={{
                    background: "var(--surface-base)",
                    padding: "0.75rem",
                    borderRadius: "6px",
                    border: "1px solid var(--line)",
                    display: "flex",
                    justifyContent: "space-between",
                    alignItems: "center",
                  }}
                >
                  <div style={{ maxWidth: "70%" }}>
                    <div style={{ display: "flex", gap: "0.5rem", alignItems: "center" }}>
                      <span className="chip" style={{ fontSize: "0.7rem", padding: "1px 6px" }}>{e.sender}</span>
                      {e.branch && <span className="muted" style={{ fontSize: "0.75rem" }}>{e.branch}</span>}
                    </div>
                    <p style={{ fontSize: "0.8rem", color: "var(--text-body)", marginTop: "4px", whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis" }}>
                      {e.body}
                    </p>
                  </div>
                  <div style={{ textAlign: "right" }}>
                    {e.amount_minor != null && (
                      <p style={{ fontWeight: 600, color: e.direction === "credit" ? "var(--ok)" : "var(--warn)", fontFamily: "var(--mono)" }}>
                        {money(e.amount_minor)}
                      </p>
                    )}
                    <p className="muted" style={{ fontSize: "0.75rem" }}>{when(e.received_at)}</p>
                  </div>
                </div>
              ))}
            </div>
          )}
        </section>
      </div>

      {/* Claims by Status Chips */}
      <section className="glass panel">
        <h2>Claims by Status</h2>
        <div className="chips" style={{ marginTop: "0.5rem" }}>
          {ORDER.map((s) => (
            <Link key={s} href={`/admin/claims?status=${s}`} className="chip">
              {STATUS_LABEL[s]}: {n(s)}
            </Link>
          ))}
        </div>
      </section>
    </div>
  );
}
