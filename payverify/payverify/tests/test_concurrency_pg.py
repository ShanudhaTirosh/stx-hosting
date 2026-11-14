"""Needs PostgreSQL (run with PV_TEST_DATABASE_URL=postgresql+psycopg2://...). SQLite cannot test row locking."""
import threading
from datetime import timedelta

import pytest
from sqlalchemy import func, select

from app.config import settings
from app.db import SessionLocal
from app.matcher import try_match_claim
from app.models import (Allocation, BankStatementImport, BankTransaction, ClaimStatus as S, Order, PaymentClaim,
                        Role, User, utcnow)
from app.security import hash_password

pytestmark = pytest.mark.skipif(not settings.database_url.startswith("postgresql"), reason="needs PostgreSQL")


def seed(n: int):
    """n customers, each with an unpaid order of the same amount and an awaiting claim, plus ONE bank credit whose
    remarks mention every payment code, so each claim sees it as a strong match."""
    with SessionLocal() as db:
        staff = User(email="s@x.lk", password_hash=hash_password("x" * 12), role=Role.finance_admin.value)
        db.add(staff); db.flush()
        codes, claim_ids = [], []
        for i in range(n):
            u = User(email=f"c{i}@x.lk", password_hash=hash_password("x" * 12), role=Role.customer.value)
            db.add(u); db.flush()
            o = Order(customer_id=u.id, invoice_no=f"I{i}", amount_minor=450000, expected_pay_amount_minor=450000, payment_code=f"PVRACE{i:02d}")
            db.add(o); db.flush()
            c = PaymentClaim(order_id=o.id, customer_id=u.id, channel="bank_transfer", claimed_amount_minor=450000,
                             status=S.awaiting_bank_confirmation.value)
            db.add(c); db.flush()
            codes.append(o.payment_code); claim_ids.append(c.id)
        imp = BankStatementImport(source="t", file_sha256="a" * 64, imported_by=staff.id)
        db.add(imp); db.flush()
        db.add(BankTransaction(import_id=imp.id, value_dt=utcnow() + timedelta(hours=1), amount_minor=450000, direction="credit",
                               remarks="CEFTS CR " + " ".join(codes), dedupe_key="k" * 64))
        db.commit()
        return claim_ids


@pytest.mark.parametrize("racers", [2, 8])
def test_one_bank_credit_can_verify_only_one_claim_under_concurrency(racers):
    ids = seed(racers)
    barrier = threading.Barrier(racers)
    results, errors = {}, []

    def worker(cid):
        try:
            with SessionLocal() as db:
                claim = db.get(PaymentClaim, cid)
                barrier.wait(timeout=20)           # all racers read the unallocated credit at the same moment
                results[cid] = try_match_claim(db, claim)
                db.commit()
        except Exception as e:                      # a crash would be a bug: surface it
            errors.append(repr(e))

    threads = [threading.Thread(target=worker, args=(i,)) for i in ids]
    [t.start() for t in threads]; [t.join(60) for t in threads]
    assert not errors, errors
    with SessionLocal() as db:
        assert db.scalar(select(func.count(Allocation.id))) == 1
        statuses = [db.get(PaymentClaim, i).status for i in ids]
        assert statuses.count(S.verified.value) == 1
        assert statuses.count(S.manual_review.value) == racers - 1
        assert db.scalar(select(func.count(Order.id)).where(Order.status == "paid")) == 1
