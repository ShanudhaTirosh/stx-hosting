"""ComBank Digital Receipt Fetcher & PDF Generator.
Handles vas.combank.net digital receipts included in CRM Cash Deposit SMS messages.
Fetches full transaction details (STAN, traceNo, terminalId, notes breakdown) from ComBank APIs
and generates an official encrypted PDF receipt for storage and matching.
"""
import io
import json
import logging
import os
import re
import uuid
import httpx
from datetime import datetime
from reportlab.lib import colors
from reportlab.lib.pagesizes import A5
from reportlab.platypus import SimpleDocTemplate, Paragraph, Spacer, Table, TableStyle, HRFlowable
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle

from .config import settings
from .crypto import encrypt_bytes, decrypt_bytes

logger = logging.getLogger(__name__)

RECEIPT_TOKEN_REGEX = re.compile(r"https?://vas\.combank\.net/rec/([A-Za-z0-9_-]+)", re.I)
COMBANK_RECEIPT_API = "https://apis.combank.net/cardservicev1/v1/digital-receipt/token-stan/"


def extract_receipt_token(url: str) -> str | None:
    if not url:
        return None
    m = RECEIPT_TOKEN_REGEX.search(url)
    return m.group(1) if m else None


def fetch_receipt_details(token: str) -> dict | None:
    """Fetch digital receipt payload from ComBank API."""
    if not token:
        return None
    url = f"{COMBANK_RECEIPT_API}{token}"
    headers = {
        "User-Agent": "Mozilla/5.0 (compatible; PayVerify/1.0)",
        "Accept": "application/json",
    }
    try:
        with httpx.Client(timeout=10.0, follow_redirects=True) as client:
            resp = client.get(url, headers=headers)
            if resp.status_code == 200:
                data = resp.json()
                if data.get("transactionStatus") == "Successful" or data.get("responseDesc") == "Successful":
                    return data
                logger.warning(f"ComBank receipt status not successful: {data.get('transactionStatus')}")
                return data
            logger.warning(f"ComBank receipt API returned HTTP {resp.status_code}")
    except Exception as e:
        logger.error(f"Failed to fetch ComBank receipt details for token {token}: {str(e)}")
    return None


