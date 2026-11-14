"use client";
import Link from "next/link";
import { useEffect, useState } from "react";
import Nav from "@/components/Nav";
import { api } from "@/lib/api";

async function downloadMyData() {
  const data = await api("/api/v1/my/data-export");
  const a = document.createElement("a");
  a.href = URL.createObjectURL(new Blob([JSON.stringify(data, null, 2)], { type: "application/json" }));
  a.download = "my-payverify-data.json"; a.click(); URL.revokeObjectURL(a.href);
}
import { money } from "@/lib/format";
import { useGuard } from "@/lib/useGuard";

export default function Orders() {
  const role = useGuard("customer");
  const [orders, setOrders] = useState<any[] | null>(null);
  const [err, setErr] = useState("");
  useEffect(() => { if (role) api("/api/v1/my/orders").then(setOrders).catch((e) => setErr(e.message)); }, [role]);
  if (!role) return null;
  return (
    <div className="wrap">
      <Nav links={[{ href: "/orders", label: "My orders" }]} />
      <h1>My orders</h1>
      {err && <p className="error" role="alert">{err}</p>}
      <p className="muted">Want a copy of the data we hold about you? <a href="#" onClick={(e) => { e.preventDefault(); downloadMyData().catch((x) => setErr(x.message)); }}>Download my data</a>. To have it erased, contact us.</p>
      {orders && orders.length === 0 && <p className="muted">No orders yet. When you place an order, it will appear here with payment instructions.</p>}
      {orders && orders.length > 0 && (
        <div className="glass panel scroll"><table>
          <thead><tr><th>Invoice</th><th>Amount to pay</th><th>Status</th><th /></tr></thead>
          <tbody>{orders.map((o) => (
            <tr key={o.id}><td>{o.invoice_no}</td><td>{money(o.amount_minor)}</td>
              <td>{o.status === "paid" ? <span className="badge b-verified">Paid</span> : <span className="badge b-pending">Awaiting payment</span>}</td>
              <td><Link href={`/orders/${o.id}`}>{o.status === "paid" ? "View" : "Pay now"}</Link></td></tr>
          ))}</tbody></table></div>
      )}
    </div>
  );
}
