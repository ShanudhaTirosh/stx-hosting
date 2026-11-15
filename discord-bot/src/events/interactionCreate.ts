import {
  Interaction,
  ModalBuilder,
  TextInputBuilder,
  TextInputStyle,
  ActionRowBuilder,
  ButtonInteraction,
  StringSelectMenuInteraction,
} from 'discord.js';
import { getAllCommands } from '../commands/index.js';
import { getGuildConfig } from '../database/repositories/guildConfigRepository.js';
import { handleVerificationButton } from '../services/onboarding.js';
import {
  openUserTicket,
  handleTicketClaim,
  handleTicketUnclaim,
  handleTicketClose,
} from '../services/ticketManager.js';
import { updateTicket } from '../database/repositories/ticketRepository.js';
import { updateSuggestionStatus } from '../database/repositories/faqAndSuggestionRepository.js';
import { EMOJIS } from '../config/constants.js';
import { logger } from '../utils/logger.js';

const commands = getAllCommands();

export async function onInteractionCreate(interaction: Interaction): Promise<void> {
  // 1. SLASH COMMANDS & CONTEXT MENUS
  if (interaction.isChatInputCommand() || interaction.isContextMenuCommand()) {
    const command = commands.get(interaction.commandName);
    if (!command) {
      logger.warn({ commandName: interaction.commandName }, 'Unknown command invoked');
      return;
    }

    try {
      await command.execute(interaction);
    } catch (err: any) {
      logger.error({ err: err.message, command: interaction.commandName }, 'Command execution error');
      const errorMsg = `${EMOJIS.ERROR} An unexpected error occurred while processing this command.`;
      if (interaction.deferred || interaction.replied) {
        await interaction.followUp({ content: errorMsg, ephemeral: true }).catch(() => {});
      } else {
        await interaction.reply({ content: errorMsg, ephemeral: true }).catch(() => {});
      }
    }
    return;
  }

  // 2. STRING SELECT MENUS (e.g. Ticket Topic Selection)
  if (interaction.isStringSelectMenu()) {
    if (interaction.customId === 'menu_ticket_topic') {
      const topic = interaction.values[0];
      const guild = interaction.guild;
      if (!guild) return;

      const config = await getGuildConfig(guild.id);
      if (!config) {
        await interaction.reply({ content: 'Server not configured.', ephemeral: true });
        return;
      }

      await interaction.deferReply({ ephemeral: true });
      const result = await openUserTicket(guild, interaction.user, topic, config);
      await interaction.editReply({ content: result.message });
    }
    return;
  }

  // 3. BUTTON INTERACTIONS
  if (interaction.isButton()) {
    const guild = interaction.guild;
    const config = guild ? await getGuildConfig(guild.id) : null;

    // A. Verification Button
    if (interaction.customId === 'btn_verify_member') {
      if (!config) return;
      await handleVerificationButton(interaction, config);
      return;
    }

    // B. Ticket Claim
    if (interaction.customId === 'btn_ticket_claim') {
      if (!config) return;
      await handleTicketClaim(interaction, config);
      return;
    }

    // C. Ticket Unclaim
    if (interaction.customId === 'btn_ticket_unclaim') {
      await handleTicketUnclaim(interaction);
      return;
    }

    // D. Ticket Close (Open Modal for Reason)
    if (interaction.customId === 'btn_ticket_close') {
      const modal = new ModalBuilder()
        .setCustomId('modal_ticket_close')
        .setTitle('Close Support Ticket');

      const reasonInput = new TextInputBuilder()
        .setCustomId('ticket_close_reason')
        .setLabel('Reason for closing')
        .setPlaceholder('e.g. Issue resolved, customer satisfied')
        .setStyle(TextInputStyle.Paragraph)
        .setRequired(false)
        .setMaxLength(500);

      const actionRow = new ActionRowBuilder<TextInputBuilder>().addComponents(reasonInput);
      modal.addComponents(actionRow);

      await interaction.showModal(modal);
      return;
    }

    // E. Customer Satisfaction Rating (e.g. rate_<ticketId>_<rating>)
    if (interaction.customId.startsWith('rate_')) {
      const parts = interaction.customId.split('_');
      const ticketId = parts[1];
      const rating = parseInt(parts[2], 10);

      if (ticketId && rating >= 1 && rating <= 5) {
        await updateTicket(ticketId, { rating });
        await interaction.reply({
          content: `${EMOJIS.SUCCESS} Thank you for your feedback! You rated this support experience **${rating} ⭐**.`,
          ephemeral: true,
        });
      }
      return;
    }

    // F. Suggestion Upvote / Downvote
    if (interaction.customId === 'btn_vote_up' || interaction.customId === 'btn_vote_down') {
      // In interactive mode, acknowledge ephemeral feedback
      await interaction.reply({
        content: `${EMOJIS.SUCCESS} Your vote has been recorded!`,
        ephemeral: true,
      });
      return;
    }
  }

  // 4. MODAL SUBMISSIONS
  if (interaction.isModalSubmit()) {
    if (interaction.customId === 'modal_ticket_close') {
      const reason = interaction.fields.getTextInputValue('ticket_close_reason') || 'Resolved by user/staff';
      const guild = interaction.guild;
      const config = guild ? await getGuildConfig(guild.id) : null;
      if (!config) return;

      await handleTicketClose(interaction as any, reason, config);
    }
  }
}
