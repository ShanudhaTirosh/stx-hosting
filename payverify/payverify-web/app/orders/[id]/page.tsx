"use client";
import { useParams } from "next/navigation";
import { FormEvent, useCallback, useEffect, useState } from "react";
import Nav from "@/components/Nav";
import Timeline from "@/components/Timeline";
import { api } from "@/lib/api";
import { money, toMinor, when } from "@/lib/format";
import { useGuard } from "@/lib/useGuard";

const CHANNELS: [string, string][] = [
  ["bank_transfer", "Bank transfer"], ["online_banking", "Online banking"], ["mobile_banking", "Mobile banking app"],
  ["atm_cash_deposit", "ATM cash deposit"], ["other", "Other"],
];
const OPEN = ["pending", "processing", "awaiting_bank_confirmation", "manual_review", "suspicious"];

export default function PayOrder() {
  const role = useGuard("customer");
  const { id } = useParams<{ id: string }>();
  const [order, setOrder] = useState<any>(null);
  const [claims, setClaims] = useState<any[]>([]);
  const [err, setErr] = useState(""); const [busy, setBusy] = useState(false);

  const load = useCallback(async () => {
    try {
      const [o, c] = await Promise.all([api(`/api/v1/orders/${id}`), api(`/api/v1/orders/${id}/payment-claims`)]);
      setOrder(o); setClaims(c);
    } catch (x: any) { setErr(x.message); }
  }, [id]);

  useEffect(() => { if (role) load(); }, [role, load]);
  const latest = claims[0];
  const inFlight = latest && OPEN.includes(latest.status);
  useEffect(() => {  // refresh while the check is running so status updates without a reload
    if (!inFlight) return;
    const t = setInterval(load, 5000); return () => clearInterval(t);
  }, [inFlight, load]);

  async function submit(e: FormEvent<HTMLFormElement>) {
    e.preventDefault(); setErr(""); setBusy(true);
    const f = new FormData(e.currentTarget);
    const out = new FormData();
    out.set("channel", String(f.get("channel")));
    out.set("amount_minor", String(toMinor(String(f.get("amount")))));
    for (const k of ["reference", "sender_name"]) if (f.get(k)) out.set(k, String(f.get(k)));
    if (f.get("paid_at")) out.set("paid_at", new Date(String(f.get("paid_at"))).toISOString());
    for (const file of f.getAll("files")) out.append("files", file);
    try { await api(`/api/v1/orders/${id}/payment-claims`, { form: out }); await load(); (e.target as HTMLFormElement).reset(); }
    catch (x: any) { setErr(x.message); } finally { setBusy(false); }
  }

  if (!role) return null;
  const paid = order?.status === "paid";
  return (
    <div className="wrap">
      <Nav links={[{ href: "/orders", label: "My orders" }]} />
      {!order ? (err ? <p className="error" role="alert">{err}</p> : <p className="muted">Loading…</p>) : (
        <>
          <h1>Pay invoice {order.invoice_no}</h1>
          <div className="grid2">
            <div className="stack">
              <section className="glass panel">
                <p className="muted">Amount to pay</p>
                <div className="amount">{money(order.amount_minor)}</div>
                <p>Transfer exactly this amount so we can match it to your order.</p>
                <dl className="kv">
                  <dt>Bank</dt><dd>{order.pay_to.bank}</dd>
                  <dt>Account name</dt><dd>{order.pay_to.account_name || "-"}</dd>
                  <dt>Account number</dt><dd className="ref">{order.pay_to.account_number || "-"}</dd>
                  <dt>Put this in the remarks</dt><dd className="ref">{order.payment_code}</dd>
                </dl>
              </section>

              {!paid && (!latest || latest.status === "rejected") && (
                <form className="glass panel" onSubmit={submit}>
                  <h2>Upload your payment proof</h2>
                  <div className="field"><label htmlFor="channel">How did you pay?</label>
                    <select id="channel" name="channel" defaultValue="bank_transfer">{CHANNELS.map(([v, l]) => <option key={v} value={v}>{l}</option>)}</select></div>
                  <div className="field"><label htmlFor="amount">Amount paid (LKR)</label>
                    <input id="amount" name="amount" inputMode="decimal" defaultValue={(order.amount_minor / 100).toFixed(2)} required /></div>
                  <div className="field"><label htmlFor="reference">Transaction reference (if shown)</label><input id="reference" name="reference" /></div>
                  <div className="field"><label htmlFor="paid_at">Date and time of payment</label><input id="paid_at" name="paid_at" type="datetime-local" /></div>
                  <div className="field"><label htmlFor="sender_name">Name on the sending account</label><input id="sender_name" name="sender_name" /></div>
                  <div className="field"><label htmlFor="files">Receipt, screenshot or slip (JPG, PNG or PDF, up to 3 files)</label>
                    <input id="files" name="files" type="file" accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf" multiple required /></div>
                  {err && <p className="error" role="alert">{err}</p>}
                  <p className="row"><button disabled={busy}>{busy ? "Uploading…" : "Submit proof"}</button></p>
                </form>
              )}
              {err && latest && <p className="error" role="alert">{err}</p>}
            </div>

            <aside className="glass panel">
              <h2>Payment progress</h2>
              {paid && !latest ? <p>This invoice is already paid.</p> : !latest ? (
                <p className="muted">Pay, then upload your proof. We confirm payments against our bank account, usually the same business day.</p>
              ) : (
                <>
                  <Timeline status={latest.status} message={latest.message} verifiedAt={`Confirmed ${when(latest.verified_at)}`} />
                  {latest.extracted && (
                    <dl className="kv"><dt>Reference we read</dt><dd className="ref">{latest.extracted.reference ?? "-"}</dd>
                      <dt>Amount we read</dt><dd>{latest.extracted.amount ?? "-"}</dd>
                      <dt>Date we read</dt><dd>{latest.extracted.datetime ?? "-"}</dd></dl>
                  )}
                  {latest.status === "rejected" && <p>Check the amount and details, then upload your proof again using the form.</p>}
                  <p className="muted">Submitted {when(latest.created_at)}</p>
                </>
              )}
            </aside>
          </div>
        </>
      )}
    </div>
  );
}
