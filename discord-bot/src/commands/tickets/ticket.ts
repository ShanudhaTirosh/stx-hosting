import {
  SlashCommandBuilder,
  ChatInputCommandInteraction,
  PermissionFlagsBits,
  TextChannel,
  EmbedBuilder,
} from 'discord.js';
import { getGuildConfig } from '../../database/repositories/guildConfigRepository.js';
import {
  openUserTicket,
  createTicketPanelMessage,
  handleTicketClaim,
  handleTicketUnclaim,
  handleTicketClose,
} from '../../services/ticketManager.js';
import { getTicketByChannel, updateTicket } from '../../database/repositories/ticketRepository.js';
import { COLORS, EMOJIS, TICKET_TOPICS } from '../../config/constants.js';

export const data = new SlashCommandBuilder()
  .setName('ticket')
  .setDescription('Support ticket management system')
  .addSubcommand((sub) =>
    sub
      .setName('open')
      .setDescription('Open a new private support ticket')
      .addStringOption((opt) =>
        opt
          .setName('topic')
          .setDescription('Ticket category')
          .setRequired(true)
          .addChoices(...TICKET_TOPICS.map((t) => ({ name: t.label, value: t.value })))
      )
      .addStringOption((opt) =>
        opt.setName('subject').setDescription('Brief summary of your question or issue').setRequired(false)
      )
  )
  .addSubcommand((sub) =>
    sub
      .setName('close')
      .setDescription('Close this support ticket with a reason and generate transcript')
      .addStringOption((opt) => opt.setName('reason').setDescription('Reason for closing').setRequired(false))
  )
  .addSubcommand((sub) => sub.setName('claim').setDescription('Staff: Claim this support ticket'))
  .addSubcommand((sub) => sub.setName('unclaim').setDescription('Staff: Release this ticket back to open pool'))
  .addSubcommand((sub) =>
    sub
      .setName('add')
      .setDescription('Add a user to this private ticket')
      .addUserOption((opt) => opt.setName('user').setDescription('User to add').setRequired(true))
  )
  .addSubcommand((sub) =>
    sub
      .setName('remove')
      .setDescription('Remove a user from this private ticket')
      .addUserOption((opt) => opt.setName('user').setDescription('User to remove').setRequired(true))
  )
  .addSubcommand((sub) =>
    sub
      .setName('priority')
      .setDescription('Staff: Update ticket priority level')
      .addStringOption((opt) =>
        opt
          .setName('level')
          .setDescription('Priority level')
          .setRequired(true)
          .addChoices(
            { name: 'Low', value: 'low' },
            { name: 'Standard', value: 'standard' },
            { name: 'High', value: 'high' },
            { name: 'Urgent', value: 'urgent' }
          )
      )
  )
  .addSubcommand((sub) =>
    sub.setName('panel').setDescription('Admin: Post the persistent interactive ticket panel in this channel')
  );

export async function execute(interaction: ChatInputCommandInteraction): Promise<void> {
  const subcommand = interaction.options.getSubcommand();
  const guild = interaction.guild;
  if (!guild) {
    await interaction.reply({ content: 'Command only works in a server.', ephemeral: true });
    return;
  }

  const config = await getGuildConfig(guild.id);
  if (!config) {
    await interaction.reply({
      content: `${EMOJIS.WARNING} Server has not been initialized. An administrator must run \`/setup\` first.`,
      ephemeral: true,
    });
    return;
  }

  // 1. SUBCOMMAND: OPEN
  if (subcommand === 'open') {
    const topic = interaction.options.getString('topic', true);
    const subject = interaction.options.getString('subject') || undefined;

    await interaction.deferReply({ ephemeral: true });

    const result = await openUserTicket(guild, interaction.user, topic, config, subject);
    await interaction.editReply({ content: result.message });
    return;
  }

  // 2. SUBCOMMAND: PANEL
  if (subcommand === 'panel') {
    if (!interaction.memberPermissions?.has(PermissionFlagsBits.ManageGuild)) {
      await interaction.reply({ content: `${EMOJIS.ERROR} You lack permission to deploy ticket panels.`, ephemeral: true });
      return;
    }

    const panel = await createTicketPanelMessage();
    await (interaction.channel as TextChannel).send(panel);
    await interaction.reply({ content: `${EMOJIS.SUCCESS} Ticket panel posted successfully!`, ephemeral: true });
    return;
  }

  // For other subcommands, we must be inside an active ticket channel
  const channel = interaction.channel as TextChannel;
  const ticket = await getTicketByChannel(channel.id);

  if (!ticket) {
    await interaction.reply({
      content: `${EMOJIS.ERROR} This command can only be used inside an active ticket channel.`,
      ephemeral: true,
    });
    return;
  }

  // 3. SUBCOMMAND: CLAIM
  if (subcommand === 'claim') {
    await handleTicketClaim(interaction as any, config);
    return;
  }

  // 4. SUBCOMMAND: UNCLAIM
  if (subcommand === 'unclaim') {
    await handleTicketUnclaim(interaction as any);
    return;
  }

  // 5. SUBCOMMAND: CLOSE
  if (subcommand === 'close') {
    const reason = interaction.options.getString('reason') || 'Resolved by user/staff';
    await handleTicketClose(interaction as any, reason, config);
    return;
  }

  // 6. SUBCOMMAND: ADD
  if (subcommand === 'add') {
    const targetUser = interaction.options.getUser('user', true);
    await channel.permissionOverwrites.create(targetUser.id, {
      ViewChannel: true,
      SendMessages: true,
      AttachFiles: true,
      ReadMessageHistory: true,
    });
    await interaction.reply({
      content: `${EMOJIS.SUCCESS} Added <@${targetUser.id}> to this ticket.`,
    });
    return;
  }

  // 7. SUBCOMMAND: REMOVE
  if (subcommand === 'remove') {
    const targetUser = interaction.options.getUser('user', true);
    await channel.permissionOverwrites.delete(targetUser.id);
    await interaction.reply({
      content: `${EMOJIS.SUCCESS} Removed <@${targetUser.id}> from this ticket.`,
    });
    return;
  }

  // 8. SUBCOMMAND: PRIORITY
  if (subcommand === 'priority') {
    const level = interaction.options.getString('level', true) as 'low' | 'standard' | 'high' | 'urgent';
    await updateTicket(ticket.id, { priority: level });
    const embed = new EmbedBuilder()
      .setColor(level === 'urgent' ? COLORS.DANGER : COLORS.WARNING)
      .setDescription(`${EMOJIS.WARNING} Ticket priority updated to **${level.toUpperCase()}** by <@${interaction.user.id}>.`);
    await interaction.reply({ embeds: [embed] });
  }
}