def generate_receipt_pdf(data: dict) -> bytes:
    """Generate a clean, official electronic deposit advice PDF using ReportLab."""
    buf = io.BytesIO()
    doc = SimpleDocTemplate(
        buf,
        pagesize=A5,
        leftMargin=30,
        rightMargin=30,
        topMargin=25,
        bottomMargin=25,
    )

    styles = getSampleStyleSheet()
    title_style = ParagraphStyle(
        "BankTitle",
        parent=styles["Heading1"],
        fontSize=15,
        leading=18,
        textColor=colors.HexColor("#0f3e6d"), # ComBank corporate blue
        alignment=1, # Center
        fontName="Helvetica-Bold",
    )
    subtitle_style = ParagraphStyle(
        "Subtitle",
        parent=styles["Normal"],
        fontSize=9,
        leading=12,
        textColor=colors.HexColor("#4b5563"),
        alignment=1,
        fontName="Helvetica",
    )
    label_style = ParagraphStyle(
        "Label",
        parent=styles["Normal"],
        fontSize=8.5,
        leading=11,
        textColor=colors.HexColor("#374151"),
        fontName="Helvetica-Bold",
    )
    val_style = ParagraphStyle(
        "Value",
        parent=styles["Normal"],
        fontSize=8.5,
        leading=11,
        textColor=colors.HexColor("#111827"),
        fontName="Helvetica",
    )
    total_style = ParagraphStyle(
        "Total",
        parent=styles["Normal"],
        fontSize=12,
        leading=15,
        textColor=colors.HexColor("#0f3e6d"),
        fontName="Helvetica-Bold",
        alignment=2, # Right
    )

    elements = []

    # Header
    elements.append(Paragraph("COMMERCIAL BANK OF CEYLON PLC", title_style))
    elements.append(Paragraph("CASH RECYCLER MACHINE (CRM) ELECTRONIC ADVICE", subtitle_style))
    elements.append(Spacer(1, 10))
    elements.append(HRFlowable(width="100%", thickness=1.5, color=colors.HexColor("#0f3e6d"), spaceBefore=3, spaceAfter=10))

    # Transaction Info Table
    branch = data.get("branch") or data.get("narration") or "CRM Branch"
    stan = data.get("stan") or "-"
    trace_no = data.get("traceNo") or "-"
    terminal_id = data.get("terminalId") or "-"
    txn_date = data.get("date") or datetime.now().strftime("%Y-%m-%d")
    txn_time = data.get("time") or ""
    account_num = data.get("accountNumber") or "-"
    card_num = data.get("card") or "-"
    status_desc = data.get("transactionStatus") or data.get("responseDesc") or "Successful"

    info_data = [
        [Paragraph("Terminal / Branch:", label_style), Paragraph(f"{terminal_id} / {branch}", val_style)],
        [Paragraph("Date & Time:", label_style), Paragraph(f"{txn_date}  {txn_time}", val_style)],
        [Paragraph("System Trace (STAN):", label_style), Paragraph(f"<b>{stan}</b>", val_style)],
        [Paragraph("Trace Number:", label_style), Paragraph(trace_no, val_style)],
        [Paragraph("Account Number:", label_style), Paragraph(account_num, val_style)],
        [Paragraph("Card Number:", label_style), Paragraph(card_num, val_style)],
        [Paragraph("Transaction Status:", label_style), Paragraph(f"<font color='#059669'><b>{status_desc}</b></font>", val_style)],
    ]

    t_info = Table(info_data, colWidths=[130, 220])
    t_info.setStyle(TableStyle([
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 4),
        ("TOPPADDING", (0, 0), (-1, -1), 2),
    ]))
    elements.append(t_info)
    elements.append(Spacer(1, 10))
    elements.append(HRFlowable(width="100%", thickness=0.5, color=colors.HexColor("#d1d5db"), spaceBefore=3, spaceAfter=8))

    # Notes Breakdown Table
    elements.append(Paragraph("<b>Deposit Breakdown</b>", label_style))
    elements.append(Spacer(1, 4))

    breakdown_data = [[
        Paragraph("<b>Denomination</b>", label_style),
        Paragraph("<b>Pieces</b>", label_style),
        Paragraph("<b>Amount (LKR)</b>", label_style),
    ]]

    deposit_details = data.get("depositDetails") or []
    if deposit_details and isinstance(deposit_details, list):
        for item in deposit_details:
            val = item.get("value", "0.00")
            notes = item.get("notes", "0")
            amt = item.get("amount", "0.00")
            breakdown_data.append([
                Paragraph(f"Rs. {val}", val_style),
                Paragraph(str(notes), val_style),
                Paragraph(f"{amt}", val_style),
            ])
    else:
        # Notes string fallback: "1000.00:3|"
        notes_str = data.get("notes") or ""
        for part in notes_str.split("|"):
            if ":" in part:
                den, cnt = part.split(":")
                try:
                    tot = float(den) * int(cnt)
                    breakdown_data.append([
                        Paragraph(f"Rs. {den}", val_style),
                        Paragraph(str(cnt), val_style),
                        Paragraph(f"{tot:.2f}", val_style),
                    ])
                except Exception:
                    pass

    t_breakdown = Table(breakdown_data, colWidths=[120, 80, 150])
    t_breakdown.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, 0), colors.HexColor("#f3f4f6")),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 4),
        ("TOPPADDING", (0, 0), (-1, -1), 3),
        ("GRID", (0, 0), (-1, -1), 0.5, colors.HexColor("#e5e7eb")),
    ]))
    elements.append(t_breakdown)
    elements.append(Spacer(1, 10))

    # Total Amount
    currency = data.get("currency") or "LKR"
    total_amt = data.get("totalDepositAmount") or "0.00"
    elements.append(Paragraph(f"TOTAL DEPOSIT: {currency} {total_amt}", total_style))
    elements.append(Spacer(1, 15))

    # Footer
    footer_text = Paragraph(
        f"<font color='#9ca3af' size='7'>Official ComBank digital advice parsed from vas.combank.net &bull; STAN #{stan} &bull; Verified by PayVerify</font>",
        subtitle_style,
    )
    elements.append(footer_text)

    doc.build(elements)
    return buf.getvalue()


def save_receipt_pdf(pdf_bytes: bytes, stan: str | None = None) -> str:
    """Save encrypted PDF into settings.storage_dir and return storage key."""
    os.makedirs(settings.storage_dir, exist_ok=True)
    clean_stan = re.sub(r"[^A-Za-z0-9]", "", stan or "receipt")
    key = f"receipt_{clean_stan}_{uuid.uuid4().hex[:12]}.pdf"
    full_path = os.path.join(settings.storage_dir, key)
    with open(full_path, "wb") as f:
        f.write(encrypt_bytes(pdf_bytes))
    return key


def read_receipt_pdf(storage_key: str) -> bytes:
    """Read and decrypt receipt PDF bytes."""
    full_path = os.path.join(settings.storage_dir, storage_key)
    with open(full_path, "rb") as f:
        return decrypt_bytes(f.read())


def process_receipt_url(url: str) -> dict | None:
    """End-to-end receipt processor: extracts token, fetches details, generates and saves PDF.
    Returns:
        {
            "token": str,
            "details": dict,
            "stan": str,
            "trace_no": str,
            "pdf_storage_key": str,
            "pdf_bytes": bytes,
        }
    """
    token = extract_receipt_token(url)
    if not token:
        return None
    details = fetch_receipt_details(token)
    if not details:
        return None
    stan = details.get("stan")
    trace_no = details.get("traceNo")

    try:
        pdf_bytes = generate_receipt_pdf(details)
        storage_key = save_receipt_pdf(pdf_bytes, stan)
    except Exception as e:
        logger.error(f"Failed to generate receipt PDF: {str(e)}")
        storage_key = None
        pdf_bytes = None

    return {
        "token": token,
        "details": details,
        "stan": stan,
        "trace_no": trace_no,
        "pdf_storage_key": storage_key,
        "pdf_bytes": pdf_bytes,
    }

