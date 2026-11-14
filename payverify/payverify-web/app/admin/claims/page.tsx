"use client";
import { useRouter, useSearchParams } from "next/navigation";
import { Suspense, useEffect, useState } from "react";
import StatusBadge from "@/components/StatusBadge";
import { api } from "@/lib/api";
import { money, STATUS_LABEL, when } from "@/lib/format";

const FILTERS = ["", "manual_review", "suspicious", "awaiting_bank_confirmation", "processing", "verified", "rejected"];

function Queue() {
  const router = useRouter(); const sp = useSearchParams();
  const status = sp.get("status") ?? ""; const [q, setQ] = useState(sp.get("q") ?? "");
  const [rows, setRows] = useState<any[] | null>(null); const [err, setErr] = useState("");
  useEffect(() => {
    const p = new URLSearchParams(); if (status) p.set("status", status); if (sp.get("q")) p.set("q", sp.get("q")!);
    setRows(null); api("/api/v1/admin/claims?" + p).then(setRows).catch((e) => setErr(e.message));
  }, [status, sp]);
  const go = (s: string, query = q) => { const p = new URLSearchParams(); if (s) p.set("status", s); if (query) p.set("q", query); router.push("/admin/claims?" + p); };
  return (
    <div className="stack">
      <h1>Payments</h1>
      <div className="row">
        <div className="chips" role="group" aria-label="Filter by status">{FILTERS.map((s) => (
          <button key={s} className={`chip ${s === status ? "on" : ""}`} onClick={() => go(s)}>{s ? STATUS_LABEL[s] : "All"}</button>))}</div>
        <form onSubmit={(e) => { e.preventDefault(); go(status); }} style={{ flex: 1, minWidth: 220 }}>
          <input aria-label="Search by reference, claim, order or customer ID" placeholder="Search reference or ID" value={q} onChange={(e) => setQ(e.target.value)} /></form>
      </div>
      {err && <p className="error" role="alert">{err}</p>}
      <div className="glass panel scroll">
        {!rows ? <p className="muted">Loading…</p> : rows.length === 0 ? <p className="muted">No payments match. Try another status or clear the search.</p> : (
          <table><thead><tr><th>#</th><th>Customer</th><th>Order</th><th>Amount</th><th>Reference</th><th>Status</th><th>Submitted</th></tr></thead>
            <tbody>{rows.map((c) => (
              <tr key={c.id} className="click" onClick={() => router.push(`/admin/claims/${c.id}`)}>
                <td>{c.id}</td>
                <td>
                  <div style={{ fontWeight: 500 }}>{c.customer_name || c.customer_email || `#${c.customer_id}`}</div>
                  {c.customer_email && <small className="muted" style={{ display: "block", fontSize: "0.8rem" }}>{c.customer_email}</small>}
                </td>
                <td>{c.order_id}</td>
                <td>{money(c.amount_minor)}</td>
                <td className="ref">{c.reference ?? "-"}</td>
                <td><StatusBadge status={c.status} /></td>
                <td>{when(c.created_at)}</td>
              </tr>))}</tbody></table>)}
      </div>
    </div>
  );
}

export default function Claims() { return <Suspense fallback={<p className="muted">Loading…</p>}><Queue /></Suspense>; }
