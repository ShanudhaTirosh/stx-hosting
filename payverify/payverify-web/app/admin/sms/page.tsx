"use client";
import { useEffect, useState } from "react";
import StatusBadge from "@/components/StatusBadge";
import { api } from "@/lib/api";
import { money, when } from "@/lib/format";

export default function SmsEvents() {
  const [rows, setRows] = useState<any[] | null>(null); const [err, setErr] = useState("");
  useEffect(() => { api("/api/v1/admin/sms-events").then(setRows).catch((e) => setErr(e.message)); }, []);
  return (
    <div className="stack">
      <h1>Bank SMS alerts</h1>
      <p className="muted">Supporting evidence only. Cash-machine deposit SMS never carry a reference number, so they
        cannot auto-verify a payment — match them to a bank statement credit by branch, amount and time, then approve manually.</p>
      {err && <p className="error" role="alert">{err}</p>}
      <div className="glass panel scroll">
        {!rows ? <p className="muted">Loading…</p> : rows.length === 0 ? <p className="muted">No SMS alerts received yet.</p> : (
          <table><thead><tr><th>Received</th><th>Amount</th><th>Branch</th><th>Reference / STAN</th><th>Matched to statement?</th><th>Receipt</th></tr></thead>
            <tbody>{rows.map((e) => (
              <tr key={e.id}><td>{when(e.received_at)}</td>
                <td>{e.amount_minor != null ? money(e.amount_minor) : <span className="muted">unreadable</span>}</td>
                <td>{e.branch ?? "-"}</td>
                <td className="ref">{e.receipt_stan ? <span title="System Trace Audit No">{e.receipt_stan}</span> : (e.ref ?? "-")}</td>
                <td>{e.matched_bank_txn_id ? <StatusBadge status="verified" /> : <span className="muted">Not yet</span>}</td>
                <td>
                  <div style={{ display: "flex", gap: "0.5rem" }}>
                    {e.has_receipt_pdf && (
                      <a
                        href={`/bff/api/v1/admin/sms-events/${e.id}/receipt-pdf`}
                        target="_blank"
                        rel="noreferrer"
                        className="btn-link"
                        style={{ fontWeight: "bold" }}
                      >
                        PDF Advice
                      </a>
                    )}
                    {e.receipt_url && (
                      <a href={e.receipt_url} target="_blank" rel="noreferrer" className="muted">
                        vas.combank
                      </a>
                    )}
                    {!e.has_receipt_pdf && !e.receipt_url && "-"}
                  </div>
                </td></tr>))}</tbody></table>)}
      </div>
    </div>
  );
}
