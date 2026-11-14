import type { ReactNode } from "react";

const STEPS = ["Proof received", "Reading your proof", "Waiting for the bank", "Payment confirmed"];
const idx: Record<string, number> = { pending: 0, processing: 1, awaiting_bank_confirmation: 2, manual_review: 2, suspicious: 2, verified: 3 };

export default function Timeline({ status, message, verifiedAt }: { status: string; message?: string | null; verifiedAt?: ReactNode }) {
  const rejected = status === "rejected";
  const current = idx[status] ?? 0;
  return (
    <ol className="timeline" aria-label="Payment progress">
      {STEPS.map((s, i) => {
        const done = !rejected && (i < current || status === "verified");
        const now = !rejected && i === current && status !== "verified";
        return (
          <li key={s} className={done ? "done" : now ? "now" : ""} aria-current={now ? "step" : undefined}>
            <span className="node" aria-hidden />
            <div>
              <strong>{s}</strong>
              {now && message && <p>{message}</p>}
              {i === 3 && status === "verified" && <p>{verifiedAt}</p>}
            </div>
          </li>
        );
      })}
      {rejected && <li className="bad"><span className="node" aria-hidden /><div><strong>We could not verify this payment</strong><p>{message}</p></div></li>}
    </ol>
  );
}
