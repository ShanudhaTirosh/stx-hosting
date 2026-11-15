import {
  Guild,
  User,
  ChannelType,
  PermissionFlagsBits,
  TextChannel,
  EmbedBuilder,
  ActionRowBuilder,
  ButtonBuilder,
  ButtonStyle,
  ButtonInteraction,
  StringSelectMenuBuilder,
  AttachmentBuilder,
} from 'discord.js';
import { GuildConfig } from '../database/repositories/guildConfigRepository.js';
import {
  createTicket,
  getActiveTicketsCount,
  getTicketByChannel,
  updateTicket,
  getTicketMessages,
  TicketRecord,
} from '../database/repositories/ticketRepository.js';
import { generateHtmlTranscript, generateTextTranscript } from './transcriptGenerator.js';
import { COLORS, EMOJIS, TICKET_TOPICS } from '../config/constants.js';
import { env } from '../config/env.js';
import { logger } from '../utils/logger.js';

export async function createTicketPanelMessage(): Promise<{
  embeds: EmbedBuilder[];
  components: ActionRowBuilder<StringSelectMenuBuilder>[];
}> {
  const embed = new EmbedBuilder()
    .setColor(COLORS.STX_BLUE)
    .setTitle(`${EMOJIS.TICKET} STX Customer Support Desk`)
    .setDescription(
      `Need technical support, have questions about your services, or want help with billing?\n\n` +
        `Select the relevant category from the dropdown menu below to open a private ticket with our staff.\n\n` +
        `**Support Categories:**\n` +
        `💳 **Billing & Invoices**: Bank transfers, deposit slips, renewal queries\n` +
        `🖥️ **Cloud VPS & Infra**: VPS setups, SSH, specifications, firewalls\n` +
        `🎮 **Game Servers**: Palworld, Minecraft, Rust configurations\n` +
        `🌐 **Web & App Hosting**: Container deployments, domains, SSL\n` +
        `📧 **Mail Accounts**: WildDuck email addresses, DNS/DKIM setup\n` +
        `❓ **Other Support**: General inquiries and sales`
    )
    .setFooter({ text: 'STX Enterprises Cloud Platform • Guaranteed SLA' });

  const selectMenu = new StringSelectMenuBuilder()
    .setCustomId('menu_ticket_topic')
    .setPlaceholder('Select a support category to open a ticket...')
    .addOptions(
      TICKET_TOPICS.map((t) => ({
        label: t.label,
        value: t.value,
        description: t.description,
        emoji: t.emoji,
      }))
    );

  const row = new ActionRowBuilder<StringSelectMenuBuilder>().addComponents(selectMenu);
  return { embeds: [embed], components: [row] };
}

