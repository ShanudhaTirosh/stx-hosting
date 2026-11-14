"use client";
import { FormEvent, useCallback, useEffect, useState } from "react";
import { api, session } from "@/lib/api";
import { money, when } from "@/lib/format";

export default function Bank() {
  const canImport = ["finance_admin", "super_admin"].includes(session.role ?? "");
  const [txns, setTxns] = useState<any[] | null>(null);
  const [res, setRes] = useState<any>(null); const [err, setErr] = useState(""); const [busy, setBusy] = useState(false);
  const load = useCallback(() => api("/api/v1/admin/bank/transactions?unmatched=true").then(setTxns).catch((e) => setErr(e.message)), []);
  useEffect(() => { load(); }, [load]);

  async function upload(e: FormEvent<HTMLFormElement>) {
    e.preventDefault(); setErr(""); setRes(null); setBusy(true);
    try { setRes(await api("/api/v1/admin/bank/statement-imports", { form: new FormData(e.currentTarget) })); await load(); }
    catch (x: any) { setErr(x.message); } finally { setBusy(false); }
  }
  return (
    <div className="stack">
      <h1>Bank statements</h1>
      {canImport ? (
        <form className="glass panel" onSubmit={upload}>
          <h2>Import a statement</h2>
          <p className="muted">Download the statement (CSV or Excel) from ComBank corporate online banking and upload it here. Payments waiting for the bank are checked straight away.</p>
          <div className="field"><label htmlFor="file">Statement file (CSV or Excel .xlsx)</label><input id="file" name="file" type="file" accept=".csv,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required /></div>
          <div className="field"><label htmlFor="tail">Last digits of the receiving account</label><input id="tail" name="account_tail" /></div>
          {err && <p className="error" role="alert">{err}</p>}
          <p><button disabled={busy}>{busy ? "Importing…" : "Import statement"}</button></p>
          {res && (<div className="okmsg" role="status">
            {res.status === "duplicate_file" ? "This exact file was already imported. Nothing changed." :
              `${res.rows_new} new credits and debits added, ${res.duplicates} already known. ${res.matching.verified} payments verified, ${res.matching.manual_review} sent to review.`}
            {res.errors?.length > 0 && <div className="error">{res.errors.length} rows could not be read: {res.errors.slice(0, 5).map((x: any) => `line ${x.line} (${x.error})`).join("; ")}</div>}
          </div>)}
        </form>
      ) : <p className="muted">Only finance admins can import statements.</p>}
      <section className="glass panel scroll"><h2>Bank credits not matched to a payment</h2>
        {!txns ? <p className="muted">Loading…</p> : txns.length === 0 ? <p className="muted">Every imported credit is matched. New credits appear here until a payment claims them.</p> : (
          <table><thead><tr><th>Date</th><th>Amount</th><th>Reference</th><th>Description</th></tr></thead>
            <tbody>{txns.map((t) => (<tr key={t.id}><td>{when(t.value_dt)}</td><td>{money(t.amount_minor)}</td><td className="ref">{t.ref ?? "-"}</td><td>{t.remarks}</td></tr>))}</tbody></table>)}
      </section>
    </div>
  );
}
