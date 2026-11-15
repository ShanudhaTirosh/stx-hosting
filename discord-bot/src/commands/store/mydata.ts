import {
  SlashCommandBuilder,
  ChatInputCommandInteraction,
  EmbedBuilder,
} from 'discord.js';
import { deleteAccountLink } from '../../database/repositories/accountLinkRepository.js';
import { COLORS, EMOJIS } from '../../config/constants.js';

export const data = new SlashCommandBuilder()
  .setName('mydata')
  .setDescription('Privacy & data retention management')
  .addSubcommand((sub) =>
    sub
      .setName('delete')
      .setDescription('Request deletion of your linked Discord account data and personal records')
  );

export async function execute(interaction: ChatInputCommandInteraction): Promise<void> {
  await interaction.deferReply({ ephemeral: true });

  const success = await deleteAccountLink(interaction.user.id);

  if (success) {
    const embed = new EmbedBuilder()
      .setColor(COLORS.SUCCESS)
      .setTitle(`${EMOJIS.SHIELD} Privacy Data Request Processed`)
      .setDescription(
        `Your linked Discord credentials, integration tokens, and automated delivery records have been permanently purged from our database in accordance with our data retention policy.\n\n` +
          `If you wish to re-link your account in the future, you can run \`/link\` at any time.`
      )
      .setTimestamp();

    await interaction.editReply({ embeds: [embed] });
  } else {
    await interaction.editReply({
      content: `${EMOJIS.WARNING} No linked Discord account record was found for your user.`,
    });
  }
}
