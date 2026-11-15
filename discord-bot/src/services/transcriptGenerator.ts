import { TicketRecord, TicketMessageRecord } from '../database/repositories/ticketRepository.js';

export function generateTextTranscript(ticket: TicketRecord, messages: TicketMessageRecord[]): string {
  const lines: string[] = [];
  lines.push(`================================================================`);
  lines.push(`STX ENTERPRISES CLOUD PLATFORM - SUPPORT TICKET TRANSCRIPT`);
  lines.push(`Ticket #: ${ticket.ticket_number} (ID: ${ticket.id})`);
  lines.push(`Topic: ${ticket.topic.toUpperCase()}`);
  lines.push(`Created At: ${ticket.created_at}`);
  lines.push(`Closed At: ${ticket.closed_at || 'N/A'}`);
  lines.push(`Closed By: ${ticket.closed_by_id || 'N/A'}`);
  lines.push(`Close Reason: ${ticket.close_reason || 'Resolved'}`);
  lines.push(`================================================================\n`);

  for (const msg of messages) {
    const roleBadge = msg.is_staff ? '[STAFF] ' : '';
    lines.push(`[${msg.sent_at || 'Unknown'}] ${roleBadge}${msg.author_name}:`);
    lines.push(`  ${msg.content || '[No text content]'}`);
    if (msg.attachments && msg.attachments.length > 0) {
      lines.push(`  Attachments: ${msg.attachments.map((a: any) => a.url || a.name || 'file').join(', ')}`);
    }
    lines.push('');
  }

  return lines.join('\n');
}

export function generateHtmlTranscript(ticket: TicketRecord, messages: TicketMessageRecord[]): string {
  const escapeHtml = (str: string) =>
    (str || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');

  const messageItems = messages
    .map((msg) => {
      const badge = msg.is_staff
        ? `<span class="badge staff">STAFF</span>`
        : `<span class="badge customer">CUSTOMER</span>`;

      const attachmentsHtml =
        msg.attachments && msg.attachments.length > 0
          ? `<div class="attachments"><strong>Attachments:</strong> ` +
            msg.attachments
              .map((a: any) => `<a href="${escapeHtml(a.url)}" target="_blank">${escapeHtml(a.name || 'View File')}</a>`)
              .join(' | ') +
            `</div>`
          : '';

      return `
      <div class="message ${msg.is_staff ? 'staff-msg' : ''}">
        <div class="msg-header">
          <span class="author">${escapeHtml(msg.author_name)}</span>
          ${badge}
          <span class="timestamp">${escapeHtml(msg.sent_at || '')}</span>
        </div>
        <div class="content">${escapeHtml(msg.content).replace(/\n/g, '<br/>')}</div>
        ${attachmentsHtml}
      </div>`;
    })
    .join('\n');

  return `<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Ticket #${ticket.ticket_number} Transcript - STX Hosting</title>
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background: #0f172a; color: #f8fafc; margin: 0; padding: 24px; }
    .container { max-width: 900px; margin: 0 auto; background: #1e293b; border-radius: 12px; padding: 24px; box-shadow: 0 10px 25px rgba(0,0,0,0.5); }
    .header { border-bottom: 2px solid #334155; padding-bottom: 16px; margin-bottom: 24px; }
    .header h1 { margin: 0 0 8px 0; color: #38bdf8; font-size: 24px; }
    .meta-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; font-size: 14px; color: #94a3b8; }
    .meta-item strong { color: #cbd5e1; }
    .messages { display: flex; flex-direction: column; gap: 16px; margin-top: 24px; }
    .message { background: #0f172a; border-radius: 8px; padding: 14px 18px; border-left: 4px solid #38bdf8; }
    .message.staff-msg { border-left-color: #10b981; background: #064e3b15; }
    .msg-header { display: flex; align-items: center; gap: 8px; margin-bottom: 6px; }
    .author { font-weight: bold; color: #f1f5f9; font-size: 15px; }
    .badge { font-size: 11px; padding: 2px 6px; border-radius: 4px; font-weight: bold; }
    .badge.staff { background: #10b981; color: #022c22; }
    .badge.customer { background: #64748b; color: #f8fafc; }
    .timestamp { font-size: 12px; color: #64748b; margin-left: auto; }
    .content { font-size: 14px; line-height: 1.5; color: #e2e8f0; word-break: break-word; }
    .attachments { margin-top: 8px; font-size: 13px; color: #38bdf8; }
    .attachments a { color: #38bdf8; text-decoration: underline; }
    .footer { text-align: center; margin-top: 32px; font-size: 12px; color: #64748b; border-top: 1px solid #334155; padding-top: 16px; }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <h1>🎫 Support Ticket #${ticket.ticket_number}</h1>
      <div class="meta-grid">
        <div class="meta-item"><strong>Topic:</strong> ${escapeHtml(ticket.topic.toUpperCase())}</div>
        <div class="meta-item"><strong>Status:</strong> ${escapeHtml(ticket.status.toUpperCase())}</div>
        <div class="meta-item"><strong>Priority:</strong> ${escapeHtml(ticket.priority.toUpperCase())}</div>
        <div class="meta-item"><strong>Created:</strong> ${escapeHtml(ticket.created_at)}</div>
        <div class="meta-item"><strong>Closed:</strong> ${escapeHtml(ticket.closed_at || 'Active')}</div>
        <div class="meta-item"><strong>Reason:</strong> ${escapeHtml(ticket.close_reason || 'N/A')}</div>
      </div>
    </div>
    <div class="messages">
      ${messageItems}
    </div>
    <div class="footer">
      Generated automatically by STX Enterprises Cloud Platform Discord Bot
    </div>
  </div>
</body>
</html>`;
}
