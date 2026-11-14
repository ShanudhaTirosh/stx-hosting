import io
import datetime as dt

import openpyxl
import pytest

from app.config import settings

from .conftest import statement


def up(client, h, content, name="s.csv"):
    return client.post("/api/v1/admin/bank/statement-imports", headers=h["fin"], files={"file": (name, content)})


def test_semicolon_cp1252_csv_with_title_lines_and_dotted_dates(client, world, monkeypatch):
    ids, h = world
    monkeypatch.setattr(settings, "statement_profile", '{"date_formats": ["%d.%m.%Y"], "columns": {"ref": ["Cheque/Ref"], "credit": ["Cr Amount"]}}')
    txt = ("Commercial Bank - Account Statement\nAccount: 8001234821;;;;\n\n"
           "Txn Date;Narration;Cheque/Ref;Debit;Cr Amount;Balance\n"
           "04.11.2026;CEFTS CR Ñandu PV123ABC;CEFT777888;;4.500,00;10.000,00\n").encode("cp1252")
    txt = txt.replace(b"4.500,00", b"4500.00").replace(b"10.000,00", b"10000.00")
    r = up(client, h, txt).json()
    assert r["rows_new"] == 1 and not r["errors"]
    t = client.get("/api/v1/admin/bank/transactions", headers=h["fin"]).json()[0]
    assert t["amount_minor"] == 450000 and t["ref"] == "CEFT777888" and "PV123ABC" in t["remarks"]


def test_single_signed_amount_column(client, world):
    ids, h = world
    csv = b"Date,Description,Amount,Balance\n2026-11-04,CEFTS CR X,4500.00,1\n2026-11-04,ATM WDL,-2000.00,2\n"
    assert up(client, h, csv).json()["rows_new"] == 2
    credits = client.get("/api/v1/admin/bank/transactions", headers=h["fin"]).json()
    assert [c["amount_minor"] for c in credits] == [450000]       # only the credit is listed; the debit is stored but not matchable


def make_xlsx(rows, title=True):
    wb = openpyxl.Workbook()
    ws = wb.active
    if title:
        ws.append(["Statement for account 8001234821"]); ws.append([])
    for r in rows:
        ws.append(r)
    buf = io.BytesIO(); wb.save(buf)
    return buf.getvalue()


def test_xlsx_with_real_dates_numbers_and_numeric_references(client, world):
    ids, h = world
    data = make_xlsx([["Value Date", "Description", "Reference No", "Debit", "Credit", "Balance"],
                      [dt.datetime(2026, 11, 4, 10, 15), "CEFTS CR PV999ZZZ", 123456789012, None, 4500.5, 9999.75],
                      [dt.datetime(2026, 11, 4, 11, 0), "CHEQUE DEP", "CHQ001", None, 1200, 11199.75]])
    r = up(client, h, data, "s.xlsx").json()
    assert r["rows_new"] == 2 and not r["errors"]
    rows = {t["ref"]: t for t in client.get("/api/v1/admin/bank/transactions", headers=h["fin"]).json()}
    assert rows["123456789012"]["amount_minor"] == 450050 and rows["CHQ001"]["amount_minor"] == 120000


def test_old_xls_and_garbage_are_refused_with_clear_messages(client, world):
    ids, h = world
    r = up(client, h, b"\xd0\xcf\x11\xe0" + b"x" * 50, "s.xls")
    assert r.status_code == 400 and "xlsx" in r.json()["detail"]
    r = up(client, h, b"hello,world\n1,2\n")
    assert r.status_code == 400 and "header" in r.json()["detail"].lower()


def test_bad_profile_json_is_reported_not_crashing(client, world, monkeypatch):
    ids, h = world
    monkeypatch.setattr(settings, "statement_profile", "{not json")
    r = up(client, h, statement("04/11/2026,x,R1,,10.00,1"))
    assert r.status_code == 400 and "PV_STATEMENT_PROFILE" in r.json()["detail"]
