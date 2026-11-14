"use client";
import { FormEvent, useState } from "react";
import { api, session } from "@/lib/api";

export default function Login() {
  const [mode, setMode] = useState<"in" | "up">("in");
  const [err, setErr] = useState(""); const [busy, setBusy] = useState(false); const [needOtp, setNeedOtp] = useState(false);

  async function submit(e: FormEvent<HTMLFormElement>) {
    e.preventDefault(); setErr(""); setBusy(true);
    const f = new FormData(e.currentTarget); const email = String(f.get("email")); const password = String(f.get("password"));
    try {
      if (mode === "up") await api("/api/v1/auth/register", { json: { email, name: f.get("name"), password } });
      const r = await api("/api/v1/auth/login", { json: { email, password, otp: f.get("otp") || undefined } });
      location.href = r.scope === "mfa_setup" ? "/mfa-setup" : r.role === "customer" ? "/orders" : "/admin";
    } catch (x: any) {
      if (x.message === "mfa_required") { setNeedOtp(true); setErr(""); }
      else setErr(x.message === "invalid_otp" ? "That code did not work. Wait for the next code and try again." : x.message);
    } finally { setBusy(false); }
  }

  return (
    <main className="center">
      <form className="glass panel narrow stack" onSubmit={submit}>
        <h1>{mode === "in" ? "Sign in" : "Create your account"}</h1>
        <p className="muted">{mode === "in" ? "Track and confirm your payments." : "Use the email you ordered with."}</p>
        {mode === "up" && <div className="field"><label htmlFor="name">Name</label><input id="name" name="name" autoComplete="name" required /></div>}
        <div className="field"><label htmlFor="email">Email</label><input id="email" name="email" type="email" autoComplete="email" required /></div>
        <div className="field"><label htmlFor="password">Password</label>
          <input id="password" name="password" type="password" minLength={mode === "up" ? 10 : 1} autoComplete={mode === "in" ? "current-password" : "new-password"} required />
          {mode === "up" && <p className="muted">At least 10 characters.</p>}</div>
        {needOtp && <div className="field"><label htmlFor="otp">Authenticator code</label><input id="otp" name="otp" inputMode="numeric" autoComplete="one-time-code" pattern="[0-9]{6}" maxLength={6} autoFocus required /></div>}
        {err && <p className="error" role="alert">{err}</p>}
        <button disabled={busy}>{mode === "in" ? "Sign in" : "Create account"}</button>
        <p className="muted">{mode === "in" ? "New here? " : "Already registered? "}
          <a href="#" onClick={(e) => { e.preventDefault(); setMode(mode === "in" ? "up" : "in"); setErr(""); }}>{mode === "in" ? "Create an account" : "Sign in"}</a></p>
      </form>
    </main>
  );
}
