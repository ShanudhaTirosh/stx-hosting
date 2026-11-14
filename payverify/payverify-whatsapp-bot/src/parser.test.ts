import test from 'node:test';
import assert from 'node:assert';
import { parseComBankStatement } from './parser.js';

test('parses ComBank WhatsApp statement accurately', () => {
  const sample = `
Account : 8xxxxxx213

2026 11 05 : DEPOSIT-BATAPOLA-CRM1 BR : LKR 3,000.00
2026 11 04 : WITHDRAWAL SLR GALLE     RM : LKR 705.00
2026 11 02 : ADV.INCOME TX CREDIT : LKR 0.01

Thank you for WhatsApp Banking with us. What would you like to do next?

1. Main Menu
2. Exit
  `;

  const parsed = parseComBankStatement(sample);
  assert.strictEqual(parsed.accountMask, '8xxxxxx213');
  assert.strictEqual(parsed.accountTail, '213');
  assert.strictEqual(parsed.transactions.length, 3);

  // First transaction: CRM Deposit
  const t1 = parsed.transactions[0];
  assert.strictEqual(t1.valueDate, '2026-11-05');
  assert.strictEqual(t1.valueDt, '2026-11-05T00:00:00+05:30');
  assert.strictEqual(t1.amountMinor, 300000);
  assert.strictEqual(t1.direction, 'credit');
  assert.strictEqual(t1.channel, 'CRM');
  assert.strictEqual(t1.branch, 'BATAPOLA');

  // Second transaction: Withdrawal
  const t2 = parsed.transactions[1];
  assert.strictEqual(t2.valueDate, '2026-11-04');
  assert.strictEqual(t2.amountMinor, 70500);
  assert.strictEqual(t2.direction, 'debit');

  // Third transaction: Tax credit
  const t3 = parsed.transactions[2];
  assert.strictEqual(t3.valueDate, '2026-11-02');
  assert.strictEqual(t3.amountMinor, 1);
  assert.strictEqual(t3.direction, 'credit');
});

test('parses ComBank WhatsApp balance response', () => {
  const sample = `
Account : 8xxxxxx213
Current Balance : LKR 3,450.70
Available Balance : LKR 3,124.70

Thank you for WhatsApp Banking with us. What would you like to do next?
  `;

  const parsed = parseComBankStatement(sample);
  assert.strictEqual(parsed.accountMask, '8xxxxxx213');
  assert.strictEqual(parsed.accountTail, '213');
  assert.strictEqual(parsed.currentBalanceMinor, 345070);
  assert.strictEqual(parsed.availableBalanceMinor, 312470);
});
