import {
  GuildMember,
  TextChannel,
  EmbedBuilder,
  ActionRowBuilder,
  ButtonBuilder,
  ButtonStyle,
  ButtonInteraction,
} from 'discord.js';
import { GuildConfig } from '../database/repositories/guildConfigRepository.js';
import { COLORS, EMOJIS } from '../config/constants.js';
import { logger } from '../utils/logger.js';

export async function handleMemberJoin(member: GuildMember, config: GuildConfig): Promise<void> {
  const guild = member.guild;
  const user = member.user;

  // 1. Account Age Check (Quarantine alert)
  const minAgeDays = config.min_account_age_days || 0;
  if (minAgeDays > 0) {
    const ageDays = (Date.now() - user.createdTimestamp) / (86400 * 1000);
    if (ageDays < minAgeDays) {
      logger.warn({ user: user.tag, ageDays, minAgeDays }, 'Young account joined server');
      if (config.mod_log_channel_id) {
        try {
          const modChannel = guild.channels.cache.get(config.mod_log_channel_id) as TextChannel;
          if (modChannel) {
            const embed = new EmbedBuilder()
              .setColor(COLORS.WARNING)
              .setTitle(`${EMOJIS.WARNING} Young Account Alert`)
              .setDescription(
                `Member ${user.tag} (\`${user.id}\`) joined, but account is only **${ageDays.toFixed(1)} days old** (Threshold: ${minAgeDays} days).\n` +
                  `Watch for potential alt or bot activity.`
              )
              .setThumbnail(user.displayAvatarURL())
              .setTimestamp();
            await modChannel.send({ embeds: [embed] });
          }
        } catch (err) {
          logger.error({ err }, 'Failed to send young account alert');
        }
      }
    }
  }

  // 2. Auto-Role Assignment
  if (config.verification_type === 'none' && config.default_role_id) {
    try {
      await member.roles.add(config.default_role_id);
    } catch (err) {
      logger.error({ err, role: config.default_role_id }, 'Failed to assign default role on join');
    }
  }

  // 3. Welcome Message Dispatch
  if (config.welcome_channel_id) {
    try {
      const welcomeChannel = guild.channels.cache.get(config.welcome_channel_id) as TextChannel;
      if (welcomeChannel) {
        const rawMessage =
          config.welcome_message ||
          'Welcome to **{guild}**, {user}! Please check out our rules and enjoy your stay. (Member #{count})';

        const formatted = rawMessage
          .replace(/{user}/g, `<@${user.id}>`)
          .replace(/{guild}/g, guild.name)
          .replace(/{count}/g, `${guild.memberCount}`);

        if (config.welcome_embed_enabled) {
          const welcomeEmbed = new EmbedBuilder()
            .setColor(COLORS.STX_BLUE)
            .setTitle(`${EMOJIS.SUCCESS} Welcome to ${guild.name}!`)
            .setDescription(formatted)
            .setThumbnail(user.displayAvatarURL())
            .addFields(
              { name: 'Member Number', value: `#${guild.memberCount}`, inline: true },
              { name: 'Account Age', value: `<t:${Math.floor(user.createdTimestamp / 1000)}:R>`, inline: true }
            )
            .setFooter({ text: 'STX Enterprises Cloud Platform Community' })
            .setTimestamp();

          await welcomeChannel.send({ embeds: [welcomeEmbed] });
        } else {
          await welcomeChannel.send({ content: formatted });
        }
      }
    } catch (err) {
      logger.error({ err }, 'Failed to send welcome message');
    }
  }
}

export async function handleMemberLeave(member: GuildMember, config: GuildConfig): Promise<void> {
  if (!config.goodbye_channel_id) return;

  try {
    const goodbyeChannel = member.guild.channels.cache.get(config.goodbye_channel_id) as TextChannel;
    if (goodbyeChannel) {
      const rawMessage =
        config.goodbye_message ||
        '**{user}** has left the server. Member count is now {count}.';

      const formatted = rawMessage
        .replace(/{user}/g, member.user.tag)
        .replace(/{guild}/g, member.guild.name)
        .replace(/{count}/g, `${member.guild.memberCount}`);

      const embed = new EmbedBuilder()
        .setColor(COLORS.DARK)
        .setDescription(formatted)
        .setTimestamp();

      await goodbyeChannel.send({ embeds: [embed] });
    }
  } catch (err) {
    logger.error({ err }, 'Failed to send goodbye message');
  }
}

export async function createVerificationPanelMessage(): Promise<{
  embeds: EmbedBuilder[];
  components: ActionRowBuilder<ButtonBuilder>[];
}> {
  const embed = new EmbedBuilder()
    .setColor(COLORS.PRIMARY)
    .setTitle(`${EMOJIS.SHIELD} Server Member Verification`)
    .setDescription(
      `Welcome to our official community!\n\n` +
        `To access the channels and protect our members against automated bots and scammers, please verify your membership by clicking the button below.\n\n` +
        `By verifying, you agree to follow our community guidelines and rules.`
    )
    .setFooter({ text: 'STX Enterprises • Instant Verification' });

  const button = new ButtonBuilder()
    .setCustomId('btn_verify_member')
    .setLabel('Complete Verification')
    .setEmoji(EMOJIS.SUCCESS)
    .setStyle(ButtonStyle.Success);

  const row = new ActionRowBuilder<ButtonBuilder>().addComponents(button);

  return { embeds: [embed], components: [row] };
}

export async function handleVerificationButton(
  interaction: ButtonInteraction,
  config: GuildConfig
): Promise<void> {
  const member = interaction.member as GuildMember;
  if (!member) {
    await interaction.reply({ content: 'Could not resolve member.', ephemeral: true });
    return;
  }

  const roleToAssign = config.verified_role_id || config.default_role_id;
  if (!roleToAssign) {
    await interaction.reply({
      content: `${EMOJIS.WARNING} No verified role has been configured for this server yet. Please notify staff.`,
      ephemeral: true,
    });
    return;
  }

  if (member.roles.cache.has(roleToAssign)) {
    await interaction.reply({
      content: `${EMOJIS.SUCCESS} You are already verified!`,
      ephemeral: true,
    });
    return;
  }

  try {
    await member.roles.add(roleToAssign);
    await interaction.reply({
      content: `${EMOJIS.SUCCESS} **Verification Successful!** You now have full access to the server. Welcome!`,
      ephemeral: true,
    });
  } catch (err) {
    logger.error({ err, roleId: roleToAssign }, 'Failed to grant verified role');
    await interaction.reply({
      content: `${EMOJIS.ERROR} Failed to assign role due to role hierarchy. Please ensure the bot's role is positioned above the member role.`,
      ephemeral: true,
    });
  }
}
