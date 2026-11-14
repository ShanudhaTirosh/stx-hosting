import os
import tempfile

_tmp = tempfile.mkdtemp()
os.environ["PV_DATABASE_URL"] = os.environ.get("PV_TEST_DATABASE_URL", f"sqlite:///{_tmp}/test.db")  # set PV_TEST_DATABASE_URL to run on Postgres
os.environ["PV_STORAGE_DIR"] = f"{_tmp}/storage"
os.environ["PV_REQUIRE_STAFF_MFA"] = "false"
os.environ["PV_JWT_SECRET"] = "test-secret-test-secret-test-secret"

import pytest  # noqa: E402
from fastapi.testclient import TestClient  # noqa: E402

from app.db import Base, SessionLocal, engine  # noqa: E402
from app.main import app  # noqa: E402
from app.models import Role, User  # noqa: E402
from app.security import hash_password  # noqa: E402

PNG = b"\x89PNG\r\n\x1a\n" + b"fakeimage-A" * 20
PNG2 = b"\x89PNG\r\n\x1a\n" + b"fakeimage-B" * 20
PNG3 = b"\x89PNG\r\n\x1a\n" + b"fakeimage-C" * 20


@pytest.fixture(autouse=True)
def clean():
    Base.metadata.drop_all(engine)
    Base.metadata.create_all(engine)
    yield


@pytest.fixture
def client():
    return TestClient(app)


def make_user(email, role, pw="correct-horse-battery"):
    with SessionLocal() as db:
        u = User(email=email, name=email, role=role.value, password_hash=hash_password(pw))
        db.add(u)
        db.commit()
        return u.id


def login(client, email, pw="correct-horse-battery"):
    r = client.post("/api/v1/auth/login", json={"email": email, "password": pw})
    assert r.status_code == 200, r.text
    return {"Authorization": f"Bearer {r.json()['access_token']}"}


@pytest.fixture
def world(client):
    ids = {"cust1": make_user("c1@example.com", Role.customer), "cust2": make_user("c2@example.com", Role.customer),
           "fin": make_user("fin@example.com", Role.finance_admin),
           "rev": make_user("rev@example.com", Role.reviewer)}
    h = {"c1": login(client, "c1@example.com"), "c2": login(client, "c2@example.com"),
         "fin": login(client, "fin@example.com"), "rev": login(client, "rev@example.com")}
    return ids, h


def new_order(client, h, customer_id, invoice, amount=450000, unique=False):
    r = client.post("/api/v1/admin/orders", headers=h["fin"], json={
        "customer_id": customer_id, "invoice_no": invoice, "amount_minor": amount, "unique_amount": unique})
    assert r.status_code == 201, r.text
    return r.json()


def submit(client, headers, order_id, amount, content=PNG, ref=None, name="a.png", channel="bank_transfer"):
    data = {"channel": channel, "amount_minor": str(amount)}
    if ref:
        data["reference"] = ref
    return client.post(f"/api/v1/orders/{order_id}/payment-claims", headers=headers, data=data,
                       files=[("files", (name, content))])


def statement(*rows):
    head = "Value Date,Description,Reference No,Debit,Credit,Balance\n"
    return (head + "\n".join(rows) + "\n").encode()
