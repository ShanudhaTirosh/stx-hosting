/**
 * Commercial Bank of Ceylon PLC (ComBank) WhatsApp response parser.
 * Authoritative parser for ComBank WhatsApp Banking message formats.
 */

export interface ParsedBankTransaction {
  valueDate: string; // ISO 8601 date string, e.g. "2026-11-05"
  valueDt: string;   // ISO 8601 with Sri Lanka offset (+05:30), e.g. "2026-11-05T00:00:00+05:30"
  remarks: string;
  amountMinor: number;
  direction: 'credit' | 'debit';
  branch: string | null;
  channel: string | null;
  raw: string;
}

export interface ParsedComBankStatement {
  accountMask: string | null;
  accountTail: string | null;
  transactions: ParsedBankTransaction[];
  currentBalanceMinor: number | null;
  availableBalanceMinor: number | null;
}

const LINE_REGEX = /^(\d{4}[\s\/-]\d{2}[\s\/-]\d{2})\s*:\s*(.+?)\s*:\s*(?:LKR|Rs\.?)\s*([\d,]+(?:\.\d{1,2})?)$/i;
const ACCOUNT_REGEX = /Account\s*:\s*([A-Za-z0-9*x]+)/i;
const BALANCE_CURRENT = /Current\s+Balance\s*:\s*(?:LKR|Rs\.?)\s*([\d,]+(?:\.\d{1,2})?)/i;
const BALANCE_AVAIL = /Available\s+Balance\s*:\s*(?:LKR|Rs\.?)\s*([\d,]+(?:\.\d{1,2})?)/i;

const CREDIT_KEYWORDS = /\b(deposit|credit|credited|cr|received|tx credit|interest)\b/i;
const DEBIT_KEYWORDS = /\b(withdrawal|withdrawn|debit|debited|dr|charge|fee|purchase|paid)\b/i;
const CRM_BRANCH_REGEX = /(?:DEPOSIT|WITHDRAWAL)[\s-]+([A-Z0-9 .&'-]+?)[\s-]*(?:CRM\d*|ATM\d*|BR|RM)\b/i;

export function parseMoneyMinor(str: string): number {
  const clean = str.replace(/[,\s]/g, '');
  const val = parseFloat(clean);
  if (isNaN(val)) return 0;
  return Math.round(val * 100);
}

export function normalizeDate(dateStr: string): { date: string; dt: string } {
  // ComBank format: "2026 11 05" or "2026-11-05" or "2026/11/05"
  const parts = dateStr.trim().split(/[\s\/-]+/);
  if (parts.length === 3) {
    const year = parts[0].padStart(4, '20');
    const month = parts[1].padStart(2, '0');
    const day = parts[2].padStart(2, '0');
    const date = `${year}-${month}-${day}`;
    return {
      date,
      dt: `${date}T00:00:00+05:30`
    };
  }
  return { date: dateStr, dt: `${dateStr}T00:00:00+05:30` };
}

export function parseComBankStatement(text: string): ParsedComBankStatement {
  const result: ParsedComBankStatement = {
    accountMask: null,
    accountTail: null,
    transactions: [],
    currentBalanceMinor: null,
    availableBalanceMinor: null
  };

  const lines = text.split(/\r?\n/).map(l => l.trim()).filter(Boolean);

  for (const line of lines) {
    // Check account mask
    const accMatch = line.match(ACCOUNT_REGEX);
    if (accMatch) {
      result.accountMask = accMatch[1].trim();
      const digitsOnly = result.accountMask.replace(/[^0-9]/g, '');
      if (digitsOnly.length >= 3) {
        result.accountTail = digitsOnly.slice(-3);
      }
      continue;
    }

    // Check balances
    const currMatch = line.match(BALANCE_CURRENT);
    if (currMatch) {
      result.currentBalanceMinor = parseMoneyMinor(currMatch[1]);
      continue;
    }
    const availMatch = line.match(BALANCE_AVAIL);
    if (availMatch) {
      result.availableBalanceMinor = parseMoneyMinor(availMatch[1]);
      continue;
    }

    // Check transaction line
    const match = line.match(LINE_REGEX);
    if (match) {
      const rawDate = match[1];
      const desc = match[2].trim();
      const amountStr = match[3];

      const { date, dt } = normalizeDate(rawDate);
      const amountMinor = parseMoneyMinor(amountStr);

      const isCredit = CREDIT_KEYWORDS.test(desc);
      const isDebit = DEBIT_KEYWORDS.test(desc);
      const direction: 'credit' | 'debit' = isCredit && !isDebit ? 'credit' : isDebit && !isCredit ? 'debit' : (desc.includes('CREDIT') || desc.includes('DEPOSIT') ? 'credit' : 'debit');

      let branch: string | null = null;
      let channel: string | null = null;

      if (/CRM/i.test(desc)) channel = 'CRM';
      else if (/ATM/i.test(desc)) channel = 'ATM';
      else if (/SLR|CEFTS/i.test(desc)) channel = 'TRANSFER';

      const branchMatch = desc.match(CRM_BRANCH_REGEX);
      if (branchMatch) {
        branch = branchMatch[1].trim().toUpperCase();
      }

      result.transactions.push({
        valueDate: date,
        valueDt: dt,
        remarks: desc,
        amountMinor,
        direction,
        branch,
        channel,
        raw: line
      });
    }
  }

  return result;
}

