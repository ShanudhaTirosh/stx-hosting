"""Seed local testing database with demo staff, customer, and sample orders.
Usage:
    python seed_test_data.py
"""
import sys
import uuid

from app.config import settings
from app.crypto import encrypt_str
from app.db import Base, SessionLocal, engine
from app.models import Order, Role, User
from app.security import hash_password

def seed():
    print("[*] Initializing PayVerify database tables...")
    Base.metadata.create_all(engine)

    with SessionLocal() as db:
        # 1. Staff Admin Users (support both .com and .local)
        for admin_email in ["admin@payverify.com", "admin@payverify.local"]:
            admin = db.query(User).filter(User.email == admin_email).first()
            if not admin:
                admin = User(
                    email=admin_email,
                    name="System Administrator",
                    role=Role.super_admin.value,
                    password_hash=hash_password("AdminPassword123!"),
                    mfa_enabled=False,
                )
                db.add(admin)
                print(f"[+] Created staff user: {admin_email} (Role: super_admin, Password: AdminPassword123!)")
            else:
                print(f"[i] Staff user {admin_email} already exists.")

        # 2. Demo Customer User
        cust_email = "kasun@example.com"
        cust = db.query(User).filter(User.email == cust_email).first()
        if not cust:
            cust = User(
                email=cust_email,
                name="Kasun Perera",
                role=Role.customer.value,
                password_hash=hash_password("CustomerPass123!"),
                phone="+94771234567",
                external_id="CUST-LK-001",
            )
            db.add(cust)
            db.flush()
            print(f"[+] Created customer user: {cust_email} (Password: CustomerPass123!)")
        else:
            print(f"[i] Customer user {cust_email} already exists.")

        # 3. Sample Demo Order
        order_inv = "INV-DEMO-001"
        order = db.query(Order).filter(Order.invoice_no == order_inv).first()
        if not order:
            order = Order(
                customer_id=cust.id,
                invoice_no=order_inv,
                amount_minor=300000,
                expected_pay_amount_minor=300042,
                unique_amount=True,
                payment_code="PV-DEMO01",
                status="unpaid",
            )
            db.add(order)
            print(f"[+] Created demo order: {order_inv} (Expected: LKR 3,000.42, Code: PV-DEMO01)")
        else:
            print(f"[i] Demo order {order_inv} already exists.")

        db.commit()

    print("\n" + "=" * 60)
    print("DEMO CREDENTIALS READY FOR TESTING:")
    print("=" * 60)
    print("  Web Admin & Sandbox URL: http://localhost:3000/admin/sandbox")
    print("  Staff Login:             admin@payverify.local")
    print("  Staff Password:          AdminPassword123!")
    print("  Note on Staff MFA:       If prompted, scan TOTP or set PV_REQUIRE_STAFF_MFA=false in .env")
    print("-" * 60)
    print("  Customer Portal URL:     http://localhost:3000/login")
    print("  Customer Login:          kasun@example.com")
    print("  Customer Password:       CustomerPass123!")
    print("=" * 60 + "\n")

if __name__ == "__main__":
    seed()
