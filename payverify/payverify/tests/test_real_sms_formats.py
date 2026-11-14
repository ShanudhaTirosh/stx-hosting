"""Built from real ComBank SMS the user forwarded (2026-11-05). See app/sms_parse.py for details."""
from app.sms_parse import contains_secret, parse

WITH_LINK = ("We wish to confirm a CRM Deposit at 15:06 for Rs. 2,000.00 through AMBALANG-CRM1 BR to "
            "your account 8*****7213 https://vas.combank.net/rec/ddH0edd4sGGNdHbE")
NO_LINK = ("We wish to confirm a CRM Deposit at 10:01 for Rs. 3,000.00 through BATAPOLA-CRM1 BR to "
          "your account 8*****7213")


def test_crm_deposit_with_receipt_link():
    p = parse(WITH_LINK)
    assert p.direction == "credit" and p.amount_minor == 200000 and p.branch == "AMBALANG"
    assert p.receipt_url == "https://vas.combank.net/rec/ddH0edd4sGGNdHbE"
    assert p.ref is None       # confirmed: no transaction reference in this message type
    assert p.ok is True
    assert not contains_secret(WITH_LINK)


def test_crm_deposit_without_receipt_link():
    p = parse(NO_LINK)
    assert p.direction == "credit" and p.amount_minor == 300000 and p.branch == "BATAPOLA" and p.receipt_url is None
    assert p.ok is True


def test_crm_deposit_never_produces_a_fake_reference():
    # guards against a future regex change accidentally matching "account 8*****7213" as a reference
    for msg in (WITH_LINK, NO_LINK):
        assert parse(msg).ref is None
