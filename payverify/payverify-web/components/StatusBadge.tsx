import { STATUS_LABEL } from "@/lib/format";

export default function StatusBadge({ status }: { status: string }) {
  return <span className={`badge b-${status}`}>{STATUS_LABEL[status] ?? status}</span>;
}
