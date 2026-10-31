<?php

namespace App\Livewire\Storefront;

use Livewire\Component;

class FaqsPage extends Component
{
    public string $activeCategory = 'all';
    public string $searchQuery = '';
    public ?int $openFaqIndex = 0;

    public function setCategory(string $category): void
    {
        $this->activeCategory = $category;
    }

    public function toggleFaq(int $index): void
    {
        $this->openFaqIndex = ($this->openFaqIndex === $index) ? null : $index;
    }

    public function render()
    {
        $allFaqs = [
            // Category: VPS
            [
                'category' => 'vps',
                'q' => 'How fast are Cloud VPS instances provisioned after payment approval?',
                'a' => 'Cloud VPS compute instances are provisioned automatically within 15 seconds after payment verification. Your dedicated IPv4, SSH port, and root credentials appear immediately in your Control Panel dashboard.',
            ],
            [
                'category' => 'vps',
                'q' => 'What operating systems are supported on Cloud VPS?',
                'a' => 'We support Ubuntu 24.04 LTS, Ubuntu 22.04 LTS, Debian 12, AlmaLinux 9, Rocky Linux 9, Alpine Linux, and custom ISO uploads.',
            ],
            [
                'category' => 'vps',
                'q' => 'Do I get full root access and dedicated IPv4 address?',
                'a' => 'Yes. Every Cloud VPS allocation includes full root access via SSH, dedicated IPv4 address, customizable rDNS, and raw kernel access.',
            ],
            [
                'category' => 'vps',
                'q' => 'Can I reinstall or format my VPS operating system at any time?',
                'a' => 'Yes. You can trigger instant OS re-installs, root password resets, and reboot cycles directly from the Control Panel or Web Terminal.',
            ],

            // Category: App Hosting
            [
                'category' => 'app',
                'q' => 'How does code deployment from GitHub or ZIP archives work?',
                'a' => 'Connect your GitHub repository or upload a compressed .zip archive. Our edge build engine automatically compiles your code, detects package dependencies, provisions Docker containers, and attaches custom Let\'s Encrypt SSL certificates.',
            ],
            [
                'category' => 'app',
                'q' => 'Which web application frameworks and runtimes are supported?',
                'a' => 'We support Node.js (Express, Next.js, Remix, NestJS), Python (Django, FastAPI, Flask), Go, Rust, static HTML/Tailwind frontends, and custom Dockerfile / Docker Compose setups.',
            ],
            [
                'category' => 'app',
                'q' => 'Can I point my own custom domain to hosted web applications?',
                'a' => 'Yes! Add your custom domain (e.g. app.yourdomain.com) in your Control Panel, point CNAME/A records to our Anycast gateway, and automated SSL certificates will be issued instantly.',
            ],

            // Category: Bot Hosting
            [
                'category' => 'bot',
                'q' => 'Are Discord, WhatsApp, and Telegram bots hosted 24/7 without sleeping?',
                'a' => 'Yes! Bot hosting instances run continuous daemons with process supervisors (PM2 / Docker healthchecks). If a bot crashes due to network disconnects, it is automatically rebooted in milliseconds.',
            ],
            [
                'category' => 'bot',
                'q' => 'How do WhatsApp Baileys bots keep QR code session state persistent?',
                'a' => 'WhatsApp Baileys bot instances utilize persistent volume mounts. Your QR code session credentials remain safely saved across container reboots so you never need to re-scan.',
            ],
            [
                'category' => 'bot',
                'q' => 'Where do I safely store DISCORD_TOKEN and API keys?',
                'a' => 'All sensitive tokens and session credentials are saved into your project\'s Encrypted Secrets Vault (AES-256-GCM) and injected securely into your container environment at runtime.',
            ],

            // Category: Databases
            [
                'category' => 'db',
                'q' => 'Which managed database engines are available?',
                'a' => 'We offer Managed PostgreSQL 16 (with pgvector), MySQL 8, Redis 7 (In-Memory Session Cache), and MongoDB 6 document databases.',
            ],
            [
                'category' => 'db',
                'q' => 'How are database backups performed and stored?',
                'a' => 'Automated daily database snapshot backups are taken and encrypted in Cloudflare R2 object storage with 1-click snapshot restore points.',
            ],

            // Category: Billing
            [
                'category' => 'billing',
                'q' => 'What payment methods are supported on STX?',
                'a' => 'We accept Crypto / Binance USDT, Visa, Mastercard, ezCash, Genie, and Sri Lankan local bank transfer receipts uploaded with automated review workflows.',
            ],
            [
                'category' => 'billing',
                'q' => 'What is your Uptime SLA Guarantee?',
                'a' => 'We provide a 99.9% network and compute Uptime SLA backed by multi-region redundant transit providers and real-time public telemetry monitoring.',
            ],
        ];

        $filtered = collect($allFaqs)->filter(function ($faq) {
            $catMatch = ($this->activeCategory === 'all' || $faq['category'] === $this->activeCategory);
            $searchMatch = empty($this->searchQuery) ||
                str_contains(strtolower($faq['q']), strtolower($this->searchQuery)) ||
                str_contains(strtolower($faq['a']), strtolower($this->searchQuery));

            return $catMatch && $searchMatch;
        })->values()->all();

        return view('livewire.storefront.faqs-page', [
            'faqs' => $filtered,
        ])->layout('layouts.storefront', [
            'title' => 'Frequently Asked Questions & Knowledge Base — STX Enterprises',
        ]);
    }
}

