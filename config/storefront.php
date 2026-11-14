<?php

return [
    'name' => env('STOREFRONT_NAME', 'STX Hosting'),
    'parent_brand' => env('STOREFRONT_PARENT_BRAND', 'SHANUTECHX'),

    'support' => [
        'email' => env('STOREFRONT_SUPPORT_EMAIL'),
        'discord' => env('STOREFRONT_DISCORD_URL', 'https://discord.gg/shanutechx'),
        'whatsapp' => env('STOREFRONT_WHATSAPP'), // e.g. https://wa.me/94XXXXXXXXX
    ],

    /*
     | Marketing claims you can stand behind (uptime target, locations, hardware).
     | Rendered on the About page only when filled in. They are empty on purpose:
     | the storefront never states a number it cannot back up.
     | Example: ['Uptime target' => '99.9%', 'Locations' => 'Colombo, Helsinki']
     */
    'claims' => [],

    /*
     | Catalog categories in display order. `db` lists the products.category values
     | that belong to the group.
     */
    'categories' => [
        'vps' => [
            'label' => 'Cloud VPS',
            'route' => 'store.vps',
            'blurb' => 'A server of your own with root access. We deliver it by hand, usually within 24 to 72 hours of payment approval.',
            'db' => ['vps'],
        ],
        'app-hosting' => [
            'label' => 'App hosting',
            'route' => 'store.app-hosting',
            'blurb' => 'Deploy from Git, get a subdomain and a certificate. Runs the day your payment is approved.',
            'db' => ['app_hosting'],
        ],
        'web-hosting' => [
            'label' => 'Website hosting',
            'route' => 'store.web-hosting',
            'blurb' => 'Sites and storefronts on managed hosting, with your own domain.',
            'db' => ['web_hosting'],
        ],
        'email-hosting' => [
            'label' => 'Email hosting',
            'route' => 'store.email-hosting',
            'blurb' => 'Custom domain email with modern webmail, IMAP/SMTP, and shared storage quotas.',
            'db' => ['email_hosting', 'mail_hosting'],
        ],
        'bot-hosting' => [
            'label' => 'Bot hosting',
            'route' => 'store.bot-hosting',
            'blurb' => 'Discord, Telegram and WhatsApp bots that stay online and restart if they crash.',
            'db' => ['bot_hosting'],
        ],
        'databases' => [
            'label' => 'Databases',
            'route' => 'store.databases',
            'blurb' => 'Managed databases with scheduled backups.',
            'db' => ['database'],
        ],
        'game-servers' => [
            'label' => 'Game servers',
            'route' => 'store.games',
            'blurb' => 'Dedicated resources for game servers.',
            'db' => ['game_servers', 'game_server'],
        ],
        'v2ray' => [
            'label' => 'V2Ray',
            'route' => 'store.v2ray',
            'blurb' => 'Plans for V2Ray users.',
            'db' => ['v2ray'],
        ],
    ],
];
