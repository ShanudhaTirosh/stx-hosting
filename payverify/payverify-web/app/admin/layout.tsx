"use client";
import Nav from "@/components/Nav";
import { useGuard } from "@/lib/useGuard";

export default function AdminLayout({ children }: { children: React.ReactNode }) {
  const role = useGuard("staff");
  if (!role) return null;
  return (
    <div className="wrap">
      <Nav links={[
        { href: "/admin", label: "Overview" },
        { href: "/admin/claims", label: "Payments" },
        { href: "/admin/integrations", label: "API & Webhooks" },
        { href: "/admin/bank", label: "Bank" },
        { href: "/admin/whatsapp-sync", label: "WhatsApp Sync" },
        { href: "/admin/devices", label: "Phones" },
        { href: "/admin/sms", label: "SMS alerts" }
      ]} />
      {children}
    </div>
  );
}
