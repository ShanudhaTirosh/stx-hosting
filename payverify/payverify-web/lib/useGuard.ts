"use client";
import { useEffect, useState } from "react";
import { session } from "./api";

const STAFF = ["support", "reviewer", "finance_admin", "super_admin"];

/** Redirects to /login unless the stored role is allowed. Returns the role once confirmed. */
export function useGuard(kind: "customer" | "staff"): string | null {
  const [role, setRole] = useState<string | null>(null);
  useEffect(() => {
    const r = session.role;
    const ok = session.scope === "full" && !!r && (kind === "customer" ? r === "customer" : STAFF.includes(r));
    if (!ok) { location.href = "/login"; return; }
    setRole(r);
  }, [kind]);
  return role;
}
