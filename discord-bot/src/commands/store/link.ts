import {
  SlashCommandBuilder,
  ChatInputCommandInteraction,
  EmbedBuilder,
  ActionRowBuilder,
  ButtonBuilder,
  ButtonStyle,
} from 'discord.js';
import { createLinkCode } from '../../database/repositories/accountLinkRepository.js';
import { COLORS, EMOJIS } from '../../config/constants.js';

export const data = new SlashCommandBuilder()
  .setName('link')
  .setDescription('Link your Discord account to your STX Enterprises Cloud Platform profile');

export async function execute(interaction: ChatInputCommandInteraction): Promise<void> {
  await interaction.deferReply({ ephemeral: true });

  try {
    const code = await createLinkCode(interaction.user.id);

    const embed = new EmbedBuilder()
      .setColor(COLORS.STX_BLUE)
      .setTitle(`${EMOJIS.LOCK} Link Your Discord Account`)
      .setDescription(
        `To receive automated invoice DM delivery, order notifications, and verified customer roles:\n\n` +
          `1. Copy your secure one-time verification code below.\n` +
          `2. Go to your **STX Customer Portal -> Account Settings -> Integrations**.\n` +
          `3. Enter the code and click **Verify Account**.\n\n` +
          `Your One-Time Code: \`\`\`\n${code}\n\`\`\`\n` +
          `*(This code will expire in **10 minutes**)*`
      )
      .setFooter({ text: 'STX Enterprises Cloud Platform' })
      .setTimestamp();

    const row = new ActionRowBuilder<ButtonBuilder>().addComponents(
      new ButtonBuilder()
        .setLabel('Go to Customer Portal')
        .setURL('http://localhost:8000/portal/account')
        .setStyle(ButtonStyle.Link)
    );

    await interaction.editReply({ embeds: [embed], components: [row] });
  } catch (err: any) {
    await interaction.editReply({
      content: `${EMOJIS.ERROR} Failed to generate linking code: ${err.message}`,
    });
  }
}
