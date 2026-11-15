import { describe, it } from 'node:test';
import assert from 'node:assert';
import { handleHoneypotTrigger } from '../src/services/honeypot.js';
import { GuildConfig } from '../src/database/repositories/guildConfigRepository.js';

describe('Honeypot Decoy Trap Protection', () => {
  const dummyConfig: GuildConfig = {
    guild_id: '123456789',
    name: 'STX Community',
    welcome_channel_id: null,
    welcome_message: null,
    welcome_embed_enabled: true,
    rules_channel_id: null,
    goodbye_channel_id: null,
    goodbye_message: null,
    default_role_id: null,
    verified_role_id: null,
    verification_type: 'button',
    min_account_age_days: 0,
    honeypot_channel_id: 'trap_channel_999',
    honeypot_action: 'ban',
    honeypot_delete_days: 7,
    mod_log_channel_id: 'mod_logs_111',
    ticket_category_id: null,
    ticket_log_channel_id: null,
    ticket_panel_channel_id: null,
    ticket_panel_message_id: null,
    staff_role_ids: ['staff_role_1'],
    admin_role_ids: ['admin_role_1'],
    raid_mode_enabled: false,
    raid_threshold_joins: 10,
    raid_threshold_seconds: 10,
    spam_filter_enabled: true,
    phishing_filter_enabled: true,
    invite_filter_enabled: true,
    suggestion_channel_id: null,
  };

  it('ignores messages in regular channels', async () => {
    const mockMessage: any = {
      channel: { id: 'general_channel' },
      author: { bot: false, id: 'user_1', tag: 'User#0001' },
      member: {
        permissions: { has: () => false },
        roles: { cache: new Map() },
      },
    };

    const triggered = await handleHoneypotTrigger(mockMessage, dummyConfig);
    assert.strictEqual(triggered, false);
  });

  it('exempts staff members from honeypot sanctions', async () => {
    let banned = false;
    const mockMessage: any = {
      channel: { id: 'trap_channel_999' },
      author: { bot: false, id: 'staff_user', tag: 'Staff#0001' },
      member: {
        permissions: { has: (perm: any) => false },
        roles: { cache: new Map([['staff_role_1', true]]) },
        ban: async () => {
          banned = true;
        },
      },
      delete: async () => {},
    };

    const triggered = await handleHoneypotTrigger(mockMessage, dummyConfig);
    assert.strictEqual(triggered, true);
    assert.strictEqual(banned, false, 'Staff member should NOT be banned');
  });

  it('triggers ban and message purge for non-staff in trap channel', async () => {
    let banned = false;
    let deleted = false;
    let banOptions: any = null;

    const mockMessage: any = {
      channel: { id: 'trap_channel_999' },
      content: 'free nitro click here',
      author: {
        bot: false,
        id: 'compromised_user',
        tag: 'Hacked#0001',
        createdAt: new Date(),
        createdTimestamp: Date.now() - 86400000,
        displayAvatarURL: () => 'http://example.com/avatar.png',
        send: async () => {},
      },
      member: {
        permissions: { has: () => false },
        roles: { cache: new Map() },
        joinedAt: new Date(),
        ban: async (opts: any) => {
          banned = true;
          banOptions = opts;
        },
      },
      delete: async () => {
        deleted = true;
      },
      guild: {
        id: '123456789',
        name: 'STX Community',
        channels: { cache: new Map() },
      },
      client: { user: { id: 'bot_id' } },
    };

    const triggered = await handleHoneypotTrigger(mockMessage, dummyConfig);
    assert.strictEqual(triggered, true);
    assert.strictEqual(deleted, true, 'Triggering message must be deleted');
    assert.strictEqual(banned, true, 'Compromised user must be banned');
    assert.strictEqual(banOptions.deleteMessageSeconds, 7 * 86400, 'Recent spam messages must be purged');
  });
});
