<?php

namespace App\Services\Commerce;

use App\Models\Commerce\MailAddon;
use App\Models\Commerce\MailPlan;
use App\Models\Commerce\PricingTier;
use App\Models\Commerce\Product;
use App\Models\Commerce\ProductVariant;
use App\Models\TemplatePermission;
use Illuminate\Support\Str;

class HostingCatalogSeederService
{
    /**
     * Seed or update Git App Hosting, Curated Templates, and Business Email Hosting plans.
     */
    public static function seedAll(): void
    {
        self::seedGitAppPlans();
        self::seedCuratedTemplates();
        self::seedEmailHostingPlans();
    }

    /**
     * Ensure the 3 fixed-price Git App Hosting plans exist.
     */
    public static function seedGitAppPlans(): Product
    {
        $product = Product::firstOrCreate(
            ['slug' => 'git-app-hosting'],
            [
                'name' => 'Git App Hosting',
                'category' => 'app_hosting',
                'description' => 'Deploy custom web applications directly from your GitHub, GitLab, or Git repository with automatic HTTPS and fixed monthly pricing.',
                'icon_url' => '/images/logos/git.svg',
                'is_popular' => true,
                'badge' => 'Developer',
                'is_active' => true,
            ]
        );

        $plans = [
            [
                'name' => 'Starter Git App',
                'slug' => 'git-starter',
                'vcpu' => 1,
                'ram_mb' => 512,
                'disk_gb' => 5,
                'price_cents' => 500, // $5.00/mo
                'price_lkr_cents' => 160000, // LKR 1,600/mo
                'specs_json' => [
                    'label' => 'Eco / Starter',
                    'tagline' => 'Ideal for APIs, microservices, and static or lightweight dynamic sites.',
                    'recommended_for' => 'APIs, Bots, Lightweight Apps',
                ],
            ],
            [
                'name' => 'Standard Git App',
                'slug' => 'git-standard',
                'vcpu' => 1,
                'ram_mb' => 1024,
                'disk_gb' => 15,
                'price_cents' => 1000, // $10.00/mo
                'price_lkr_cents' => 320000, // LKR 3,200/mo
                'specs_json' => [
                    'label' => 'Standard / Growth',
                    'tagline' => 'Perfect for production web applications, Laravel, Next.js, and Node.js.',
                    'recommended_for' => 'Fullstack Web Apps, Laravel, Next.js',
                ],
            ],
            [
                'name' => 'Pro Git App',
                'slug' => 'git-pro',
                'vcpu' => 2,
                'ram_mb' => 2048,
                'disk_gb' => 30,
                'price_cents' => 2000, // $20.00/mo
                'price_lkr_cents' => 640000, // LKR 6,400/mo
                'specs_json' => [
                    'label' => 'Pro / Scale',
                    'tagline' => 'High performance for intensive apps, background workers, and higher concurrency.',
                    'recommended_for' => 'Heavy Apps, Background Workers, Scale',
                ],
            ],
        ];

        foreach ($plans as $planData) {
            $variant = ProductVariant::updateOrCreate(
                ['product_id' => $product->id, 'slug' => $planData['slug']],
                [
                    'name' => $planData['name'],
                    'vcpu' => $planData['vcpu'],
                    'ram_mb' => $planData['ram_mb'],
                    'disk_gb' => $planData['disk_gb'],
                    'specs_json' => $planData['specs_json'],
                    'is_active' => true,
                ]
            );

            PricingTier::updateOrCreate(
                ['product_variant_id' => $variant->id, 'billing_type' => 'monthly'],
                [
                    'price_cents' => $planData['price_cents'],
                    'price_lkr_cents' => $planData['price_lkr_cents'],
                    'setup_fee_cents' => 0,
                    'is_active' => true,
                ]
            );
        }

        return $product;
    }

