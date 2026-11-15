import { describe, it } from 'node:test';
import assert from 'node:assert';
import { generateHtmlTranscript, generateTextTranscript } from '../src/services/transcriptGenerator.js';
import { TicketRecord, TicketMessageRecord } from '../src/database/repositories/ticketRepository.js';

describe('Ticket Transcript Generation', () => {
  const dummyTicket: TicketRecord = {
    id: 'ticket-uuid-1234',
    ticket_number: 142,
    guild_id: 'guild_1',
    channel_id: 'channel_1',
    user_id: 'user_customer',
    topic: 'vps',
    status: 'closed',
    priority: 'standard',
    claimed_by_id: 'staff_alice',
    claimed_at: '2026-11-14T20:00:00Z',
    first_response_at: '2026-11-14T20:05:00Z',
    closed_at: '2026-11-14T20:30:00Z',
    closed_by_id: 'staff_alice',
    close_reason: 'Resolved server firewall issue',
    transcript_url: null,
    transcript_text: null,
    rating: 5,
    rating_feedback: 'Great and fast support!',
    last_activity_at: '2026-11-14T20:30:00Z',
    created_at: '2026-11-14T19:55:00Z',
    updated_at: '2026-11-14T20:30:00Z',
  };

  const dummyMessages: TicketMessageRecord[] = [
    {
      ticket_id: 'ticket-uuid-1234',
      author_id: 'user_customer',
      author_name: 'CustomerBob',
      content: 'Hello, my VPS port 80 is not answering.',
      is_staff: false,
      sent_at: '2026-11-14T19:56:00Z',
    },
    {
      ticket_id: 'ticket-uuid-1234',
      author_id: 'staff_alice',
      author_name: 'StaffAlice',
      content: 'Hi Bob, checking your security group rules now.',
      is_staff: true,
      sent_at: '2026-11-14T20:06:00Z',
    },
  ];

  it('generates well-formatted plain text transcript', () => {
    const text = generateTextTranscript(dummyTicket, dummyMessages);
    assert.match(text, /Ticket #: 142/);
    assert.match(text, /Topic: VPS/);
    assert.match(text, /Resolved server firewall issue/);
    assert.match(text, /CustomerBob:/);
    assert.match(text, /\[STAFF\] StaffAlice:/);
  });

  it('generates secure, styled standalone HTML transcript', () => {
    const html = generateHtmlTranscript(dummyTicket, dummyMessages);
    assert.match(html, /<!DOCTYPE html>/);
    assert.match(html, /Support Ticket #142/);
    assert.match(html, /CustomerBob/);
    assert.match(html, /StaffAlice/);
    assert.match(html, /badge staff/);
    assert.match(html, /badge customer/);
  });
});
