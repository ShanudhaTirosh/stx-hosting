import { describe, it } from 'node:test';
import assert from 'node:assert';
import { checkPhishingAndInvites } from '../src/services/phishingFilter.js';
import { checkSpamAndMentions } from '../src/services/antiSpam.js';
import { GuildConfig } from '../src/database/repositories/guildConfigRepository.js';

describe('Anti-Spam & Phishing Filter', () => {
  const dummyConfig: GuildConfig = {
    guild_id: 'security_guild',
    name: 'Security Test Guild',
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
    honeypot_channel_id: null,
    honeypot_action: 'ban',
    honeypot_delete_days: 7,
    mod_log_channel_id: null,
    ticket_category_id: null,
    ticket_log_channel_id: null,
    ticket_panel_channel_id: null,
    ticket_panel_message_id: null,
    staff_role_ids: [],
    admin_role_ids: [],
    raid_mode_enabled: false,
    raid_threshold_joins: 10,
    raid_threshold_seconds: 10,
    spam_filter_enabled: true,
    phishing_filter_enabled: true,
    invite_filter_enabled: true,
    suggestion_channel_id: null,
  };

  it('detects known phishing and fake Nitro scam links', async () => {
    let deleted = false;
    let timedOut = false;

    const mockMessage: any = {
      content: 'Hey everyone claiming 3 months nitro here: https://discorcl-gift.com/airdrop',
      author: { bot: false, id: 'spammer_1', tag: 'Scammer#1234' },
      guild: { id: 'security_guild', channels: { cache: new Map() } },
      client: { user: { id: 'bot_id' } },
      member: {
        permissions: { has: () => false },
        roles: { cache: new Map() },
        timeout: async () => {
          timedOut = true;
        },
      },
      delete: async () => {
        deleted = true;
      },
    };

    const detected = await checkPhishingAndInvites(mockMessage, dummyConfig);
    assert.strictEqual(detected, true);
    assert.strictEqual(deleted, true, 'Phishing message must be deleted');
    assert.strictEqual(timedOut, true, 'Phishing author must be timed out');
  });

  it('detects mass mention abuse and pings', async () => {
    let deleted = false;
    let timedOut = false;

    const mockMessage: any = {
      content: 'Hello @everyone check this out!',
      author: { bot: false, id: 'mention_spammer', tag: 'Spam#1234' },
      guild: { id: 'security_guild', channels: { cache: new Map() } },
      client: { user: { id: 'bot_id' } },
      mentions: { users: new Map(), roles: new Map() },
      member: {
        permissions: { has: () => false },
        roles: { cache: new Map() },
        timeout: async () => {
          timedOut = true;
        },
      },
      delete: async () => {
        deleted = true;
      },
    };

    const detected = await checkSpamAndMentions(mockMessage, dummyConfig);
    assert.strictEqual(detected, true);
    assert.strictEqual(deleted, true);
    assert.strictEqual(timedOut, true);
  });
});
