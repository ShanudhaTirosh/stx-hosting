import {
  SlashCommandBuilder,
  ChatInputCommandInteraction,
  PermissionFlagsBits,
  EmbedBuilder,
} from 'discord.js';
import { getGuildConfig, upsertGuildConfig } from '../../database/repositories/guildConfigRepository.js';
import { COLORS, EMOJIS } from '../../config/constants.js';

export const data = new SlashCommandBuilder()
  .setName('config')
  .setDescription('View and update server bot configurations')
  .addSubcommand((sub) => sub.setName('show').setDescription('Display current server configuration'))
  .addSubcommand((sub) =>
    sub
      .setName('set-channel')
      .setDescription('Set a channel configuration')
      .addStringOption((opt) =>
        opt
          .setName('type')
          .setDescription('Channel type')
          .setRequired(true)
          .addChoices(
            { name: 'Mod Logs', value: 'mod_log_channel_id' },
            { name: 'Honeypot Decoy', value: 'honeypot_channel_id' },
            { name: 'Welcome', value: 'welcome_channel_id' },
            { name: 'Goodbye', value: 'goodbye_channel_id' },
            { name: 'Suggestions', value: 'suggestion_channel_id' }
          )
      )
      .addChannelOption((opt) => opt.setName('channel').setDescription('Channel to assign').setRequired(true))
  )
  .addSubcommand((sub) =>
    sub
      .setName('set-role')
      .setDescription('Assign default, verified, or staff roles')
      .addStringOption((opt) =>
        opt
          .setName('type')
          .setDescription('Role type')
          .setRequired(true)
          .addChoices(
            { name: 'Default Member Role', value: 'default_role_id' },
            { name: 'Verified Role', value: 'verified_role_id' }
          )
      )
      .addRoleOption((opt) => opt.setName('role').setDescription('Role to assign').setRequired(true))
  )
  .addSubcommand((sub) =>
    sub
      .setName('set-honeypot-action')
      .setDescription('Configure honeypot sanction action')
      .addStringOption((opt) =>
        opt
          .setName('action')
          .setDescription('Sanction')
          .setRequired(true)
          .addChoices({ name: 'Ban Member & Purge Spam', value: 'ban' }, { name: 'Timeout Member', value: 'timeout' })
      )
  )
  .setDefaultMemberPermissions(PermissionFlagsBits.Administrator);

export async function execute(interaction: ChatInputCommandInteraction): Promise<void> {
  const guild = interaction.guild;
  if (!guild) return;

  const subcommand = interaction.options.getSubcommand();
  const config = await getGuildConfig(guild.id);

  if (subcommand === 'show') {
    if (!config) {
      await interaction.reply({
        content: 'Server not configured yet. Run `/setup` first.',
        ephemeral: true,
      });
      return;
    }

    const embed = new EmbedBuilder()
      .setColor(COLORS.STX_BLUE)
      .setTitle(`⚙️ Server Configuration: ${guild.name}`)
      .addFields(
        {
          name: 'Channels',
          value:
            `• **Mod Logs**: ${config.mod_log_channel_id ? `<#${config.mod_log_channel_id}>` : 'Not set'}\n` +
            `• **Honeypot Trap**: ${config.honeypot_channel_id ? `<#${config.honeypot_channel_id}>` : 'Not set'}\n` +
            `• **Welcome**: ${config.welcome_channel_id ? `<#${config.welcome_channel_id}>` : 'Not set'}\n` +
            `• **Suggestions**: ${config.suggestion_channel_id ? `<#${config.suggestion_channel_id}>` : 'Not set'}`,
        },
        {
          name: 'Roles',
          value:
            `• **Default Role**: ${config.default_role_id ? `<@&${config.default_role_id}>` : 'None'}\n` +
            `• **Verified Role**: ${config.verified_role_id ? `<@&${config.verified_role_id}>` : 'None'}\n` +
            `• **Staff Roles**: ${config.staff_role_ids.length > 0 ? config.staff_role_ids.map((id) => `<@&${id}>`).join(', ') : 'None'}`,
        },
        {
          name: 'Protection & Thresholds',
          value:
            `• **Honeypot Action**: \`${config.honeypot_action.toUpperCase()}\` (Purge: ${config.honeypot_delete_days}d)\n` +
            `• **Verification Mode**: \`${config.verification_type}\`\n` +
            `• **Anti-Raid**: ${config.raid_threshold_joins} joins / ${config.raid_threshold_seconds}s\n` +
            `• **Spam & Phishing Filters**: \`${config.spam_filter_enabled ? 'ENABLED' : 'DISABLED'}\``,
        }
      )
      .setTimestamp();

    await interaction.reply({ embeds: [embed], ephemeral: true });
    return;
  }

  if (subcommand === 'set-channel') {
    const type = interaction.options.getString('type', true);
    const channel = interaction.options.getChannel('channel', true);

    await upsertGuildConfig(guild.id, { [type]: channel.id });
    await interaction.reply({
      content: `${EMOJIS.SUCCESS} Updated **${type}** to <#${channel.id}>.`,
      ephemeral: true,
    });
    return;
  }

  if (subcommand === 'set-role') {
    const type = interaction.options.getString('type', true);
    const role = interaction.options.getRole('role', true);

    await upsertGuildConfig(guild.id, { [type]: role.id });
    await interaction.reply({
      content: `${EMOJIS.SUCCESS} Updated **${type}** to <@&${role.id}>.`,
      ephemeral: true,
    });
    return;
  }

  if (subcommand === 'set-honeypot-action') {
    const action = interaction.options.getString('action', true) as 'ban' | 'timeout';
    await upsertGuildConfig(guild.id, { honeypot_action: action });
    await interaction.reply({
      content: `${EMOJIS.SUCCESS} Updated honeypot action to **${action.toUpperCase()}**.`,
      ephemeral: true,
    });
  }
}
