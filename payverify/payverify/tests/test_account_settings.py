from app.db import SessionLocal
from app.models import AuditLog, MerchantAccountSettings
from sqlalchemy import select


def test_account_settings_crud_masking_and_reveal(client, world):
    ids, h = world
    fin_headers = h["fin"]

    # 1. Get initial settings (masked)
    resp = client.get("/api/v1/admin/settings/account", headers=fin_headers)
    assert resp.status_code == 200, resp.text
    data = resp.json()
    assert "account_number_masked" in data
    assert "2662265" in data["monitored_senders"]
    assert "usage_indicators" in data

    # 2. Update with new account number and NIC
    update_payload = {
        "full_name": "Shanufx Global Ltd",
        "bank_name": "Commercial Bank of Ceylon PLC",
        "branch_name": "Colombo Fort",
        "account_number": "8123456789",
        "id_number": "199512345678",
        "mobile_number": "+94771234567",
        "combank_whatsapp_number": "+94771234567",
        "monitored_senders": ["COMBANK", "2662265", "0771234567"],
    }
    put_resp = client.put("/api/v1/admin/settings/account", headers=fin_headers, json=update_payload)
    assert put_resp.status_code == 200, put_resp.text
    assert "account_number" in put_resp.json()["updated_fields"]

    # 3. Read back masked settings
    get_resp = client.get("/api/v1/admin/settings/account", headers=fin_headers)
    assert get_resp.status_code == 200
    updated_data = get_resp.json()
    assert updated_data["full_name"] == "Shanufx Global Ltd"
    assert updated_data["account_number_tail"] == "6789"
    assert updated_data["account_number_masked"] == "****6789"
    assert "8123456789" not in updated_data["account_number_masked"]
    assert "199512345678" not in updated_data["id_number_masked"]
    assert "2662265" in updated_data["monitored_senders"]

    # 4. Attempt reveal with wrong password
    bad_reveal = client.post(
        "/api/v1/admin/settings/account/reveal",
        headers=fin_headers,
        json={"password": "WrongPassword"},
    )
    assert bad_reveal.status_code == 401

    # 5. Successful reveal with correct password ("correct-horse-battery" is default pw in conftest)
    good_reveal = client.post(
        "/api/v1/admin/settings/account/reveal",
        headers=fin_headers,
        json={"password": "correct-horse-battery"},
    )
    assert good_reveal.status_code == 200
    revealed = good_reveal.json()
    assert revealed["account_number"] == "8123456789"
    assert revealed["id_number"] == "199512345678"

    # 6. Verify audit logs exist and DO NOT contain plain credentials
    with SessionLocal() as db:
        logs = db.execute(
            select(AuditLog).where(AuditLog.entity == "merchant_settings").order_by(AuditLog.id.desc())
        ).scalars().all()
        assert len(logs) >= 2  # update and reveal
        reveal_log = next(l for l in logs if l.action == "settings.credentials_revealed")
        assert reveal_log.actor_id == ids["fin"]
        # Ensure neither plaintext account nor NIC was stored in log detail
        log_str = str(reveal_log.detail)
        assert "8123456789" not in log_str
        assert "199512345678" not in log_str
