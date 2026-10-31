<?php

namespace Database\Seeders;

use App\Models\Commerce\PricingTier;
use App\Models\Commerce\Product;
use App\Models\Commerce\ProductVariant;
use Illuminate\Database\Seeder;

class HostingCatalogSeeder extends Seeder
{
    public function run(): void
    {
        // =========================================================================
        // 1. VPS Products (Category: vps)
        // =========================================================================
        $vpsProduct = Product::updateOrCreate(
            ['slug' => 'cloud-vps'],
            [
                'name' => 'High Performance Cloud VPS',
                'category' => 'vps',
                'description' => 'Unmanaged, enterprise-grade cloud VPS instances with dedicated NVMe storage, manual provisioning, and multi-region availability.',
                'icon_url' => '/images/logos/server.svg',
                'is_popular' => true,
                'badge' => 'High Performance',
                'is_active' => true,
            ]
        );

        $vpsPlans = [
            [
                'name' => 'Starter Cloud VPS',
                'slug' => 'cx22',
                'vcpu' => 2,
                'ram_mb' => 4096,
                'disk_gb' => 40,
                'disk_type' => 'nvme',
                'bandwidth_gb' => 20000,
                'price_cents_30' => 750, // $7.50
                'price_cents_50' => 1200, // $12.00
                'price_lkr_30' => 240000,
                'price_lkr_50' => 380000,
                'is_popular' => false,
                'badge' => 'Starter',
                'specs' => [
                    'cpu' => '2 vCPU x86',
                    'ram' => '4 GB ECC RAM',
                    'storage' => '40 GB Gen4 NVMe',
                    'bandwidth' => '20 TB Traffic',
                    'port' => '1 Gbps Dedicated',
                    'location' => 'Multi-Region (US / EU / Asia)',
                    'ipv4' => '1 Dedicated IPv4 + /64 IPv6',
                ],
            ],
            [
                'name' => 'Production Cloud VPS',
                'slug' => 'cx32',
                'vcpu' => 4,
                'ram_mb' => 8192,
                'disk_gb' => 80,
                'disk_type' => 'nvme',
                'bandwidth_gb' => 20000,
                'price_cents_30' => 1450, // $14.50
                'price_cents_50' => 2300, // $23.00
                'price_lkr_30' => 460000,
                'price_lkr_50' => 730000,
                'is_popular' => true,
                'badge' => 'Most Popular',
                'specs' => [
                    'cpu' => '4 vCPU x86',
                    'ram' => '8 GB ECC RAM',
                    'storage' => '80 GB Gen4 NVMe',
                    'bandwidth' => '20 TB Traffic',
                    'port' => '1 Gbps Dedicated',
                    'location' => 'Multi-Region (US / EU / Asia)',
                    'ipv4' => '1 Dedicated IPv4 + /64 IPv6',
                ],
            ],
            [
                'name' => 'Compute Pro VPS',
                'slug' => 'cx42',
                'vcpu' => 8,
                'ram_mb' => 16384,
                'disk_gb' => 160,
                'disk_type' => 'nvme',
                'bandwidth_gb' => 20000,
                'price_cents_30' => 2850, // $28.50
                'price_cents_50' => 4500, // $45.00
                'price_lkr_30' => 910000,
                'price_lkr_50' => 1430000,
                'is_popular' => false,
                'badge' => 'High Concurrency',
                'specs' => [
                    'cpu' => '8 vCPU x86',
                    'ram' => '16 GB ECC RAM',
                    'storage' => '160 GB Gen4 NVMe',
                    'bandwidth' => '20 TB Traffic',
                    'port' => '1 Gbps Dedicated',
                    'location' => 'Multi-Region (US / EU / Asia)',
                    'ipv4' => '1 Dedicated IPv4 + /64 IPv6',
                ],
            ],
            [
                'name' => 'Enterprise Cluster VPS',
                'slug' => 'cx52',
                'vcpu' => 16,
                'ram_mb' => 32768,
                'disk_gb' => 320,
                'disk_type' => 'nvme',
                'bandwidth_gb' => 20000,
                'price_cents_30' => 5650, // $56.50
                'price_cents_50' => 8900, // $89.00
                'price_lkr_30' => 1800000,
                'price_lkr_50' => 2830000,
                'is_popular' => false,
                'badge' => 'Enterprise',
                'specs' => [
                    'cpu' => '16 vCPU x86',
                    'ram' => '32 GB ECC RAM',
                    'storage' => '320 GB Gen4 NVMe',
                    'bandwidth' => '20 TB Traffic',
                    'port' => '1 Gbps Dedicated',
                    'location' => 'Multi-Region (US / EU / Asia)',
                    'ipv4' => '1 Dedicated IPv4 + /64 IPv6',
                ],
            ],
        ];

        foreach ($vpsPlans as $planData) {
            $variant = ProductVariant::updateOrCreate(
                ['product_id' => $vpsProduct->id, 'slug' => $planData['slug']],
                [
                    'name' => $planData['name'],
                    'vcpu' => $planData['vcpu'],
                    'ram_mb' => $planData['ram_mb'],
                    'disk_gb' => $planData['disk_gb'],
                    'disk_type' => $planData['disk_type'],
                    'bandwidth_gb' => $planData['bandwidth_gb'],
                    'specs_json' => $planData['specs'],
                    'is_active' => true,
                ]
            );

            // 30-day, 50-day, and monthly tiers
            PricingTier::updateOrCreate(
                ['product_variant_id' => $variant->id, 'billing_type' => '30-day'],
                ['price_cents' => $planData['price_cents_30'], 'price_lkr_cents' => $planData['price_lkr_30'], 'setup_fee_cents' => 0, 'is_active' => true]
            );
            PricingTier::updateOrCreate(
                ['product_variant_id' => $variant->id, 'billing_type' => '50-day'],
                ['price_cents' => $planData['price_cents_50'], 'price_lkr_cents' => $planData['price_lkr_50'], 'setup_fee_cents' => 0, 'is_active' => true]
            );
            PricingTier::updateOrCreate(
                ['product_variant_id' => $variant->id, 'billing_type' => 'monthly'],
                ['price_cents' => $planData['price_cents_30'], 'price_lkr_cents' => $planData['price_lkr_30'], 'setup_fee_cents' => 0, 'is_active' => true]
            );
        }

        // =========================================================================
        // 2. Game Server Hosting (Category: game_servers)
        // =========================================================================
        $gameProduct = Product::updateOrCreate(
            ['slug' => 'game-servers'],
            [
                'name' => 'Game Server Hosting',
                'category' => 'game_servers',
                'description' => 'Low-latency, high-tickrate dedicated game server allocations with advanced DDoS mitigation and instant setup.',
                'icon_url' => '/images/logos/gamepad.svg',
                'is_popular' => true,
                'badge' => 'High Tickrate',
                'is_active' => true,
            ]
        );

        $gamePlans = [
            [
                'name' => 'Minecraft Paper/Forge Node',
                'slug' => 'game-minecraft',
                'vcpu' => 4,
                'ram_mb' => 8192,
                'disk_gb' => 50,
                'price_cents' => 1200, // $12.00
                'price_lkr' => 380000,
                'specs' => [
                    'cpu' => '4 vCPU High-Clock',
                    'ram' => '8 GB DDR5 RAM',
                    'storage' => '50 GB NVMe Storage',
                    'slots' => 'Unlimited Player Slots',
                    'ddos' => 'Game-Specific Anti-DDoS Filter',
                    'backups' => 'Automated Hourly World Backups',
                ],
            ],
            [
                'name' => 'Rust Survival Server',
                'slug' => 'game-rust',
                'vcpu' => 8,
                'ram_mb' => 16384,
                'disk_gb' => 100,
                'price_cents' => 2400, // $24.00
                'price_lkr' => 760000,
                'specs' => [
                    'cpu' => '8 vCPU High-Performance',
                    'ram' => '16 GB DDR5 RAM',
                    'storage' => '100 GB NVMe Storage',
                    'tickrate' => 'Native 60 Tickrate Guarantee',
                    'ddos' => 'Layer 7 Filtering + Oxide Mod Support',
                    'wipe' => '1-Click Scheduled Map Wipe Automation',
                ],
            ],
            [
                'name' => 'Palworld Dedicated Host',
                'slug' => 'game-palworld',
                'vcpu' => 8,
                'ram_mb' => 16384,
                'disk_gb' => 80,
                'price_cents' => 2000, // $20.00
                'price_lkr' => 640000,
                'specs' => [
                    'cpu' => '8 vCPU Dedicated',
                    'ram' => '16 GB ECC High-Speed RAM',
                    'storage' => '80 GB NVMe Storage',
                    'memory_leak' => 'Auto Memory Recycling Watchdog',
                    'players' => '32 Concurrent Player Support',
                    'support' => '24/7 Discord Technical Assistance',
                ],
            ],
            [
                'name' => 'CS2 128-Tick Competitive Node',
                'slug' => 'game-cs2',
                'vcpu' => 4,
                'ram_mb' => 8192,
                'disk_gb' => 60,
                'price_cents' => 1500, // $15.00
                'price_lkr' => 480000,
                'specs' => [
                    'cpu' => '4 vCPU Ultra-Low Latency',
                    'ram' => '8 GB High-Frequency RAM',
                    'storage' => '60 GB Gen4 NVMe',
                    'tickrate' => '128 Tickrate Competitive Engine',
                    'location' => 'Direct BGP Routing to Singapore & Frankfurt',
                    'fastdl' => 'Integrated FastDL HTTP Content Mirror',
                ],
            ],
        ];

        foreach ($gamePlans as $planData) {
            $variant = ProductVariant::updateOrCreate(
                ['product_id' => $gameProduct->id, 'slug' => $planData['slug']],
                [
                    'name' => $planData['name'],
                    'vcpu' => $planData['vcpu'],
                    'ram_mb' => $planData['ram_mb'],
                    'disk_gb' => $planData['disk_gb'],
                    'disk_type' => 'nvme',
                    'bandwidth_gb' => 10000,
                    'specs_json' => $planData['specs'],
                    'is_active' => true,
                ]
            );

            PricingTier::updateOrCreate(
                ['product_variant_id' => $variant->id, 'billing_type' => 'monthly'],
                ['price_cents' => $planData['price_cents'], 'price_lkr_cents' => $planData['price_lkr'], 'setup_fee_cents' => 0, 'is_active' => true]
            );
        }

        // =========================================================================
        // 3. Application Hosting (Category: app_hosting)
        // =========================================================================
        $appProduct = Product::updateOrCreate(
            ['slug' => 'app-hosting'],
            [
                'name' => 'PaaS Application Hosting',
                'category' => 'app_hosting',
                'description' => 'STX-powered container deployment platform with automatic SSL, custom domains, and Git integration.',
                'icon_url' => '/svgs/stx-logo.svg',
                'is_popular' => true,
                'badge' => 'Instant Deploy',
                'is_active' => true,
            ]
        );

        $appPlans = [
            [
                'name' => 'Starter Container',
                'slug' => 'app-starter',
                'vcpu' => 1,
                'ram_mb' => 512,
                'disk_gb' => 10,
                'price_cents' => 250, // $2.50
                'price_lkr' => 80000,
                'specs' => [
                    'cpu' => '0.5 vCPU Shared',
                    'ram' => '512 MB Container RAM',
                    'storage' => '10 GB NVMe Persistent Volume',
                    'domains' => '1 Custom Domain with Auto SSL',
                    'bandwidth' => '1 TB Traffic',
                    'git' => 'GitHub & GitLab Webhooks',
                ],
            ],
            [
                'name' => 'Standard Container',
                'slug' => 'app-standard',
                'vcpu' => 1,
                'ram_mb' => 1024,
                'disk_gb' => 25,
                'price_cents' => 500, // $5.00
                'price_lkr' => 160000,
                'specs' => [
                    'cpu' => '1.0 vCPU Dedicated',
                    'ram' => '1 GB Container RAM',
                    'storage' => '25 GB NVMe Persistent Volume',
                    'domains' => '3 Custom Domains with Auto SSL',
                    'bandwidth' => '5 TB Traffic',
                    'git' => 'Continuous Deployment + Rollbacks',
                ],
            ],
            [
                'name' => 'Pro Container',
                'slug' => 'app-pro',
                'vcpu' => 2,
                'ram_mb' => 2048,
                'disk_gb' => 50,
                'price_cents' => 1000, // $10.00
                'price_lkr' => 320000,
                'specs' => [
                    'cpu' => '2.0 vCPU Dedicated',
                    'ram' => '2 GB Container RAM',
                    'storage' => '50 GB NVMe Persistent Volume',
                    'domains' => 'Unlimited Custom Domains',
                    'bandwidth' => '10 TB Traffic',
                    'git' => 'Priority Queue + Automated Backups',
                ],
            ],
        ];

        foreach ($appPlans as $planData) {
            $variant = ProductVariant::updateOrCreate(
                ['product_id' => $appProduct->id, 'slug' => $planData['slug']],
                [
                    'name' => $planData['name'],
                    'vcpu' => $planData['vcpu'],
                    'ram_mb' => $planData['ram_mb'],
                    'disk_gb' => $planData['disk_gb'],
                    'disk_type' => 'nvme',
                    'bandwidth_gb' => 5000,
                    'specs_json' => $planData['specs'],
                    'is_active' => true,
                ]
            );

            PricingTier::updateOrCreate(
                ['product_variant_id' => $variant->id, 'billing_type' => 'monthly'],
                ['price_cents' => $planData['price_cents'], 'price_lkr_cents' => $planData['price_lkr'], 'setup_fee_cents' => 0, 'is_active' => true]
            );
        }

        // =========================================================================
        // 4. 24/7 Bot Hosting (Category: bot_hosting)
        // =========================================================================
        $botProduct = Product::updateOrCreate(
            ['slug' => 'bot-hosting'],
            [
                'name' => '24/7 Bot Hosting',
                'category' => 'bot_hosting',
                'description' => 'Continuous high-availability runtime containers for Discord.js, Python bots, Baileys WhatsApp gateways, and Telegram daemons.',
                'icon_url' => '/images/logos/bot.svg',
                'is_popular' => true,
                'badge' => '24/7 Online',
                'is_active' => true,
            ]
        );

        $botPlans = [
            [
                'name' => 'Discord Micro Daemon',
                'slug' => 'bot-micro',
                'vcpu' => 1,
                'ram_mb' => 512,
                'disk_gb' => 5,
                'price_cents' => 150, // $1.50
                'price_lkr' => 48000,
                'specs' => [
                    'cpu' => '0.5 vCPU Shared',
                    'ram' => '512 MB RAM',
                    'storage' => '5 GB NVMe Persistent Volume',
                    'uptime' => '99.9% Uptime Guarantee with Auto-Restart',
                    'logs' => 'Real-Time WebSocket Log Stream',
                ],
            ],
            [
                'name' => 'Multi-Bot Production Cluster',
                'slug' => 'bot-cluster',
                'vcpu' => 2,
                'ram_mb' => 2048,
                'disk_gb' => 20,
                'price_cents' => 450, // $4.50
                'price_lkr' => 145000,
                'specs' => [
                    'cpu' => '2.0 vCPU Dedicated',
                    'ram' => '2 GB RAM',
                    'storage' => '20 GB NVMe Storage',
                    'bots' => 'Run Up to 8 Independent Bot Daemons',
                    'persistence' => 'Full WhatsApp Session QR State Persistence',
                ],
            ],
        ];

        foreach ($botPlans as $planData) {
            $variant = ProductVariant::updateOrCreate(
                ['product_id' => $botProduct->id, 'slug' => $planData['slug']],
                [
                    'name' => $planData['name'],
                    'vcpu' => $planData['vcpu'],
                    'ram_mb' => $planData['ram_mb'],
                    'disk_gb' => $planData['disk_gb'],
                    'disk_type' => 'nvme',
                    'bandwidth_gb' => 2000,
                    'specs_json' => $planData['specs'],
                    'is_active' => true,
                ]
            );

            PricingTier::updateOrCreate(
                ['product_variant_id' => $variant->id, 'billing_type' => 'monthly'],
                ['price_cents' => $planData['price_cents'], 'price_lkr_cents' => $planData['price_lkr'], 'setup_fee_cents' => 0, 'is_active' => true]
            );
        }

        // =========================================================================
        // 5. Web Hosting & CMS (Category: web_hosting)
        // =========================================================================
        $webProduct = Product::updateOrCreate(
            ['slug' => 'managed-web-hosting'],
            [
                'name' => 'Managed Web & WordPress Hosting',
                'category' => 'web_hosting',
                'description' => 'Fast, optimized web hosting for WordPress, blogs, portfolios, and static websites with automated SSL certificates and CDN caching.',
                'icon_url' => '/images/logos/wordpress.svg',
                'is_popular' => true,
                'badge' => 'Free SSL & CDN',
                'is_active' => true,
            ]
        );

        $webPlans = [
            [
                'name' => 'Personal Web Suite',
                'slug' => 'web-personal',
                'vcpu' => 1,
                'ram_mb' => 1024,
                'disk_gb' => 15,
                'price_cents' => 300, // $3.00
                'price_lkr' => 96000,
                'specs' => [
                    'cpu' => '1 vCPU Shared',
                    'ram' => '1 GB RAM',
                    'storage' => '15 GB NVMe Storage',
                    'websites' => '1 WordPress or Static Website',
                    'ssl' => 'Free Wildcard Let\'s Encrypt SSL',
                    'bandwidth' => 'Unmetered Standard Traffic',
                ],
            ],
            [
                'name' => 'Business Web Cluster',
                'slug' => 'web-business',
                'vcpu' => 2,
                'ram_mb' => 4096,
                'disk_gb' => 50,
                'price_cents' => 800, // $8.00
                'price_lkr' => 256000,
                'specs' => [
                    'cpu' => '2 vCPU Dedicated',
                    'ram' => '4 GB ECC RAM',
                    'storage' => '50 GB NVMe Storage',
                    'websites' => 'Up to 5 Websites with Isolated Containers',
                    'cache' => 'Built-in Redis Object Cache for WordPress',
                    'backups' => 'Daily Remote S3 Snapshot Backups',
                ],
            ],
        ];

        foreach ($webPlans as $planData) {
            $variant = ProductVariant::updateOrCreate(
                ['product_id' => $webProduct->id, 'slug' => $planData['slug']],
                [
                    'name' => $planData['name'],
                    'vcpu' => $planData['vcpu'],
                    'ram_mb' => $planData['ram_mb'],
                    'disk_gb' => $planData['disk_gb'],
                    'disk_type' => 'nvme',
                    'bandwidth_gb' => 5000,
                    'specs_json' => $planData['specs'],
                    'is_active' => true,
                ]
            );

            PricingTier::updateOrCreate(
                ['product_variant_id' => $variant->id, 'billing_type' => 'monthly'],
                ['price_cents' => $planData['price_cents'], 'price_lkr_cents' => $planData['price_lkr'], 'setup_fee_cents' => 0, 'is_active' => true]
            );
        }

        // =========================================================================
        // 6. Managed Databases (Category: database)
        // =========================================================================
        $dbProduct = Product::updateOrCreate(
            ['slug' => 'managed-databases'],
            [
                'name' => 'Managed Cloud Databases',
                'category' => 'database',
                'description' => 'Fully managed standalone PostgreSQL, MySQL, and Redis engines with automated backups and private VPC networking.',
                'icon_url' => '/images/logos/postgresql.svg',
                'is_popular' => false,
                'badge' => 'Managed DB',
                'is_active' => true,
            ]
        );

        $dbPlans = [
            [
                'name' => 'PostgreSQL 16 Engine',
                'slug' => 'db-postgres',
                'vcpu' => 1,
                'ram_mb' => 1024,
                'disk_gb' => 20,
                'price_cents' => 600, // $6.00
                'price_lkr' => 190000,
                'specs' => ['engine' => 'PostgreSQL 16', 'ram' => '1 GB', 'storage' => '20 GB NVMe', 'backups' => 'Daily S3 Backups'],
            ],
            [
                'name' => 'MySQL 8.4 Engine',
                'slug' => 'db-mysql',
                'vcpu' => 1,
                'ram_mb' => 1024,
                'disk_gb' => 20,
                'price_cents' => 600, // $6.00
                'price_lkr' => 190000,
                'specs' => ['engine' => 'MySQL 8.4 LTS', 'ram' => '1 GB', 'storage' => '20 GB NVMe', 'backups' => 'Daily S3 Backups'],
            ],
            [
                'name' => 'Redis 7.4 In-Memory Cache',
                'slug' => 'db-redis',
                'vcpu' => 1,
                'ram_mb' => 512,
                'disk_gb' => 5,
                'price_cents' => 400, // $4.00
                'price_lkr' => 130000,
                'specs' => ['engine' => 'Redis 7.4', 'ram' => '512 MB In-Memory', 'storage' => '5 GB Persistence', 'persistence' => 'AOF + RDB'],
            ],
        ];

        foreach ($dbPlans as $planData) {
            $variant = ProductVariant::updateOrCreate(
                ['product_id' => $dbProduct->id, 'slug' => $planData['slug']],
                [
                    'name' => $planData['name'],
                    'vcpu' => $planData['vcpu'],
                    'ram_mb' => $planData['ram_mb'],
                    'disk_gb' => $planData['disk_gb'],
                    'disk_type' => 'nvme',
                    'bandwidth_gb' => 2000,
                    'specs_json' => $planData['specs'],
                    'is_active' => true,
                ]
            );

            PricingTier::updateOrCreate(
                ['product_variant_id' => $variant->id, 'billing_type' => 'monthly'],
                ['price_cents' => $planData['price_cents'], 'price_lkr_cents' => $planData['price_lkr'], 'setup_fee_cents' => 0, 'is_active' => true]
            );
        }
    }
}
