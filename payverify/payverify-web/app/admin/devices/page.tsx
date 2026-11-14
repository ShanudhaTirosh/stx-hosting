"use client";
import { useCallback, useEffect, useState } from "react";
import { api, session } from "@/lib/api";
import { when } from "@/lib/format";

export default function Devices() {
  const canManage = ["finance_admin", "super_admin"].includes(session.role ?? "");
  const [rows, setRows] = useState<any[] | null>(null); const [err, setErr] = useState("");
  const [code, setCode] = useState<string | null>(null); const [busy, setBusy] = useState(false);
  const load = useCallback(() => api("/api/v1/admin/devices").then(setRows).catch((e) => setErr(e.message)), []);
  useEffect(() => { load(); const t = setInterval(load, 20000); return () => clearInterval(t); }, [load]);

  async function newCode() {
    setBusy(true); setErr("");
    try { setCode((await api("/api/v1/admin/devices/enrolment-codes", { method: "POST" })).code); } catch (x: any) { setErr(x.message); } finally { setBusy(false); }
  }
  async function revoke(id: number) {
    if (!confirm("Revoke this phone? It will stop being able to send alerts.")) return;
    try { await api(`/api/v1/admin/devices/${id}/revoke`, { method: "POST" }); await load(); } catch (x: any) { setErr(x.message); }
  }
  const warn = (d: any) => [!d.status?.sms_permission && "SMS permission off", d.status?.battery_optimised && "battery saver on",
    d.status?.last_error].filter(Boolean).join(" · ");

  return (
    <div className="stack">
      <h1>Phones</h1>
      <p className="muted">Phones that forward bank SMS alerts. Alerts are supporting evidence only: a payment is verified from the bank statement, never from an SMS.</p>
      {err && <p className="error" role="alert">{err}</p>}
      {canManage && (
        <section className="glass panel">
          <h2>Add a phone</h2>
          <p className="muted">Create a one-time code, then enter it in the PayVerify app on the phone that receives your bank SMS.</p>
          <p><button onClick={newCode} disabled={busy}>Create enrolment code</button></p>
          {code && <p>Code: <strong className="ref" style={{ fontSize: "1.4rem" }}>{code}</strong> <span className="muted">Valid for 15 minutes. Shown once.</span></p>}
        </section>)}
      <section className="glass panel scroll">
        {!rows ? <p className="muted">Loading…</p> : rows.length === 0 ? <p className="muted">No phones connected yet.</p> : (
          <table><thead><tr><th>Name</th><th>State</th><th>Last heartbeat</th><th>Waiting</th><th>Needs attention</th><th /></tr></thead>
            <tbody>{rows.map((d) => (
              <tr key={d.id}><td>{d.name}</td>
                <td>{d.revoked ? <span className="badge b-rejected">Revoked</span> : d.stale ? <span className="badge b-manual_review">Silent</span> : <span className="badge b-verified">Online</span>}</td>
                <td>{when(d.last_heartbeat_at)}</td><td>{d.status?.queue_depth ?? "-"}</td><td>{warn(d) || "-"}</td>
                <td>{canManage && !d.revoked && <button className="ghost" onClick={() => revoke(d.id)}>Revoke</button>}</td></tr>))}</tbody></table>)}
      </section>
    </div>
  );
}
