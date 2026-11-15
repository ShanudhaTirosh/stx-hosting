import { describe, it } from 'node:test';
import assert from 'node:assert';
import { processPaidInvoiceNotification } from '../src/services/invoiceService.js';
import { setSupabaseClient } from '../src/database/supabase.js';

describe('Invoice Notification & Closed DM Fallback', () => {
  it('handles user with no linked Discord account gracefully', async () => {
    // Mock Supabase returning no link
    const mockSupabase: any = {
      from: (table: string) => ({
        select: () => ({
          eq: () => ({
            not: () => ({
              maybeSingle: async () => ({ data: null }),
            }),
            eq: () => ({
              maybeSingle: async () => ({ data: null }),
            }),
          }),
        }),
      }),
    };
    setSupabaseClient(mockSupabase);

    const mockClient: any = {
      users: { fetch: async () => null },
    };

    const result = await processPaidInvoiceNotification(mockClient, {
      invoice_number: 'INV-2026-001',
      platform_user_id: 'user_uuid_none',
      total_cents: 2500,
      currency: 'USD',
    });

    assert.strictEqual(result.delivered, false);
    assert.strictEqual(result.channel, 'not_linked');
  });

  it('delivers invoice directly to user DM when DMs are open', async () => {
    // Mock Supabase returning linked Discord ID
    const mockSupabase: any = {
      from: (table: string) => {
        if (table === 'discord_account_links') {
          return {
            select: () => ({
              eq: () => ({
                not: () => ({
                  maybeSingle: async () => ({ data: { discord_user_id: 'discord_user_123' } }),
                }),
              }),
            }),
          };
        }
        return { select: () => ({}) };
      },
    };
    setSupabaseClient(mockSupabase);

    let dmSent = false;
    const mockClient: any = {
      users: {
        fetch: async (id: string) => ({
          id,
          username: 'TestCustomer',
          send: async () => {
            dmSent = true;
          },
        }),
      },
    };

    const result = await processPaidInvoiceNotification(mockClient, {
      invoice_number: 'INV-2026-002',
      platform_user_id: 'user_uuid_active',
      total_cents: 4900,
      currency: 'USD',
    });

    assert.strictEqual(result.delivered, true);
    assert.strictEqual(result.channel, 'dm');
    assert.strictEqual(dmSent, true);
  });

  it('safely falls back to private ticket when user DMs are closed', async () => {
    // Mock Supabase returning linked Discord ID and an active support ticket
    const mockSupabase: any = {
      from: (table: string) => {
        if (table === 'discord_account_links') {
          return {
            select: () => ({
              eq: () => ({
                not: () => ({
                  maybeSingle: async () => ({ data: { discord_user_id: 'discord_user_456' } }),
                }),
              }),
            }),
          };
        }
        if (table === 'discord_tickets') {
          return {
            select: () => ({
              eq: () => ({
                neq: () => ({
                  order: () => ({
                    limit: () => ({
                      maybeSingle: async () => ({
                        data: { channel_id: 'private_ticket_channel_789', guild_id: 'guild_1' },
                      }),
                    }),
                  }),
                }),
              }),
            }),
          };
        }
        return { select: () => ({}) };
      },
    };
    setSupabaseClient(mockSupabase);

    let ticketMsgSent = false;
    const mockClient: any = {
      users: {
        fetch: async (id: string) => ({
          id,
          username: 'ClosedDMCustomer',
          send: async () => {
            const err: any = new Error('Cannot send messages to this user');
            err.code = 50007; // Discord Closed DM error code
            throw err;
          },
        }),
      },
      channels: {
        cache: new Map([
          [
            'private_ticket_channel_789',
            {
              send: async () => {
                ticketMsgSent = true;
              },
            },
          ],
        ]),
      },
    };

    const result = await processPaidInvoiceNotification(mockClient, {
      invoice_number: 'INV-2026-003',
      platform_user_id: 'user_uuid_closed_dm',
      total_cents: 15000,
      currency: 'USD',
    });

    assert.strictEqual(result.delivered, true);
    assert.strictEqual(result.channel, 'ticket', 'Must route to private ticket fallback');
    assert.strictEqual(ticketMsgSent, true);
  });
});
