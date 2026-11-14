"""Statement import (Tier A evidence). Generic CSV reader with header auto-detection.

ComBank's real export layout is unconfirmed: once you have a sample statement, add an adapter here.
Unparseable rows are REPORTED, never silently dropped."""
import csv
import hashlib
import io
import json
import re
from datetime import date, datetime, timedelta, timezone
from decimal import Decimal, InvalidOperation

from sqlalchemy import select
from sqlalchemy.orm import Session

from . import audit
from .models import BankStatementImport, BankTransaction

SL_TZ = timezone(timedelta(hours=5, minutes=30))  # Sri Lanka has no DST
ALIASES = {
    "date": ["value date", "transaction date", "txn date", "posting date", "date"],
    "desc": ["description", "narration", "particulars", "details", "remarks", "transaction details"],
    "ref": ["reference no", "reference", "ref no", "ref", "transaction id", "txn id", "transaction reference"],
    "credit": ["credit amount", "credits", "credit", "deposit", "deposits", "cr"],
    "debit": ["debit amount", "debits", "debit", "withdrawal", "withdrawals", "dr"],
    "amount": ["amount"],
    "type": ["dr/cr", "type", "cr/dr"],
    "balance": ["balance", "running balance"],
}
DATE_FORMATS = [
    "%Y-%m-%dT%H:%M:%S.%f", "%Y-%m-%dT%H:%M:%S", "%Y-%m-%dT%H:%M",
    "%Y-%m-%d %H:%M:%S.%f", "%Y-%m-%d %H:%M:%S", "%Y-%m-%d %H:%M",
    "%d/%m/%Y %H:%M:%S", "%d/%m/%Y %H:%M",
    "%d/%m/%Y %I:%M:%S %p", "%d/%m/%Y %I:%M %p", "%d-%m-%Y %I:%M:%S %p", "%d-%m-%Y %I:%M %p",
    "%Y-%m-%d %I:%M:%S %p", "%Y-%m-%d %I:%M %p",
    "%d.%m.%Y %H:%M:%S", "%d.%m.%Y %H:%M", "%d.%m.%Y", "%d.%m.%Y %I:%M:%S %p", "%d.%m.%Y %I:%M %p",
    "%Y-%m-%d", "%d/%m/%Y", "%d-%m-%Y", "%d-%b-%Y", "%d/%m/%y", "%Y/%m/%d", "%d %b %Y",
    "%d %B %Y", "%b %d, %Y", "%B %d, %Y",
]


class ImportError_(ValueError):
    pass


def _profile() -> dict:
    from .config import settings
    if not settings.statement_profile:
        return {}
    try:
        return json.loads(settings.statement_profile)
    except ValueError:
        raise ImportError_("PV_STATEMENT_PROFILE is not valid JSON")


def _aliases() -> dict[str, list[str]]:
    out = {k: list(v) for k, v in ALIASES.items()}
    for k, names in (_profile().get("columns") or {}).items():
        if k in out:
            out[k] = [n.strip().lower() for n in names] + out[k]
    return out


def parse_money(s: str | None) -> int | None:
    if s is None or not s.strip():
        return None
    # Strip currency names, symbols, and multi-codepoint Sinhala/Tamil rupee prefixes
    # e.g. LKR, Rs, Rs., රු., රු, ரூ., ரூ, $, €, £, and trailing /= or /-
    t = s.strip()
    t = re.sub(r"(?i)\b(?:lkr|rs|usd|eur|gbp)\b\.?", "", t)
    t = re.sub(r"(?:රු|ரூ|[$€£])\.?", "", t)
    t = re.sub(r"[/=|-]+$", "", t).strip()
    t = re.sub(r"[,\s]", "", t)
    neg = t.startswith("(") and t.endswith(")")
    t = t.strip("()")
    if not t:
        return None
    try:
        v = Decimal(t)
    except InvalidOperation:
        raise ImportError_(f"bad amount {s!r}")
    minor = int((v * 100).to_integral_value())
    return -minor if neg else minor


def parse_dt(s: str) -> datetime:
    s = s.strip()
    # Normalize uppercase AM/PM
    s = re.sub(r"(?i)\b(am|pm)\b", lambda m: m.group(1).upper(), s)
    # Strip ISO timezone if present for SL_TZ
    s_clean = re.sub(r"(?:Z|[+-]\d{2}:?\d{2})$", "", s).strip()
    for f in [*(_profile().get("date_formats") or []), *DATE_FORMATS]:
        for cand in (s, s_clean):
            try:
                return datetime.strptime(cand, f).replace(tzinfo=SL_TZ)
            except ValueError:
                continue
    raise ImportError_(f"bad date {s!r}")


def _map_headers(headers: list[str]) -> dict[str, str]:
    low = {h.strip().lower(): h for h in headers}
    out = {}
    for key, names in _aliases().items():
        for n in names:
            if n in low:
                out[key] = low[n]
                break
    if "date" not in out or not ({"credit", "debit", "amount"} & out.keys()):
        raise ImportError_(f"Cannot recognise statement columns: {headers}")
    return out


def _cell(v) -> str:
    if v is None:
        return ""
    if isinstance(v, datetime):
        return v.strftime("%Y-%m-%d %H:%M:%S")
    if isinstance(v, date):
        return v.strftime("%Y-%m-%d")
    if isinstance(v, bool):
        return str(v)
    if isinstance(v, int):
        return str(v)
    if isinstance(v, float):
        return str(int(v)) if v.is_integer() else format(Decimal(repr(v)), "f")
    return str(v).strip()


