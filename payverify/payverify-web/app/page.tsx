"use client";
import { useEffect } from "react";
import { session } from "@/lib/api";

export default function Home() {
  useEffect(() => {
    const r = session.role;
    location.href = !session.signedIn ? "/login" : r === "customer" ? "/orders" : "/admin";
  }, []);
  return null;
}
