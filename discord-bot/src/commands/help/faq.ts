import {
  SlashCommandBuilder,
  ChatInputCommandInteraction,
  EmbedBuilder,
  PermissionFlagsBits,
} from 'discord.js';
import { getFaq, listFaqs, upsertFaq } from '../../database/repositories/faqAndSuggestionRepository.js';
import { COLORS, EMOJIS } from '../../config/constants.js';

export const data = new SlashCommandBuilder()
  .setName('faq')
  .setDescription('Frequently Asked Questions knowledgebase')
  .addSubcommand((sub) =>
    sub
      .setName('show')
      .setDescription('Look up a quick answer by keyword')
      .addStringOption((opt) => opt.setName('keyword').setDescription('Topic keyword (e.g. refund, ssh, billing)').setRequired(true))
  )
  .addSubcommand((sub) => sub.setName('list').setDescription('List all available FAQ topics'))
  .addSubcommand((sub) =>
    sub
      .setName('set')
      .setDescription('Staff: Create or update an FAQ entry')
      .addStringOption((opt) => opt.setName('keyword').setDescription('Unique keyword (e.g. vps-login)').setRequired(true))
      .addStringOption((opt) => opt.setName('question').setDescription('Full question text').setRequired(true))
      .addStringOption((opt) => opt.setName('answer').setDescription('Answer text (supports markdown)').setRequired(true))
  );

export async function execute(interaction: ChatInputCommandInteraction): Promise<void> {
  const guild = interaction.guild;
  if (!guild) {
    await interaction.reply({ content: 'Only available in server.', ephemeral: true });
    return;
  }

  const subcommand = interaction.options.getSubcommand();

  // 1. SUBCOMMAND: SHOW
  if (subcommand === 'show') {
    const keyword = interaction.options.getString('keyword', true);
    const faq = await getFaq(guild.id, keyword);

    if (!faq) {
      await interaction.reply({
        content: `${EMOJIS.WARNING} No FAQ found for keyword **"${keyword}"**. Use \`/faq list\` to see available topics.`,
        ephemeral: true,
      });
      return;
    }

    const embed = new EmbedBuilder()
      .setColor(COLORS.STX_BLUE)
      .setTitle(`❓ ${faq.question}`)
      .setDescription(faq.answer)
      .setFooter({ text: `FAQ Keyword: ${faq.keyword}` })
      .setTimestamp();

    await interaction.reply({ embeds: [embed] });
    return;
  }

  // 2. SUBCOMMAND: LIST
  if (subcommand === 'list') {
    const faqs = await listFaqs(guild.id);
    if (faqs.length === 0) {
      await interaction.reply({
        content: `${EMOJIS.WARNING} No FAQs have been added to this server yet.`,
        ephemeral: true,
      });
      return;
    }

    const listText = faqs
      .map((f) => `• \`/faq show ${f.keyword}\` — **${f.question}**`)
      .join('\n');

    const embed = new EmbedBuilder()
      .setColor(COLORS.STX_BLUE)
      .setTitle(`${EMOJIS.CLIPBOARD} Available FAQ Topics`)
      .setDescription(listText)
      .setFooter({ text: 'Use /faq show <keyword> to read any answer' });

    await interaction.reply({ embeds: [embed] });
    return;
  }

  // 3. SUBCOMMAND: SET (Staff Only)
  if (subcommand === 'set') {
    if (!interaction.memberPermissions?.has(PermissionFlagsBits.ManageMessages)) {
      await interaction.reply({
        content: `${EMOJIS.ERROR} You lack permission to configure FAQs.`,
        ephemeral: true,
      });
      return;
    }

    const keyword = interaction.options.getString('keyword', true);
    const question = interaction.options.getString('question', true);
    const answer = interaction.options.getString('answer', true);

    await upsertFaq({
      guild_id: guild.id,
      keyword,
      question,
      answer,
      created_by: interaction.user.tag,
    });

    await interaction.reply({
      content: `${EMOJIS.SUCCESS} FAQ for **${keyword}** has been created/updated!`,
      ephemeral: true,
    });
  }
}
