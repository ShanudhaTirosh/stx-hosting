<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Mail\StorefrontContactMail;
use App\Models\Commerce\Monitor;
use App\Models\Commerce\Product;
use App\Models\Commerce\MailPlan;
use App\Models\Commerce\MailAddon;
use App\Services\Supabase\SupabaseClient;
use App\Models\Team;
use App\Services\Commerce\CartService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

class StorefrontController extends Controller
{
    private const TERM_LABELS = [
        '30-day' => '30 days', '50-day' => '50 days', 'monthly' => 'month',
        'quarterly' => '3 months', 'semi_annual' => '6 months', 'annual' => 'year',
    ];

    public function home(CartService $cart)
    {
        $currency = $cart->currency();
        $groups = $this->groups($currency);

        $index = collect($groups)->map(fn ($g) => [
            'label' => $g['label'],
            'route' => $g['route'],
            'from' => $g['from_cents'] === null ? null : Money::format($g['from_cents'], $currency),
            'term' => $g['from_term'],
        ])->values();

        $status = Cache::remember('storefront_status_summary_v2', 30, fn () => [
            'total' => Monitor::where('is_public', true)->count(),
            'down' => Monitor::where('is_public', true)->where('current_status', '!=', 'operational')->count(),
        ]);

        return view('storefront.home', compact('index', 'status', 'currency'));
    }

    /** One catalog for every category page and for /pricing (all groups). */
    public function catalog(Request $request, CartService $cart, ?string $category = null)
    {
        $currency = $cart->currency();
        $categories = config('storefront.categories');

        abort_if($category !== null && ! isset($categories[$category]), 404);

        $groups = $this->groups($currency, $category);

        return view('storefront.catalog', [
            'groups' => $groups,
            'single' => $category !== null ? ($categories[$category] ?? null) : null,
            'currency' => $currency,
        ]);
    }

    /** Dedicated Email Hosting pricing and plans landing */
    public function emailHosting(CartService $cart)
    {
        $currency = $cart->currency();
        $supabase = app(SupabaseClient::class);

        try {
            $plans = $supabase->from('mail_plans')->eq('is_active', true)->order('storage_mb')->get();
            $addons = $supabase->from('mail_addons')->eq('is_active', true)->order('type')->get();
        } catch (\Throwable $e) {
            $plans = MailPlan::active()->orderBy('storage_mb')->get()->toArray();
            $addons = MailAddon::active()->orderBy('type')->get()->toArray();
        }

        if (empty($plans)) {
            $plans = [
                ['name' => 'Starter', 'storage_mb' => 500, 'max_mailboxes' => 3, 'price_cents' => 150, 'price_lkr_cents' => 48000],
                ['name' => 'Basic', 'storage_mb' => 1024, 'max_mailboxes' => 5, 'price_cents' => 300, 'price_lkr_cents' => 95000],
                ['name' => 'Pro', 'storage_mb' => 3072, 'max_mailboxes' => 15, 'price_cents' => 600, 'price_lkr_cents' => 190000],
                ['name' => 'Business', 'storage_mb' => 5120, 'max_mailboxes' => 30, 'price_cents' => 1000, 'price_lkr_cents' => 320000],
            ];
        }

        return view('storefront.email-hosting', [
            'currency' => $currency,
            'plans' => $plans,
            'addons' => $addons,
        ]);
    }

    public function about()
    {
        return view('storefront.about', ['claims' => config('storefront.claims')]);
    }

    public function faqs()
    {
        return view('storefront.faqs', ['sections' => $this->faqData()]);
    }

    public function contact()
    {
        return view('storefront.contact');
    }

    public function contactSend(Request $request)
    {
        // A hidden field real people never fill in: bots do.
        if ($request->filled('website')) {
            return redirect()->route('store.contact')->with('success', 'Thanks. We will reply by email.');
        }

        $data = $request->validate([
            'name' => 'required|string|min:2|max:100',
            'email' => 'required|email|max:150',
            'department' => 'required|in:tech,sales,billing,abuse',
            'subject' => 'nullable|string|max:150',
            'message' => 'required|string|min:10|max:3000',
        ]);

        $recipients = array_filter([config('storefront.support.email')]);

        if ($recipients === []) {
            $recipients = Team::find(0)?->members()->wherePivotIn('role', ['owner', 'admin'])->pluck('email')->all() ?? [];
        }

        if ($recipients === []) {
            return back()->withInput()->with('error', 'We could not send your message right now. Please reach us on Discord instead.');
        }

        try {
            Mail::to($recipients)->send(new StorefrontContactMail(
                $data['name'], $data['email'], $data['department'], $data['subject'] ?? null, $data['message'],
            ));
        } catch (\Throwable $e) {
            logger()->error("Storefront contact mail failed: {$e->getMessage()}");

            return back()->withInput()->with('error', 'We could not send your message right now. Please reach us on Discord instead.');
        }

        return redirect()->route('store.contact')->with('success', 'Thanks. We will reply by email.');
    }

    public function legal(string $type)
    {
        abort_unless(in_array($type, ['terms', 'privacy', 'refund', 'cookies'], true), 404);

        $titles = ['terms' => 'Terms of service', 'privacy' => 'Privacy policy', 'refund' => 'Refunds and cancellation', 'cookies' => 'Cookie policy'];

        return view('storefront.legal', ['type' => $type, 'title' => $titles[$type]]);
    }

