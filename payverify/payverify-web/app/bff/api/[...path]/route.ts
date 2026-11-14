import { NextRequest, NextResponse } from "next/server";

/**
 * Same-origin proxy to the PayVerify API. The browser never sees the access token:
 * it lives in an httpOnly, SameSite=Strict cookie that only this server reads.
 * CSRF: state-changing calls need the custom X-PV-CSRF header (not sendable cross-site without a CORS
 * preflight, which we never grant) and a matching Origin when one is present.
 */
const API = process.env.API_URL ?? "http://localhost:8000";
const SECURE = process.env.NODE_ENV === "production" && process.env.COOKIE_INSECURE !== "1"; // COOKIE_INSECURE is for local smoke tests only
const TOKEN_PATHS = new Set(["v1/auth/login", "v1/auth/mfa/confirm"]);
const MAX_BODY = 30 * 1024 * 1024;

function deny(status: number, detail: string) { return NextResponse.json({ detail }, { status }); }

async function handle(req: NextRequest, ctx: { params: Promise<{ path: string[] }> }) {
  const rel = (await ctx.params).path.join("/");
  if (!rel.startsWith("v1/") || rel.includes("..")) return deny(404, "Not found");

  if (req.method !== "GET") {
    if (req.headers.get("x-pv-csrf") !== "1") return deny(403, "Missing CSRF header");
    const origin = req.headers.get("origin");
    const host = req.headers.get("x-forwarded-host") ?? req.headers.get("host");
    if (origin && new URL(origin).host !== host) return deny(403, "Cross-site request refused");
  }

  const headers = new Headers();
  for (const h of ["content-type", "accept", "x-api-key", "authorization"]) { const v = req.headers.get(h); if (v) headers.set(h, v); }
  const token = req.cookies.get("pv_session")?.value;
  if (token) headers.set("authorization", `Bearer ${token}`);
  const ip = req.headers.get("x-forwarded-for")?.split(",")[0].trim() ?? req.headers.get("x-real-ip");
  if (ip) headers.set("x-forwarded-for", ip);       // so the API's per-IP login limit sees the real client

  let body: ArrayBuffer | undefined;
  if (req.method !== "GET" && req.method !== "HEAD") {
    const raw = await req.arrayBuffer();
    if (raw.byteLength > MAX_BODY) return deny(413, "Upload too large");
    if (raw.byteLength > 0) body = raw;
  }

  let up: Response;
  try {
    up = await fetch(`${API}/api/${rel}${req.nextUrl.search}`, { method: req.method, headers, body, cache: "no-store", redirect: "manual" });
  } catch { return deny(502, "The payment service is unreachable. Try again shortly."); }

  if (TOKEN_PATHS.has(rel) && up.ok) {
    const j = await up.json();
    const res = NextResponse.json({ role: j.role, scope: j.scope });          // token is NOT returned to the browser
    const maxAge = (j.scope === "full" ? 30 : 10) * 60;
    res.cookies.set("pv_session", j.access_token, { httpOnly: true, secure: SECURE, sameSite: "strict", path: "/", maxAge });
    res.cookies.set("pv_meta", `${j.role}.${j.scope}`, { httpOnly: false, secure: SECURE, sameSite: "strict", path: "/", maxAge }); // for UI routing only
    return res;
  }

  const out = new Headers({ "cache-control": "no-store", "x-content-type-options": "nosniff" });
  for (const h of ["content-type", "content-disposition"]) { const v = up.headers.get(h); if (v) out.set(h, v); }
  return new NextResponse(up.body, { status: up.status, headers: out });
}

export { handle as GET, handle as POST, handle as PUT, handle as PATCH, handle as DELETE };
