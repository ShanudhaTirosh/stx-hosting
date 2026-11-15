import { Collection } from 'discord.js';

// Store Commands
import * as productsCmd from './store/products.js';
import * as storeCmd from './store/store.js';
import * as orderCmd from './store/order.js';
import * as linkCmd from './store/link.js';
import * as mydataCmd from './store/mydata.js';

// Ticket Commands
import * as ticketCmd from './tickets/ticket.js';

// Help Commands
import * as statusCmd from './help/status.js';
import * as faqCmd from './help/faq.js';
import * as suggestCmd from './help/suggest.js';
import * as helpCmd from './help/help.js';

// Moderation Commands
import * as warnCmd from './moderation/warn.js';
import * as timeoutCmd from './moderation/timeout.js';
import * as kickCmd from './moderation/kick.js';
import * as banCmd from './moderation/ban.js';
import * as unbanCmd from './moderation/unban.js';
import * as purgeCmd from './moderation/purge.js';
import * as modlogsCmd from './moderation/modlogs.js';
import * as noteCmd from './moderation/note.js';

// Admin & Protection Commands
import * as setupCmd from './admin/setup.js';
import * as configCmd from './admin/config.js';
import * as raidCmd from './protection/raid.js';

// Context Menus
import * as reportMessageCmd from './context/reportMessage.js';

export interface CommandModule {
  data: any;
  execute: (interaction: any) => Promise<void>;
}

export function getAllCommands(): Collection<string, CommandModule> {
  const commands = new Collection<string, CommandModule>();

  const list: CommandModule[] = [
    productsCmd,
    storeCmd,
    orderCmd,
    linkCmd,
    mydataCmd,
    ticketCmd,
    statusCmd,
    faqCmd,
    suggestCmd,
    helpCmd,
    warnCmd,
    timeoutCmd,
    kickCmd,
    banCmd,
    unbanCmd,
    purgeCmd,
    modlogsCmd,
    noteCmd,
    setupCmd,
    configCmd,
    raidCmd,
    reportMessageCmd,
  ];

  for (const cmd of list) {
    commands.set(cmd.data.name, cmd);
  }

  return commands;
}
