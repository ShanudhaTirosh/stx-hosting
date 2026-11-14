export const money = (minor: number | null | undefined) =>
  minor == null ? "-" : "Rs. " + (minor / 100).toLocaleString("en-LK", { minimumFractionDigits: 2, maximumFractionDigits: 2 });

export const when = (iso?: string | null) =>
  iso ? new Date(iso.endsWith("Z") || /[+-]\d\d:\d\d$/.test(iso) ? iso : iso + "Z").toLocaleString("en-LK", { dateStyle: "medium", timeStyle: "short" }) : "-";

export const STATUS_LABEL: Record<string, string> = {
  pending: "Received", processing: "Reading proof", awaiting_bank_confirmation: "Waiting for bank",
  verified: "Verified", rejected: "Rejected", suspicious: "Suspicious", manual_review: "Manual review",
};

export const toMinor = (s: string) => Math.round(parseFloat(s.replace(/,/g, "")) * 100);
