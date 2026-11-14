"use client";
import { FormEvent, useEffect, useState } from "react";
import { api, session } from "@/lib/api";

export default function MfaSetup() {
  const [secret, setSecret] = useState(""); const [uri, setUri] = useState(""); const [err, setErr] = useState("");
  useEffect(() => {
    if (!session.signedIn || session.scope !== "mfa_setup") { location.href = "/login"; return; }
    api("/api/v1/auth/mfa/setup", { method: "POST" }).then((r) => { setSecret(r.secret); setUri(r.otpauth_uri); }).catch((e) => setErr(e.message));
  }, []);
  async function confirm(e: FormEvent<HTMLFormElement>) {
    e.preventDefault(); setErr("");
    try {
      const r = await api("/api/v1/auth/mfa/confirm", { json: { otp: new FormData(e.currentTarget).get("otp") } });
      location.href = r.role ? "/admin" : "/login";
    } catch (x: any) { setErr(x.message === "invalid_otp" ? "That code did not match. Check the time on your phone and try the next code." : x.message); }
  }
  return (
    <main className="center">
      <form className="glass panel narrow stack" onSubmit={confirm}>
        <h1>Secure your account</h1>
        <p>Staff accounts need an authenticator app (Google Authenticator, Authy, 1Password). Add this account to it using the key below, then enter the 6-digit code.</p>
        <div className="field"><label>Setup key</label><p className="ref" style={{ fontSize: "1.1rem", wordBreak: "break-all" }}>{secret || "Loading…"}</p>
          {uri && <p className="muted">On a phone you can also <a href={uri}>open it in your authenticator</a>.</p>}</div>
        <div className="field"><label htmlFor="otp">6-digit code</label><input id="otp" name="otp" inputMode="numeric" pattern="[0-9]{6}" maxLength={6} autoComplete="one-time-code" required /></div>
        {err && <p className="error" role="alert">{err}</p>}
        <button disabled={!secret}>Turn on two-step sign-in</button>
        <p className="muted">Save the key somewhere safe. If you lose your phone, a super admin can reset this for you.</p>
      </form>
    </main>
  );
}
