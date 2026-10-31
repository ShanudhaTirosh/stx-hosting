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
        // 1. VPS Products
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

            // 30-Day Tier
            PricingTier::updateOrCreate(
                ['product_variant_id' => $variant->id, 'billing_type' => '30-day'],
                [
                    'price_cents' => $planData['price_cents_30'],
                    'price_lkr_cents' => $planData['price_lkr_30'],
                    'setup_fee_cents' => 0,
                    'is_active' => true,
                ]
            );

            // 50-Day Tier
            PricingTier::updateOrCreate(
                ['product_variant_id' => $variant->id, 'billing_type' => '50-day'],
                [
                    'price_cents' => $planData['price_cents_50'],
                    'price_lkr_cents' => $planData['price_lkr_50'],
                    'setup_fee_cents' => 0,
                    'is_active' => true,
                ]
            );

            // Monthly standard tier
            PricingTier::updateOrCreate(
                ['product_variant_id' => $variant->id, 'billing_type' => 'monthly'],
                [
                    'price_cents' => $planData['price_cents_30'],
                    'price_lkr_cents' => $planData['price_lkr_30'],
                    'setup_fee_cents' => 0,
                    'is_active' => true,
                ]
            );
        }

        // =========================================================================
        // 2. Web Hosting / Application Hosting Products
        // =========================================================================
        $appProduct = Product::updateOrCreate(
            ['slug' => 'app-hosting'],
            [
                'name' => 'PaaS Application Hosting',
                'category' => 'web_hosting',
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
                [
                    'price_cents' => $planData['price_cents'],
                    'price_lkr_cents' => $planData['price_lkr'],
                    'setup_fee_cents' => 0,
                    'is_active' => true,
                ]
            );
        }

        // =========================================================================
        // 3. Managed Database Products
        // =========================================================================
        $dbProduct = Product::updateOrCreate(
            ['slug' => 'managed-databases'],
            [
                'name' => 'Managed Cloud Databases',
                'category' => 'database',
                'description' => 'Fully managed standalone PostgreSQL, MySQL, and Redis engines with automated backups.',
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
                [
                    'price_cents' => $planData['price_cents'],
                    'price_lkr_cents' => $planData['price_lkr'],
                    'setup_fee_cents' => 0,
                    'is_active' => true,
                ]
            );
        }
    }
}
