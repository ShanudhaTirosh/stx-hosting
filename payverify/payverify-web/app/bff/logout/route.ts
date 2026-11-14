import { NextRequest, NextResponse } from "next/server";

export async function POST(req: NextRequest) {
  if (req.headers.get("x-pv-csrf") !== "1") return NextResponse.json({ detail: "Missing CSRF header" }, { status: 403 });
  const res = NextResponse.json({ ok: true });
  res.cookies.set("pv_session", "", { path: "/", maxAge: 0 });
  res.cookies.set("pv_meta", "", { path: "/", maxAge: 0 });
  return res;
}
