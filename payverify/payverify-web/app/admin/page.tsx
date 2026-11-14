"use client";
import Link from "next/link";
import { useEffect, useState } from "react";
import { api } from "@/lib/api";
import { money, STATUS_LABEL } from "@/lib/format";

const ORDER = ["manual_review", "suspicious", "awaiting_bank_confirmation", "processing", "verified", "rejected"];

export default function Overview() {
  const [d, setD] = useState<any>(null); const [err, setErr] = useState("");
  useEffect(() => { api("/api/v1/admin/dashboard").then(setD).catch((e) => setErr(e.message)); }, []);
  if (err) return <p className="error" role="alert">{err}</p>;
  if (!d) return <p className="muted">Loading…</p>;
  const n = (s: string) => d.by_status[s] ?? 0;
  const needs = n("manual_review") + n("suspicious");
  return (
    <div className="stack">
      <h1>Overview</h1>
      <section className="glass panel">
        <h2>{needs > 0 ? `${needs} payment${needs > 1 ? "s" : ""} need your review` : "Nothing needs review right now"}</h2>
        <p className="muted">{needs > 0 ? "Start with these. Customers are waiting." : "New payments appear here when they need a human decision."}</p>
        {needs > 0 && <p><Link href="/admin/claims?status=manual_review" className="btn">Open review queue</Link></p>}
      </section>
      <div className="grid3">
        <div className="glass panel tile"><div className="num">{d.total_claims}</div><p>Payments submitted</p></div>
        <div className="glass panel tile"><div className="num">{money(d.verified_value_minor)}</div><p>Verified value</p></div>
        <div className="glass panel tile"><div className="num">{d.unmatched_bank_credits}</div><p><Link href="/admin/bank">Bank credits not matched yet</Link></p></div>
      </div>
      <section className="glass panel"><h2>By status</h2>
        <div className="chips">{ORDER.map((s) => (
          <Link key={s} href={`/admin/claims?status=${s}`} className="chip">{STATUS_LABEL[s]}: {n(s)}</Link>))}</div></section>
    </div>
  );
}