export async function openUserTicket(
  guild: Guild,
  user: User,
  topic: string,
  config: GuildConfig,
  initialSubject?: string
): Promise<{ success: boolean; channel?: TextChannel; message: string }> {
  // 1. Check max open tickets limit
  const activeCount = await getActiveTicketsCount(guild.id, user.id);
  const maxAllowed = env.MAX_OPEN_TICKETS_PER_USER;

  if (activeCount >= maxAllowed) {
    return {
      success: false,
      message: `${EMOJIS.WARNING} You already have **${activeCount} open ticket(s)**. Please resolve your existing tickets before opening a new one.`,
    };
  }

  // 2. Prepare permissions
  const permissionOverwrites: any[] = [
    {
      id: guild.roles.everyone.id,
      deny: [PermissionFlagsBits.ViewChannel],
    },
    {
      id: user.id,
      allow: [
        PermissionFlagsBits.ViewChannel,
        PermissionFlagsBits.SendMessages,
        PermissionFlagsBits.AttachFiles,
        PermissionFlagsBits.ReadMessageHistory,
      ],
    },
    {
      id: guild.client.user?.id,
      allow: [
        PermissionFlagsBits.ViewChannel,
        PermissionFlagsBits.SendMessages,
        PermissionFlagsBits.ManageChannels,
        PermissionFlagsBits.ManageMessages,
        PermissionFlagsBits.EmbedLinks,
        PermissionFlagsBits.AttachFiles,
        PermissionFlagsBits.ReadMessageHistory,
      ],
    },
  ];

  // Add staff roles permissions
  for (const roleId of config.staff_role_ids || []) {
    permissionOverwrites.push({
      id: roleId,
      allow: [
        PermissionFlagsBits.ViewChannel,
        PermissionFlagsBits.SendMessages,
        PermissionFlagsBits.AttachFiles,
        PermissionFlagsBits.ReadMessageHistory,
        PermissionFlagsBits.ManageMessages,
      ],
    });
  }

  const topicConfig = TICKET_TOPICS.find((t) => t.value === topic);
  const topicLabel = topicConfig ? topicConfig.label : topic;

  // 3. Create private channel
  let channel: TextChannel;
  try {
    channel = await guild.channels.create({
      name: `ticket-${user.username.slice(0, 10)}-temp`,
      type: ChannelType.GuildText,
      parent: config.ticket_category_id || undefined,
      permissionOverwrites,
      topic: `Support Ticket for ${user.tag} | Topic: ${topicLabel} | Status: Open`,
    });
  } catch (err) {
    logger.error({ err, userId: user.id }, 'Failed to create ticket channel');
    return { success: false, message: `${EMOJIS.ERROR} Failed to create ticket channel. Please check bot permissions.` };
  }

  // 4. Create database ticket record
  const ticket = await createTicket({
    guild_id: guild.id,
    channel_id: channel.id,
    user_id: user.id,
    topic,
  });

  if (!ticket) {
    await channel.delete().catch(() => {});
    return { success: false, message: `${EMOJIS.ERROR} Database error initializing ticket record.` };
  }

  // Rename channel to formatted ticket number
  const formattedNumber = String(ticket.ticket_number).padStart(4, '0');
  await channel.setName(`ticket-${formattedNumber}`).catch(() => {});

  // 5. Send initial welcome and control panel inside ticket channel
  const welcomeEmbed = new EmbedBuilder()
    .setColor(COLORS.STX_BLUE)
    .setTitle(`${EMOJIS.TICKET} Ticket #${formattedNumber}: ${topicLabel}`)
    .setDescription(
      `Hello <@${user.id}>! Thank you for contacting STX Enterprises Support.\n\n` +
        `**Topic**: ${topicLabel}\n` +
        (initialSubject ? `**Initial Details**: ${initialSubject}\n\n` : `\n`) +
        `A support engineer will claim your ticket shortly. Please describe your issue in detail, including any relevant order numbers or server hostnames.`
    )
    .addFields(
      { name: 'Created By', value: `<@${user.id}>`, inline: true },
      { name: 'Status', value: '🟢 Open / Awaiting Staff', inline: true },
      { name: 'Priority', value: '⚡ Standard', inline: true }
    )
    .setFooter({ text: 'Staff can claim this ticket using the button below' })
    .setTimestamp();

  const claimBtn = new ButtonBuilder()
    .setCustomId('btn_ticket_claim')
    .setLabel('Claim Ticket')
    .setEmoji(EMOJIS.PIN)
    .setStyle(ButtonStyle.Primary);

  const closeBtn = new ButtonBuilder()
    .setCustomId('btn_ticket_close')
    .setLabel('Close Ticket')
    .setEmoji(EMOJIS.LOCK)
    .setStyle(ButtonStyle.Danger);

  const row = new ActionRowBuilder<ButtonBuilder>().addComponents(claimBtn, closeBtn);

  await channel.send({ content: `<@${user.id}> Welcome!`, embeds: [welcomeEmbed], components: [row] });

  return { success: true, channel, message: `${EMOJIS.SUCCESS} Ticket created successfully in <#${channel.id}>!` };
}

export async function handleTicketClaim(interaction: ButtonInteraction, config: GuildConfig): Promise<void> {
  const channel = interaction.channel as TextChannel;
  if (!channel) return;

  const ticket = await getTicketByChannel(channel.id);
  if (!ticket) {
    await interaction.reply({ content: 'Ticket not found in database.', ephemeral: true });
    return;
  }

  if (ticket.status === 'claimed' && ticket.claimed_by_id) {
    await interaction.reply({
      content: `${EMOJIS.WARNING} This ticket is already claimed by <@${ticket.claimed_by_id}>.`,
      ephemeral: true,
    });
    return;
  }

  // Update in DB
  const now = new Date().toISOString();
  await updateTicket(ticket.id, {
    status: 'claimed',
    claimed_by_id: interaction.user.id,
    claimed_at: now,
  });

  // Update channel topic
  await channel.setTopic(
    `Ticket #${ticket.ticket_number} | Topic: ${ticket.topic} | Claimed by ${interaction.user.tag}`
  ).catch(() => {});

  const unclaimBtn = new ButtonBuilder()
    .setCustomId('btn_ticket_unclaim')
    .setLabel('Unclaim')
    .setEmoji(EMOJIS.UNLOCK)
    .setStyle(ButtonStyle.Secondary);

  const closeBtn = new ButtonBuilder()
    .setCustomId('btn_ticket_close')
    .setLabel('Close Ticket')
    .setEmoji(EMOJIS.LOCK)
    .setStyle(ButtonStyle.Danger);

  const row = new ActionRowBuilder<ButtonBuilder>().addComponents(unclaimBtn, closeBtn);

  const claimEmbed = new EmbedBuilder()
    .setColor(COLORS.SUCCESS)
    .setDescription(`${EMOJIS.PIN} **Ticket Claimed!** <@${interaction.user.id}> has claimed this ticket and will be assisting you.`)
    .setTimestamp();

  await interaction.reply({ embeds: [claimEmbed] });
}

