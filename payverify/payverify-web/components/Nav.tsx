"use client";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { session } from "@/lib/api";

export default function Nav({ links }: { links: { href: string; label: string }[] }) {
  const path = usePathname();
  return (
    <header className="nav glass">
      <Link href="/" className="brand">PayVerify</Link>
      <nav>
        {links.map((l) => (
          <Link key={l.href} href={l.href} className={path === l.href || (l.href !== "/admin" && path.startsWith(l.href)) ? "on" : ""}>{l.label}</Link>
        ))}
      </nav>
      <button className="ghost" onClick={() => session.logout()}>Sign out</button>
    </header>
  );
}
