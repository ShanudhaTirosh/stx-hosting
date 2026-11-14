"""Server-side SMS parsing (authoritative; the app only pre-filters).

Confirmed against real ComBank messages (2026-11):
  "We wish to confirm a CRM Deposit at 15:06 for Rs. 2,000.00 through AMBALANG-CRM1 BR to your
   account 8*****7213" -- sometimes followed by a vas.combank.net receipt link, sometimes not,
   and sometimes no SMS arrives at all for a cash deposit.
Patterns for CEFTS/transfer-style "credited"/"debited" wording are still unconfirmed guesses.

CRM (cash deposit machine) messages carry NO transaction reference and no depositor name -- there is
nothing in them that can uniquely identify an order. A payment code or unique-cents amount, which work
for bank transfers, cannot work here either: cash machines won't accept odd cents. So a CRM deposit SMS
can only ever corroborate (branch + amount + time), never auto-verify; matching the order still happens
from the bank statement, by branch description + amount + time window, with a human making the final call."""
import re
from dataclasses import dataclass

from .bank_import import ImportError_, parse_money

SECRET = re.compile(r"\b(otp|one[\s-]?time|verification code|passcode|password|pin|cvv|cvc)\b", re.I)
AMOUNT = re.compile(r"(?:LKR|Rs\.?)\s*([\d,]+(?:\.\d{1,2})?)", re.I)
CREDIT = re.compile(r"\b(credited|credit|deposit(?:ed)?|received|cr)\b", re.I)
DEBIT = re.compile(r"\b(debited|debit|withdraw(?:al|n)?|purchase|paid|dr)\b", re.I)
REF = re.compile(r"(?:ref(?:erence)?(?:\s*(?:no|number|id))?|txn(?:\s*id)?|trx|transaction\s*id)[\s:.#-]*([A-Z0-9]{6,})", re.I)
# "...through AMBALANG-CRM1 BR to your account..." -> branch "AMBALANG"
BRANCH = re.compile(r"\bthrough\s+([A-Z][A-Z .&'-]{1,40}?)[\s-]*CRM\d*\s*BR\b", re.I)
RECEIPT_URL = re.compile(r"https?://vas\.combank\.net\S*", re.I)


@dataclass
class Parsed:
    direction: str | None = None
    amount_minor: int | None = None
    ref: str | None = None
    branch: str | None = None
    receipt_url: str | None = None
    ok: bool = False


def contains_secret(text: str) -> bool:
    return bool(SECRET.search(text))


def parse(text: str) -> Parsed:
    p = Parsed()
    m = AMOUNT.search(text)
    if m:
        try:
            p.amount_minor = parse_money(m.group(1))
        except ImportError_:
            pass
    c, d = bool(CREDIT.search(text)), bool(DEBIT.search(text))
    p.direction = "credit" if c and not d else "debit" if d and not c else None
    r = REF.search(text)
    p.ref = r.group(1).upper() if r else None
    b = BRANCH.search(text)
    if b:
        p.branch = re.sub(r"\s+", " ", b.group(1)).strip().upper()
        if p.direction is None:  # CRM confirmations don't use the word "deposit"/"credited" elsewhere, just "CRM Deposit"
            p.direction = "credit" if re.search(r"\bCRM\s+Deposit\b", text, re.I) else \
                          "debit" if re.search(r"\bCRM\s+Withdrawal\b", text, re.I) else None
    u = RECEIPT_URL.search(text)
    p.receipt_url = u.group(0) if u else None
    p.ok = p.amount_minor is not None and p.direction is not None
    return p
