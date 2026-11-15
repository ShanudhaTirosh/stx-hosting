import { describe, it } from 'node:test';
import assert from 'node:assert';
import { lookupOrder } from '../src/database/repositories/storeRepository.js';
import { setSupabaseClient } from '../src/database/supabase.js';

describe('Order Lookup & Anti-Guessing Protection', () => {
  it('returns order details when email matches exactly (case-insensitive)', async () => {
    const mockSupabase: any = {
      from: (table: string) => ({
        select: () => ({
          or: () => ({
            maybeSingle: async () => ({
              data: {
                uuid: 'order-uuid-9876',
                status: 'paid',
                total_cents: 2900,
                currency: 'USD',
                created_at: '2026-11-14T10:00:00Z',
                profiles: { email: 'Customer@Domain.com' },
                order_items: [
                  {
                    quantity: 1,
                    unit_price_cents: 2900,
                    hostname: 'vps-us-01',
                    product_variants: {
                      name: 'Starter VPS',
                      products: { name: 'Cloud VPS' },
                    },
                  },
                ],
              },
              error: null,
            }),
          }),
        }),
      }),
    };
    setSupabaseClient(mockSupabase);

    const result = await lookupOrder('order-uuid-9876', 'customer@domain.com');

    assert.notStrictEqual(result, null);
    assert.strictEqual(result?.uuid, 'order-uuid-9876');
    assert.strictEqual(result?.status, 'paid');
    assert.strictEqual(result?.total_cents, 2900);
    assert.strictEqual(result?.items.length, 1);
  });

  it('strictly returns null when customer email does not match', async () => {
    const mockSupabase: any = {
      from: (table: string) => ({
        select: () => ({
          or: () => ({
            maybeSingle: async () => ({
              data: {
                uuid: 'order-uuid-9876',
                status: 'paid',
                total_cents: 2900,
                currency: 'USD',
                profiles: { email: 'realowner@domain.com' },
                order_items: [],
              },
              error: null,
            }),
          }),
        }),
      }),
    };
    setSupabaseClient(mockSupabase);

    // Attacker tries guessing someone else's order with wrong email
    const result = await lookupOrder('order-uuid-9876', 'attacker@scam.com');

    assert.strictEqual(result, null, 'Must return null on email mismatch to prevent data leakage');
  });
});