    /**
     * Ensure default curated templates are populated in template_permissions.
     */
    public static function seedCuratedTemplates(): void
    {
        $templates = [
            [
                'template_name' => 'wordpress',
                'display_name' => 'WordPress',
                'category' => 'cms',
                'icon_url' => '/images/logos/wordpress.svg',
                'short_description' => 'The world\'s most popular website builder and blogging platform.',
                'default_port' => 80,
                'min_ram_mb' => 512,
                'min_vcpu' => 1.0,
                'is_featured' => true,
                'is_globally_enabled' => true,
                'is_allowed' => true,
                'metadata' => [
                    'exposable_services' => [
                        [
                            'service_name' => 'wordpress',
                            'friendly_name' => 'WordPress Website & Admin',
                            'description' => 'Public website and wp-admin administrative backend',
                            'internal_port' => 80,
                            'is_default' => true,
                            'subdomain_prefix' => '',
                            'access_type' => 'public',
                            'suggested' => true,
                            'env_bindings' => ['WP_HOME', 'WP_SITEURL'],
                        ],
                    ],
                ],
            ],
            [
                'template_name' => 'ghost',
                'display_name' => 'Ghost Publishing',
                'category' => 'blog',
                'icon_url' => '/images/logos/ghost.svg',
                'short_description' => 'Modern, elegant platform for newsletters, publications, and blogs.',
                'default_port' => 2368,
                'min_ram_mb' => 512,
                'min_vcpu' => 0.5,
                'is_featured' => true,
                'is_globally_enabled' => true,
                'is_allowed' => true,
                'metadata' => [
                    'exposable_services' => [
                        [
                            'service_name' => 'ghost',
                            'friendly_name' => 'Ghost Publication',
                            'description' => 'Public publication, member portal, and ghost admin',
                            'internal_port' => 2368,
                            'is_default' => true,
                            'subdomain_prefix' => '',
                            'access_type' => 'public',
                            'suggested' => true,
                            'env_bindings' => ['url'],
                        ],
                    ],
                ],
            ],
            [
                'template_name' => 'n8n',
                'display_name' => 'n8n Workflow Automation',
                'category' => 'automation',
                'icon_url' => '/images/logos/n8n.svg',
                'short_description' => 'Fair-code workflow automation tool to connect any app and automate tasks.',
                'default_port' => 5678,
                'min_ram_mb' => 1024,
                'min_vcpu' => 1.0,
                'is_featured' => true,
                'is_globally_enabled' => true,
                'is_allowed' => true,
                'metadata' => [
                    'exposable_services' => [
                        [
                            'service_name' => 'n8n',
                            'friendly_name' => 'Workflow Canvas & Webhooks',
                            'description' => 'Main automation engine, webhooks, and visual workflow builder',
                            'internal_port' => 5678,
                            'is_default' => true,
                            'subdomain_prefix' => '',
                            'access_type' => 'public',
                            'suggested' => true,
                            'env_bindings' => ['N8N_EDITOR_BASE_URL', 'WEBHOOK_URL', 'N8N_HOST'],
                        ],
                    ],
                ],
            ],
            [
                'template_name' => 'supabase',
                'display_name' => 'Supabase Backend Suite',
                'category' => 'backend',
                'icon_url' => '/images/logos/supabase.svg',
                'short_description' => 'Complete open source Firebase alternative with Postgres, Auth, APIs, and Studio.',
                'default_port' => 8000,
                'min_ram_mb' => 2048,
                'min_vcpu' => 2.0,
                'is_featured' => true,
                'is_globally_enabled' => true,
                'is_allowed' => true,
                'metadata' => [
                    'exposable_services' => [
                        [
                            'service_name' => 'supabase-kong',
                            'friendly_name' => 'Main API Gateway',
                            'description' => 'Unified endpoint for REST, GraphQL, Auth, Storage, and Realtime',
                            'internal_port' => 8000,
                            'is_default' => true,
                            'subdomain_prefix' => '',
                            'access_type' => 'public',
                            'suggested' => true,
                            'env_bindings' => [
                                'SUPABASE_PUBLIC_URL',
                                'NEXT_PUBLIC_SUPABASE_URL',
                                'API_EXTERNAL_URL',
                                'GOTRUE_SITE_URL',
                            ],
                        ],
                        [
                            'service_name' => 'supabase-studio',
                            'friendly_name' => 'Admin Studio Dashboard',
                            'description' => 'Web console for managing tables, SQL editor, and authentication',
                            'internal_port' => 3000,
                            'is_default' => false,
                            'subdomain_prefix' => 'studio',
                            'access_type' => 'admin_only',
                            'suggested' => true,
                            'env_bindings' => [
                                'STUDIO_DEFAULT_ORGANIZATION',
                                'STUDIO_DEFAULT_PROJECT',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'template_name' => 'minio',
                'display_name' => 'MinIO Object Storage',
                'category' => 'storage',
                'icon_url' => '/images/logos/minio.svg',
                'short_description' => 'High performance, S3 compatible enterprise object storage server.',
                'default_port' => 9000,
                'min_ram_mb' => 1024,
                'min_vcpu' => 1.0,
                'is_featured' => true,
                'is_globally_enabled' => true,
                'is_allowed' => true,
                'metadata' => [
                    'exposable_services' => [
                        [
                            'service_name' => 'minio',
                            'friendly_name' => 'S3 Object Storage API',
                            'description' => 'High-performance Amazon S3-compatible REST storage API',
                            'internal_port' => 9000,
                            'is_default' => true,
                            'subdomain_prefix' => 's3',
                            'access_type' => 'public',
                            'suggested' => true,
                            'env_bindings' => [
                                'MINIO_SERVER_URL',
                            ],
                        ],
                        [
                            'service_name' => 'minio',
                            'friendly_name' => 'MinIO Web Console',
                            'description' => 'Administrative dashboard for buckets, policies, and access keys',
                            'internal_port' => 9001,
                            'is_default' => false,
                            'subdomain_prefix' => 'console',
                            'access_type' => 'admin_only',
                            'suggested' => true,
                            'env_bindings' => [
                                'MINIO_BROWSER_REDIRECT_URL',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'template_name' => 'appwrite',
                'display_name' => 'Appwrite BaaS',
                'category' => 'backend',
                'icon_url' => '/images/logos/appwrite.svg',
                'short_description' => 'Complete backend solution for web, mobile, and flutter developers.',
                'default_port' => 80,
                'min_ram_mb' => 2048,
                'min_vcpu' => 2.0,
                'is_featured' => false,
                'is_globally_enabled' => true,
                'is_allowed' => true,
                'metadata' => [
                    'exposable_services' => [
                        [
                            'service_name' => 'appwrite',
                            'friendly_name' => 'Appwrite Gateway & Console',
                            'description' => 'Unified backend-as-a-service REST/GraphQL API and developer console',
                            'internal_port' => 80,
                            'is_default' => true,
                            'subdomain_prefix' => '',
                            'access_type' => 'public',
                            'suggested' => true,
                            'env_bindings' => [
                                '_APP_DNS_EXTERNAL',
                                '_APP_CONSOLE_HOSTNAMES',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'template_name' => 'nextcloud',
                'display_name' => 'Nextcloud Hub',
                'category' => 'cloud',
                'icon_url' => '/images/logos/nextcloud.svg',
                'short_description' => 'Self-hosted productivity and private cloud file storage.',
                'default_port' => 80,
                'min_ram_mb' => 1024,
                'min_vcpu' => 1.0,
                'is_featured' => false,
                'is_globally_enabled' => true,
                'is_allowed' => true,
            ],
            [
                'template_name' => 'strapi',
                'display_name' => 'Strapi Headless CMS',
                'category' => 'cms',
                'icon_url' => '/images/logos/strapi.svg',
                'short_description' => 'Leading open-source headless CMS to build customizable APIs effortlessly.',
                'default_port' => 1337,
                'min_ram_mb' => 1024,
                'min_vcpu' => 1.0,
                'is_featured' => false,
                'is_globally_enabled' => true,
                'is_allowed' => true,
            ],
            [
                'template_name' => 'plausible',
                'display_name' => 'Plausible Analytics',
                'category' => 'analytics',
                'icon_url' => '/images/logos/plausible.svg',
                'short_description' => 'Lightweight and privacy-friendly alternative to Google Analytics.',
                'default_port' => 8000,
                'min_ram_mb' => 512,
                'min_vcpu' => 0.5,
                'is_featured' => false,
                'is_globally_enabled' => true,
                'is_allowed' => true,
            ],
            [
                'template_name' => 'uptime-kuma',
                'display_name' => 'Uptime Kuma',
                'category' => 'monitoring',
                'icon_url' => '/images/logos/uptime-kuma.svg',
                'short_description' => 'Self-hosted monitoring tool with status pages and instant alerts.',
                'default_port' => 3001,
                'min_ram_mb' => 256,
                'min_vcpu' => 0.5,
                'is_featured' => true,
                'is_globally_enabled' => true,
                'is_allowed' => true,
            ],
            [
                'template_name' => 'orbit',
                'display_name' => 'Orbit Team Workspace',
                'category' => 'collaboration',
                'icon_url' => '/images/logos/orbit.svg',
                'short_description' => 'Self-hosted team workspace with tasks, docs, real-time chat, and MCP server.',
                'default_port' => 3000,
                'min_ram_mb' => 1024,
                'min_vcpu' => 1.0,
                'is_featured' => false,
                'is_globally_enabled' => false, // Curated: Admin enables in /admin/templates
                'is_allowed' => false,
            ],
            [
                'template_name' => 'hi-events',
                'display_name' => 'Hi.Events Platform',
                'category' => 'events',
                'icon_url' => '/images/logos/hi-events.svg',
                'short_description' => 'Open-source event management and ticket selling platform.',
                'default_port' => 8080,
                'min_ram_mb' => 1024,
                'min_vcpu' => 1.0,
                'is_featured' => false,
                'is_globally_enabled' => false, // Curated: Admin enables in /admin/templates
                'is_allowed' => false,
            ],
        ];

        foreach ($templates as $tmpl) {
            TemplatePermission::updateOrCreate(
                [
                    'template_name' => $tmpl['template_name'],
                    'team_id' => null,
                    'hosting_plan_id' => null,
                ],
                $tmpl
            );
        }
    }

    /**
     * Seed or update the 4 dedicated Business Email Hosting plans & add-ons.
     */
    public static function seedEmailHostingPlans(): Product
    {
        $product = Product::updateOrCreate(
            ['slug' => 'business-email'],
            [
                'name' => 'Business Email Hosting',
                'category' => 'email_hosting',
                'description' => 'Dedicated business email hosting with your custom domain. Includes pure Webmail, modern IMAP/SMTP encryption, 2048-bit DKIM deliverability, and pooled storage.',
                'icon_url' => '/images/logos/mail.svg',
                'is_popular' => true,
                'badge' => 'Custom Domain',
                'is_active' => true,
            ]
        );

        $plans = [
            [
                'slug' => 'mail-starter',
                'name' => 'Starter Email',
                'storage_mb' => 500,
                'max_mailboxes' => 3,
                'max_domains' => 1,
                'price_cents' => 150, // $1.50/mo
                'price_lkr_cents' => 48000, // LKR 480/mo
                'features' => ['500 MB Shared Storage', 'Up to 3 Mailboxes', 'Bring 1 Custom Domain', 'Webmail + IMAP/SMTP', '2048-bit DKIM & SPF'],
            ],
            [
                'slug' => 'mail-basic',
                'name' => 'Basic Business Email',
                'storage_mb' => 1024,
                'max_mailboxes' => 5,
                'max_domains' => 1,
                'price_cents' => 300, // $3.00/mo
                'price_lkr_cents' => 95000, // LKR 950/mo
                'features' => ['1 GB Shared Storage', 'Up to 5 Mailboxes', 'Bring 1 Custom Domain', 'Custom Per-Mailbox Quotas', 'Catch-All & Aliases'],
            ],
            [
                'slug' => 'mail-pro',
                'name' => 'Pro Team Email',
                'storage_mb' => 3072,
                'max_mailboxes' => 15,
                'max_domains' => 2,
                'price_cents' => 600, // $6.00/mo
                'price_lkr_cents' => 190000, // LKR 1,900/mo
                'features' => ['3 GB Shared Storage', 'Up to 15 Mailboxes', 'Bring 2 Custom Domains', 'Custom Per-Mailbox Quotas', 'Unlimited Aliases'],
            ],
            [
                'slug' => 'mail-business',
                'name' => 'Business Enterprise Email',
                'storage_mb' => 5120,
                'max_mailboxes' => 30,
                'max_domains' => 3,
                'price_cents' => 1000, // $10.00/mo
                'price_lkr_cents' => 320000, // LKR 3,200/mo
                'features' => ['5 GB Shared Storage', 'Up to 30 Mailboxes', 'Bring 3 Custom Domains', 'Custom Per-Mailbox Quotas', 'Priority Inbound Delivery'],
            ],
        ];

        foreach ($plans as $p) {
            $variant = ProductVariant::updateOrCreate(
                ['product_id' => $product->id, 'slug' => $p['slug']],
                [
                    'name' => $p['name'],
                    'vcpu' => 1,
                    'ram_mb' => 512,
                    'disk_gb' => (int) ceil($p['storage_mb'] / 1024),
                    'bandwidth_gb' => 1000,
                    'specs_json' => [
                        'storage_mb' => $p['storage_mb'],
                        'max_mailboxes' => $p['max_mailboxes'],
                        'max_domains' => $p['max_domains'],
                        'features' => $p['features'],
                    ],
                    'is_active' => true,
                ]
            );

            PricingTier::updateOrCreate(
                ['product_variant_id' => $variant->id, 'billing_type' => 'monthly'],
                [
                    'price_cents' => $p['price_cents'],
                    'price_lkr_cents' => $p['price_lkr_cents'],
                    'setup_fee_cents' => 0,
                    'is_active' => true,
                ]
            );

            // Also synchronize into mail_plans table
            MailPlan::updateOrCreate(
                ['slug' => $p['slug']],
                [
                    'name' => $p['name'],
                    'storage_mb' => $p['storage_mb'],
                    'max_mailboxes' => $p['max_mailboxes'],
                    'price_cents' => $p['price_cents'],
                    'price_lkr_cents' => $p['price_lkr_cents'],
                    'billing_interval' => 'monthly',
                    'is_active' => true,
                    'features' => $p['features'],
                ]
            );
        }

        // Seed Add-ons catalog
        $addons = [
            ['slug' => 'addon-storage-1gb', 'name' => '+1 GB Extra Mailbox Storage', 'type' => 'storage', 'specs_json' => ['storage_mb' => 1024], 'price_cents' => 100],
            ['slug' => 'addon-storage-5gb', 'name' => '+5 GB High-Capacity Mail Pool', 'type' => 'storage', 'specs_json' => ['storage_mb' => 5120], 'price_cents' => 400],
            ['slug' => 'addon-mailboxes-5', 'name' => '+5 Extra Mailboxes Pack', 'type' => 'mailbox', 'specs_json' => ['extra_mailboxes' => 5], 'price_cents' => 300],
            ['slug' => 'addon-domain-1', 'name' => '+1 Extra Custom Domain Allocation', 'type' => 'domain', 'specs_json' => ['extra_domains' => 1], 'price_cents' => 200],
            ['slug' => 'addon-unlimited-aliases', 'name' => 'Unlimited Email Aliases', 'type' => 'feature', 'specs_json' => ['unlimited_aliases' => true], 'price_cents' => 100],
            ['slug' => 'addon-catch-all', 'name' => 'Catch-All Mail Routing', 'type' => 'feature', 'specs_json' => ['catch_all' => true], 'price_cents' => 150],
        ];

        foreach ($addons as $addonData) {
            MailAddon::updateOrCreate(
                ['slug' => $addonData['slug']],
                [
                    'name' => $addonData['name'],
                    'type' => $addonData['type'],
                    'specs_json' => $addonData['specs_json'],
                    'price_cents' => $addonData['price_cents'],
                    'billing_interval' => 'monthly',
                    'is_active' => true,
                ]
            );
        }

        return $product;
    }
}