    /**
     * Sellable plans grouped by category, priced in the visitor's currency.
     *
     * @return array<string, array<string, mixed>>
     */
    private function groups(string $currency, ?string $only = null): array
    {
        $products = Product::with(['variants' => fn ($q) => $q->where('is_active', true)->orderBy('id'), 'variants.pricingTiers' => fn ($q) => $q->where('is_active', true)])
            ->where('is_active', true)
            ->orderByDesc('is_popular')
            ->orderBy('id')
            ->get();

        $groups = [];

        foreach (config('storefront.categories') as $slug => $cat) {
            if ($only !== null && $only !== $slug) {
                continue;
            }

            $rows = [];
            foreach ($products->whereIn('category', $cat['db']) as $product) {
                foreach ($product->variants as $variant) {
                    $terms = $variant->pricingTiers
                        ->map(function ($tier) use ($currency) {
                            $cents = $currency === 'LKR' ? (int) $tier->price_lkr_cents : (int) $tier->price_cents + (int) $tier->setup_fee_cents;
                            $blocked = $currency === 'LKR' && ((int) $tier->setup_fee_cents > 0);

                            return $cents > 0 && ! $blocked ? [
                                'billing_type' => $tier->billing_type,
                                'cents' => $cents,
                                'label' => self::TERM_LABELS[$tier->billing_type] ?? $tier->billing_type,
                                'price' => Money::format($cents, $currency),
                            ] : null;
                        })
                        ->filter()->sortBy('cents')->values();

                    $rows[] = [
                        'variant' => $variant,
                        'product' => $product,
                        'terms' => $terms,
                        'ram' => $variant->ram_mb >= 1024 ? rtrim(rtrim(number_format($variant->ram_mb / 1024, 1), '0'), '.').' GB' : $variant->ram_mb.' MB',
                        'disk' => $variant->disk_gb.' GB '.($variant->disk_type === 'nvme' ? 'NVMe' : strtoupper($variant->disk_type)),
                        'traffic' => $variant->bandwidth_gb >= 1000 ? rtrim(rtrim(number_format($variant->bandwidth_gb / 1000, 1), '0'), '.').' TB' : $variant->bandwidth_gb.' GB',
                        'stock_status' => $variant->stock_status ?? 'available',
                        'is_sold_out' => ($variant->stock_status ?? 'available') === 'sold_out',
                        'location' => $variant->location_name ?? null,
                        'location_flag' => $variant->location_flag ?? null,
                        'backing_provider' => $variant->backing_provider ?? null,
                    ];
                }
            }

            if ($rows === []) {
                continue;
            }

            $cheapest = collect($rows)->flatMap(fn ($r) => $r['terms'])->sortBy('cents')->first();

            $groups[$slug] = $cat + [
                'slug' => $slug,
                'rows' => $rows,
                'from_cents' => $cheapest['cents'] ?? null,
                'from_term' => $cheapest['label'] ?? null,
            ];
        }

        return $groups;
    }

    private function faqData(): array
    {
        return [
            'Ordering and payment' => [
                ['How do I pay?', 'Checkout creates your invoice first. Pay with one of the methods shown on it, such as a bank transfer, then upload your receipt. We check every receipt by hand and email you at each step.'],
                ['What if my receipt is rejected?', 'We email you the reason. Your invoice stays open, so you can upload a corrected receipt.'],
                ['Which currencies do you accept?', 'USD and LKR. The currency switch at the top of every page changes the prices you see.'],
            ],
            'Cloud VPS' => [
                ['How long until my VPS is ready?', 'We set VPS orders up by hand. After your payment is approved, delivery usually takes 24 to 72 hours. We email you when it is ready, and the IP address and login details appear in your client panel.'],
                ['Do I get root access?', 'Yes. Each VPS comes with an IP address, a root login, and an SSH password or key. You can open a terminal and a file manager from your client panel.'],
                ['Can I restart my VPS?', 'Yes, from your client panel. There is no power-off button. If you need the server stopped, contact us.'],
                ['Can I add a server I already have?', 'No. The panel only manages servers that we provide.'],
            ],
            'App, bot and website hosting' => [
                ['How do I deploy my code?', 'Connect your GitHub repository, choose a branch, add environment variables, and deploy. Pushes to that branch redeploy automatically. You can also start from one of our templates.'],
                ['Do I get a domain and HTTPS?', 'Every deployment gets a subdomain on our domain with an automatic HTTPS certificate. You can add your own domain as well.'],
                ['Can I go back to an earlier version?', 'Yes. You can roll back to a previous deployment from your deployment history.'],
            ],
            'Email hosting' => [
                ['Can I bring my own domain?', 'Yes. You bring your own domain, add the MX, SPF, DKIM, and DMARC DNS records we generate, and verify in seconds.'],
                ['Are there daily send or receive limits?', 'No daily send or receive quotas are enforced or advertised. Standard burst anti-spam protection keeps our IP reputation clean.'],
                ['How do I access my mail?', 'You can use our pure modern Webmail in any browser, or configure IMAP/SMTP on mobile and desktop email clients.'],
                ['Is storage pooled?', 'Yes. The storage of your chosen plan is shared across all your team mailboxes, and you can assign optional per-mailbox limits.'],
            ],
            'Backups' => [
                ['Are my databases and apps backed up?', 'Backups run on a schedule you choose, and you can start one yourself at any time. They are stored in Cloudflare R2, and you can restore from your client panel.'],
            ],
            'Help' => [
                ['How do I get help?', 'Use the contact form or our Discord. Signed-in customers can also message the team from the client panel.'],
            ],
        ];
    }
}
