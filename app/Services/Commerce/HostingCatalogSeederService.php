<?php

namespace App\Services\Commerce;

use App\Models\Commerce\PricingTier;
use App\Models\Commerce\Product;
use App\Models\Commerce\ProductVariant;
use App\Models\TemplatePermission;
use Illuminate\Support\Str;

class HostingCatalogSeederService
{
    /**
     * Seed or update the 3 Git App Hosting plans and popular curated templates.
     */
    public static function seedAll(): void
    {
        self::seedGitAppPlans();
        self::seedCuratedTemplates();
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
}