def read_rows(data: bytes) -> list[list[str]]:
    """CSV (comma, semicolon, tab or pipe separated; UTF-8 or Windows-1252) or .xlsx, as rows of text."""
    if data.startswith(b"PK\x03\x04"):
        try:
            import openpyxl
            wb = openpyxl.load_workbook(io.BytesIO(data), read_only=True, data_only=True)
        except Exception:
            raise ImportError_("Could not open this Excel file")
        for ws in wb.worksheets:                      # the first sheet that looks like a statement wins
            rows = [[_cell(c) for c in r] for r in ws.iter_rows(values_only=True)]
            if _find_header(rows) is not None:
                return rows
        raise ImportError_("No sheet with date and credit/debit columns found")
    if data.startswith(b"\xd0\xcf\x11\xe0"):
        raise ImportError_("Old .xls files are not supported: save the statement as .xlsx or CSV")
    try:
        text = data.decode("utf-8-sig")
    except UnicodeDecodeError:
        text = data.decode("cp1252", errors="replace")
    first = None
    for delim in (",", ";", "\t", "|"):   # keep the separator for which a statement header row is actually found
        rows = list(csv.reader(io.StringIO(text), delimiter=delim))
        first = first or rows
        if _find_header(rows) is not None:
            return rows
    return first or []


def _find_header(rows: list[list[str]]) -> int | None:
    for i, r in enumerate(rows):      # statements often have title/account lines above the header
        try:
            _map_headers([c.strip() for c in r])
            return i
        except ImportError_:
            continue
    return None


def import_statement(db: Session, *, data: bytes, source: str, user_id: int, account_tail: str | None) -> dict:
    from .config import settings
    if settings.bank_account_tail and account_tail != settings.bank_account_tail:
        raise ImportError_("Statement account does not match the configured receiving account")
    sha = hashlib.sha256(data).hexdigest()
    if db.execute(select(BankStatementImport.id).where(BankStatementImport.file_sha256 == sha)).first():
        return {"status": "duplicate_file", "rows_total": 0, "rows_new": 0, "duplicates": 0, "errors": []}
    _profile()          # fail loudly on a malformed PV_STATEMENT_PROFILE instead of reporting 'no header'
    rows = read_rows(data)
    hidx = _find_header(rows)
    if hidx is None:
        raise ImportError_("No header row with date and credit/debit columns found")
    headers = [c.strip() for c in rows[hidx]]
    m = _map_headers(headers)
    reader = []
    for off, cells in enumerate(rows[hidx + 1:], start=hidx + 2):
        d: dict[str, str] = {}
        for h, v in zip(headers, cells + [""] * (len(headers) - len(cells))):
            d.setdefault(h, v)
        reader.append((off, d))
    imp = BankStatementImport(source=source, file_sha256=sha, imported_by=user_id)
    db.add(imp)
    db.flush()
    seen: dict[tuple, int] = {}
    total = new = dups = 0
    errors: list[dict] = []
    for n, row in reader:
        if not any((v or "").strip() for v in row.values()):
            continue
        total += 1
        try:
            dt = parse_dt(row[m["date"]])
            credit = parse_money(row.get(m.get("credit", ""), None)) if "credit" in m else None
            debit = parse_money(row.get(m.get("debit", ""), None)) if "debit" in m else None
            if credit is None and debit is None and "amount" in m:
                amt = parse_money(row[m["amount"]])
                if amt is None:
                    raise ImportError_("empty amount")
                if "type" in m:
                    is_cr = (row.get(m["type"], "") or "").strip().upper().startswith("C")
                    credit, debit = (amt, None) if is_cr else (None, abs(amt))
                else:   # one signed column: negative = money out
                    credit, debit = (amt, None) if amt > 0 else (None, -amt)
            if credit and credit > 0:
                direction, amount = "credit", credit
            elif debit and debit > 0:
                direction, amount = "debit", debit
            else:
                raise ImportError_("no positive credit or debit amount")
        except ImportError_ as e:
            errors.append({"line": n, "error": str(e)})
            continue
        desc = (row.get(m.get("desc", ""), "") or "").strip()
        ref = (row.get(m.get("ref", ""), "") or "").strip() or None
        bal = (row.get(m.get("balance", ""), "") or "").strip()
        # Identical rows within one file are legitimate (two equal deposits): disambiguate by occurrence index,
        # which stays stable when overlapping statements are re-imported.
        base = (dt.isoformat(), amount, direction, ref or "", desc, bal)
        seen[base] = seen.get(base, 0) + 1
        key = hashlib.sha256(repr((*base, seen[base])).encode()).hexdigest()
        if db.execute(select(BankTransaction.id).where(BankTransaction.dedupe_key == key)).first():
            dups += 1
            continue
        db.add(BankTransaction(import_id=imp.id, bank_txn_ref=ref, value_dt=dt, amount_minor=amount,
                               direction=direction, remarks=desc, dedupe_key=key, raw=dict(row)))
        new += 1
    imp.rows_total, imp.rows_new = total, new
    audit.log(db, action="bank.import", entity="import", entity_id=imp.id, actor_id=user_id,
              detail={"rows_total": total, "rows_new": new, "duplicates": dups, "errors": len(errors)})
    return {"status": "imported", "import_id": imp.id, "rows_total": total, "rows_new": new,
            "duplicates": dups, "errors": errors}
