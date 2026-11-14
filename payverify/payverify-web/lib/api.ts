export class ApiError extends Error {
  constructor(public status: number, message: string) { super(message); }
}

/** The access token never reaches JavaScript: it is an httpOnly cookie handled by the /bff proxy.
 *  `pv_meta` (role.scope) is a readable hint used only to pick which screens to show. The API enforces every permission. */
function meta(): [string | null, string | null] {
  if (typeof document === "undefined") return [null, null];
  const m = document.cookie.split("; ").find((c) => c.startsWith("pv_meta="));
  if (!m) return [null, null];
  const [role, scope] = decodeURIComponent(m.slice(8)).split(".");
  return [role || null, scope || null];
}

export const session = {
  get role() { return meta()[0]; },
  get scope() { return meta()[1]; },
  get signedIn() { return !!meta()[0]; },
  async logout() {
    await fetch("/bff/logout", { method: "POST", headers: { "x-pv-csrf": "1" } }).catch(() => {});
    location.href = "/login";
  },
};

async function failure(r: Response): Promise<never> {
  let msg = r.statusText;
  try { const j = await r.json(); msg = typeof j.detail === "string" ? j.detail : JSON.stringify(j.detail ?? j); } catch {}
  if (r.status === 401 && typeof window !== "undefined" && !location.pathname.startsWith("/login") && msg !== "invalid_otp") {
    await session.logout();
  }
  throw new ApiError(r.status, msg);
}

export async function api<T = any>(path: string, opt: { method?: string; json?: unknown; form?: FormData } = {}): Promise<T> {
  const headers: Record<string, string> = {};
  let body: BodyInit | undefined;
  if (opt.json !== undefined) { headers["Content-Type"] = "application/json"; body = JSON.stringify(opt.json); }
  if (opt.form) body = opt.form;
  const method = opt.method ?? (body ? "POST" : "GET");
  if (method !== "GET") headers["x-pv-csrf"] = "1";
  const r = await fetch(`/bff${path}`, { method, headers, body, credentials: "same-origin" });
  if (!r.ok) return failure(r);
  return r.json();
}

export async function fetchBlobUrl(path: string): Promise<{ url: string; type: string }> {
  const r = await fetch(`/bff${path}`, { credentials: "same-origin" });
  if (!r.ok) return failure(r);
  const b = await r.blob();
  return { url: URL.createObjectURL(b), type: b.type };
}
