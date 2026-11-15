export const COLORS = {
  PRIMARY: 0x5865f2,    // Blurple
  SUCCESS: 0x57f287,    // Green
  WARNING: 0xfee75c,    // Yellow
  DANGER: 0xed4245,     // Red
  DARK: 0x2b2d31,       // Dark Charcoal
  STX_BLUE: 0x0284c7,   // STX Sky Blue
} as const;

export const EMOJIS = {
  SUCCESS: '✅',
  ERROR: '❌',
  WARNING: '⚠️',
  LOCK: '🔒',
  UNLOCK: '🔓',
  TICKET: '🎫',
  PIN: '📌',
  STORE: '🛒',
  SERVER: '🖥️',
  DATABASE: '🗄️',
  MAIL: '📧',
  BILLING: '💳',
  SHIELD: '🛡️',
  STAR: '⭐',
  CLIPBOARD: '📋',
  ARROW: '➡️',
} as const;

export const TICKET_TOPICS = [
  { label: 'Billing & Invoices', value: 'billing', description: 'Payments, invoices, renewals, and refunds', emoji: '💳' },
  { label: 'Cloud VPS & Infra', value: 'vps', description: 'Virtual servers, SSH, resources, and networking', emoji: '🖥️' },
  { label: 'Game Server Hosting', value: 'game_hosting', description: 'Palworld, Minecraft, Rust, and game setups', emoji: '🎮' },
  { label: 'Web & App Hosting', value: 'web_hosting', description: 'Docker apps, Git deploys, and domain routing', emoji: '🌐' },
  { label: 'Mail & Email Accounts', value: 'mail', description: 'WildDuck mailboxes, DNS, and SPF/DKIM records', emoji: '📧' },
  { label: 'General / Other', value: 'other', description: 'General questions or unlisted topics', emoji: '❓' },
] as const;

export const KNOWN_PHISHING_DOMAINS = [
  'discorcl-gift',
  'dlscord-nitro',
  'discorcl.com',
  'discord-nitro.gift',
  'discord-app.net',
  'steamcommuniity',
  'steamcommunity-trade',
  'steam-nitro',
  'roblox-robux-free',
  'airdrop-token-claim',
];
