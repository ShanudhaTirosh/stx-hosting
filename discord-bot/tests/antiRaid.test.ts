import { describe, it } from 'node:test';
import assert from 'node:assert';
import { checkRaidVelocity, isRaidActive, clearRaidMode } from '../src/services/antiRaid.js';
import { GuildConfig } from '../src/database/repositories/guildConfigRepository.js';

describe('Anti-Raid Join Velocity Monitor', () => {
  const dummyConfig: GuildConfig = {
    guild_id: 'raid_test_guild',
    name: 'Raid Test Server',
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
    raid_mode_enabled: true,
    raid_threshold_joins: 5,
    raid_threshold_seconds: 5,
    spam_filter_enabled: true,
    phishing_filter_enabled: true,
    invite_filter_enabled: true,
    suggestion_channel_id: null,
  };

  const createMockMember = (id: string): any => ({
    id,
    guild: {
      id: 'raid_test_guild',
      name: 'Raid Test Server',
      client: { user: { id: 'bot_id' } },
      channels: { cache: new Map() },
    },
  });

  it('engages raid mode when join velocity exceeds threshold', () => {
    clearRaidMode('raid_test_guild');
    assert.strictEqual(isRaidActive('raid_test_guild'), false);

    // Simulate 4 joins (below threshold of 5)
    for (let i = 0; i < 4; i++) {
      const triggered = checkRaidVelocity(createMockMember(`member_${i}`), dummyConfig);
      assert.strictEqual(triggered, false);
    }
    assert.strictEqual(isRaidActive('raid_test_guild'), false);

    // 5th join triggers threshold
    const fifthTriggered = checkRaidVelocity(createMockMember('member_5'), dummyConfig);
    assert.strictEqual(fifthTriggered, true);
    assert.strictEqual(isRaidActive('raid_test_guild'), true);
  });

  it('resets and clears raid mode', () => {
    clearRaidMode('raid_test_guild');
    assert.strictEqual(isRaidActive('raid_test_guild'), false);
  });
});