export async function handleTicketUnclaim(interaction: ButtonInteraction): Promise<void> {
  const channel = interaction.channel as TextChannel;
  if (!channel) return;

  const ticket = await getTicketByChannel(channel.id);
  if (!ticket) return;

  await updateTicket(ticket.id, {
    status: 'open',
    claimed_by_id: null,
    claimed_at: null,
  });

  await channel.setTopic(`Ticket #${ticket.ticket_number} | Topic: ${ticket.topic} | Status: Open`).catch(() => {});

  await interaction.reply({
    content: `${EMOJIS.UNLOCK} Ticket has been unclaimed and returned to the open queue.`,
  });
}

export async function handleTicketClose(
  interaction: ButtonInteraction,
  reason: string,
  config: GuildConfig
): Promise<void> {
  const channel = interaction.channel as TextChannel;
  if (!channel) return;

  const ticket = await getTicketByChannel(channel.id);
  if (!ticket) {
    await interaction.reply({ content: 'Ticket not found.', ephemeral: true });
    return;
  }

  await interaction.reply({
    content: `${EMOJIS.LOCK} Generating transcript and closing ticket #${ticket.ticket_number}...`,
  });

  // 1. Fetch messages
  const messages = await getTicketMessages(ticket.id);

  // 2. Generate transcripts
  const textTranscript = generateTextTranscript(ticket, messages);
  const htmlTranscript = generateHtmlTranscript(ticket, messages);

  // 3. Update database record
  const now = new Date().toISOString();
  await updateTicket(ticket.id, {
    status: 'closed',
    closed_at: now,
    closed_by_id: interaction.user.id,
    close_reason: reason,
    transcript_text: textTranscript,
  });

  const htmlBuffer = Buffer.from(htmlTranscript, 'utf-8');
  const transcriptAttachment = new AttachmentBuilder(htmlBuffer, {
    name: `transcript-ticket-${ticket.ticket_number}.html`,
  });

  // 4. Send transcript & rating survey to customer DM
  try {
    const customer = await interaction.client.users.fetch(ticket.user_id);
    if (customer) {
      const dmEmbed = new EmbedBuilder()
        .setColor(COLORS.STX_BLUE)
        .setTitle(`${EMOJIS.TICKET} Ticket #${ticket.ticket_number} Closed`)
        .setDescription(
          `Your support ticket regarding **${ticket.topic}** has been marked as closed.\n\n` +
            `**Close Reason**: ${reason || 'Resolved'}\n` +
            `**Closed By**: <@${interaction.user.id}>\n\n` +
            `A copy of your conversation transcript is attached to this message.`
        )
        .setFooter({ text: 'Thank you for choosing STX Enterprises!' })
        .setTimestamp();

      const ratingRow = new ActionRowBuilder<ButtonBuilder>().addComponents(
        new ButtonBuilder().setCustomId(`rate_${ticket.id}_1`).setLabel('1 ⭐').setStyle(ButtonStyle.Secondary),
        new ButtonBuilder().setCustomId(`rate_${ticket.id}_2`).setLabel('2 ⭐').setStyle(ButtonStyle.Secondary),
        new ButtonBuilder().setCustomId(`rate_${ticket.id}_3`).setLabel('3 ⭐').setStyle(ButtonStyle.Secondary),
        new ButtonBuilder().setCustomId(`rate_${ticket.id}_4`).setLabel('4 ⭐').setStyle(ButtonStyle.Secondary),
        new ButtonBuilder().setCustomId(`rate_${ticket.id}_5`).setLabel('5 ⭐').setStyle(ButtonStyle.Success)
      );

      await customer.send({
        embeds: [dmEmbed],
        files: [transcriptAttachment],
        components: [ratingRow],
      });
    }
  } catch (err) {
    logger.warn({ err, userId: ticket.user_id }, 'Could not deliver ticket transcript to customer DM');
  }

  // 5. Send transcript to staff ticket log channel
  if (config.ticket_log_channel_id) {
    try {
      const logChannel = interaction.guild?.channels.cache.get(config.ticket_log_channel_id) as TextChannel;
      if (logChannel) {
        const logEmbed = new EmbedBuilder()
          .setColor(COLORS.DARK)
          .setTitle(`${EMOJIS.CLIPBOARD} Ticket #${ticket.ticket_number} Resolved & Closed`)
          .addFields(
            { name: 'Creator', value: `<@${ticket.user_id}>`, inline: true },
            { name: 'Claimed By', value: ticket.claimed_by_id ? `<@${ticket.claimed_by_id}>` : 'Unclaimed', inline: true },
            { name: 'Closed By', value: `<@${interaction.user.id}>`, inline: true },
            { name: 'Topic', value: ticket.topic, inline: true },
            { name: 'Reason', value: reason || 'Resolved', inline: true },
            { name: 'Messages Count', value: `${messages.length}`, inline: true }
          )
          .setTimestamp();

        await logChannel.send({ embeds: [logEmbed], files: [transcriptAttachment] });
      }
    } catch (err) {
      logger.error({ err }, 'Failed to log ticket transcript to log channel');
    }
  }

  // 6. Delete channel after 5-second countdown
  setTimeout(async () => {
    try {
      await channel.delete();
    } catch (err) {
      logger.error({ err }, 'Failed to delete ticket channel');
    }
  }, 5000);
}
