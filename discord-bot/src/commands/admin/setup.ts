import {
  SlashCommandBuilder,
  ChatInputCommandInteraction,
  PermissionFlagsBits,
  ChannelType,
  EmbedBuilder,
  TextChannel,
} from 'discord.js';
import { upsertGuildConfig } from '../../database/repositories/guildConfigRepository.js';
import { createVerificationPanelMessage } from '../../services/onboarding.js';
import { createTicketPanelMessage } from '../../services/ticketManager.js';
import { COLORS, EMOJIS } from '../../config/constants.js';
import { logger } from '../../utils/logger.js';

export const data = new SlashCommandBuilder()
  .setName('setup')
  .setDescription('Interactive server onboarding setup wizard for STX Cloud Platform')
  .setDefaultMemberPermissions(PermissionFlagsBits.Administrator);

export async function execute(interaction: ChatInputCommandInteraction): Promise<void> {
  const guild = interaction.guild;
  if (!guild) return;

  await interaction.deferReply();

  logger.info({ guildId: guild.id, guildName: guild.name }, 'Running interactive guild setup wizard');

  try {
    // 1. Create or resolve Bot & Staff Category
    let staffCategory = guild.channels.cache.find(
      (c) => c.type === ChannelType.GuildCategory && c.name.toLowerCase().includes('staff')
    );
    if (!staffCategory) {
      staffCategory = await guild.channels.create({
        name: '🔒 Staff & Logs',
        type: ChannelType.GuildCategory,
        permissionOverwrites: [
          { id: guild.roles.everyone.id, deny: [PermissionFlagsBits.ViewChannel] },
          { id: guild.client.user?.id, allow: [PermissionFlagsBits.ViewChannel, PermissionFlagsBits.SendMessages] },
        ],
      });
    }

    // 2. Create Mod Log Channel
    let modLogChannel = guild.channels.cache.find(
      (c) => c.type === ChannelType.GuildText && c.name === 'mod-logs'
    ) as TextChannel;
    if (!modLogChannel) {
      modLogChannel = await guild.channels.create({
        name: 'mod-logs',
        type: ChannelType.GuildText,
        parent: staffCategory.id,
        permissionOverwrites: [
          { id: guild.roles.everyone.id, deny: [PermissionFlagsBits.ViewChannel] },
          { id: guild.client.user?.id, allow: [PermissionFlagsBits.ViewChannel, PermissionFlagsBits.SendMessages] },
        ],
      });
    }

    // 3. Create Honeypot Trap Channel
    // CRITICAL: Normal members CAN view and CAN send messages so scammers/bots trigger the trap!
    let honeypotChannel = guild.channels.cache.find(
      (c) => c.type === ChannelType.GuildText && (c.name === 'do-not-type-here' || c.name === 'bot-trap')
    ) as TextChannel;

    if (!honeypotChannel) {
      honeypotChannel = await guild.channels.create({
        name: 'do-not-type-here',
        type: ChannelType.GuildText,
        topic: '⚠️ TRAP CHANNEL - DO NOT POST HERE. Regular members are told never to type here. Anyone sending a message here is instantly banned.',
        permissionOverwrites: [
          {
            id: guild.roles.everyone.id,
            allow: [PermissionFlagsBits.ViewChannel, PermissionFlagsBits.SendMessages, PermissionFlagsBits.ReadMessageHistory],
            deny: [PermissionFlagsBits.AddReactions],
          },
          {
            id: guild.client.user?.id,
            allow: [PermissionFlagsBits.ViewChannel, PermissionFlagsBits.SendMessages, PermissionFlagsBits.ManageMessages],
          },
        ],
      });

      // Pin warning message
      const trapEmbed = new EmbedBuilder()
        .setColor(COLORS.DANGER)
        .setTitle('⚠️ RESTRICTED DECOY CHANNEL — DO NOT TYPE HERE')
        .setDescription(
          `**HUMANS: DO NOT SEND ANY MESSAGES IN THIS CHANNEL.**\n\n` +
            `This channel is a security honeypot trap to catch automated scam bots, token scrapers, and compromised accounts.\n\n` +
            `Posting any message here will result in an **instant automated ban** with message purging.`
        )
        .setFooter({ text: 'STX Automated Defense System' });

      const pinnedMsg = await honeypotChannel.send({ embeds: [trapEmbed] });
      await pinnedMsg.pin().catch(() => {});
    }

    // 4. Create Support Tickets Category
    let ticketCategory = guild.channels.cache.find(
      (c) => c.type === ChannelType.GuildCategory && c.name.toLowerCase().includes('tickets')
    );
    if (!ticketCategory) {
      ticketCategory = await guild.channels.create({
        name: '🎫 Support Tickets',
        type: ChannelType.GuildCategory,
      });
    }

    // 5. Create Ticket Panel Channel
    let openTicketChannel = guild.channels.cache.find(
      (c) => c.type === ChannelType.GuildText && (c.name === 'open-a-ticket' || c.name === 'support')
    ) as TextChannel;
    if (!openTicketChannel) {
      openTicketChannel = await guild.channels.create({
        name: 'open-a-ticket',
        type: ChannelType.GuildText,
        parent: ticketCategory.id,
        permissionOverwrites: [
          {
            id: guild.roles.everyone.id,
            allow: [PermissionFlagsBits.ViewChannel, PermissionFlagsBits.ReadMessageHistory],
            deny: [PermissionFlagsBits.SendMessages],
          },
          {
            id: guild.client.user?.id,
            allow: [PermissionFlagsBits.ViewChannel, PermissionFlagsBits.SendMessages],
          },
        ],
      });

      const panel = await createTicketPanelMessage();
      await openTicketChannel.send(panel);
    }

    // 6. Create Suggestions Channel
    let suggestionChannel = guild.channels.cache.find(
      (c) => c.type === ChannelType.GuildText && c.name === 'suggestions'
    ) as TextChannel;
    if (!suggestionChannel) {
      suggestionChannel = await guild.channels.create({
        name: 'suggestions',
        type: ChannelType.GuildText,
        topic: 'Community feature requests and ideas. Submit using /suggest <idea>',
      });
    }

    // 7. Upsert configuration in Supabase database
    await upsertGuildConfig(guild.id, {
      name: guild.name,
      mod_log_channel_id: modLogChannel.id,
      honeypot_channel_id: honeypotChannel.id,
      ticket_category_id: ticketCategory.id,
      ticket_panel_channel_id: openTicketChannel.id,
      suggestion_channel_id: suggestionChannel.id,
      spam_filter_enabled: true,
      phishing_filter_enabled: true,
      invite_filter_enabled: true,
    });

    const successEmbed = new EmbedBuilder()
      .setColor(COLORS.SUCCESS)
      .setTitle(`${EMOJIS.SUCCESS} STX Platform Server Setup Complete!`)
      .setDescription(
        `All essential channels, roles, and automated security modules have been provisioned.\n\n` +
          `**Configured Infrastructure:**\n` +
          `• **Mod Log**: <#${modLogChannel.id}>\n` +
          `• **Honeypot Trap**: <#${honeypotChannel.id}> *(Send Messages enabled with clear warning)*\n` +
          `• **Tickets Category**: ${ticketCategory.name}\n` +
          `• **Open Ticket Desk**: <#${openTicketChannel.id}>\n` +
          `• **Suggestions**: <#${suggestionChannel.id}>\n\n` +
          `You can fine-tune roles and thresholds using \`/config\`.`
      )
      .setFooter({ text: 'STX Enterprises Cloud Platform' })
      .setTimestamp();

    await interaction.editReply({ embeds: [successEmbed] });
  } catch (err: any) {
    logger.error({ err }, 'Error during setup wizard execution');
    await interaction.editReply({
      content: `${EMOJIS.ERROR} Failed to complete setup: ${err.message}. Please verify the bot has Manage Channels and Manage Roles permissions.`,
    });
  }
}
