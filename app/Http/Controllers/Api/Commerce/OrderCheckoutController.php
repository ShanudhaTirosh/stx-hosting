<?php

namespace App\Http\Controllers\Api\Commerce;

use App\Http\Controllers\Controller;
use App\Models\Commerce\CommerceAuditLog;
use App\Models\Commerce\Invoice;
use App\Models\Commerce\InvoiceItem;
use App\Models\Commerce\Order;
use App\Models\Commerce\OrderItem;
use App\Models\Commerce\PricingTier;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class OrderCheckoutController extends Controller
{
    /**
     * Create an order and invoice atomically from validated cart items.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'items' => 'required|array|min:1',
            'items.*.pricing_tier_id' => 'required|string', // uuid or id
            'items.*.quantity' => 'nullable|integer|min:1|max:10',
            'items.*.hostname' => 'nullable|string|max:255',
            'items.*.public_ssh_key' => 'nullable|string|max:4096',
            'items.*.datacenter' => 'nullable|string|max:64',
            'currency' => 'nullable|string|in:USD,LKR',
            'customer_notes' => 'nullable|string|max:1000',
            'customer_email' => 'nullable|email', // For guest or unauthenticated checkout
            'customer_name' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $currency = strtoupper($request->input('currency', 'USD'));
        $user = auth()->user();

        // If no authenticated user, resolve or create customer user
        if (! $user) {
            $email = $request->input('customer_email');
            if (! $email) {
                return response()->json([
                    'success' => false,
                    'error' => 'Authentication required or customer_email must be provided for guest checkout.',
                ], 401);
            }

            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $request->input('customer_name', 'Hosting Customer'),
                    'password' => bcrypt(Str::random(24)),
                ]
            );
        }

        // Resolve customer team
        $team = $user->currentTeam() ?? $user->teams()->first();
        if (! $team) {
            $team = Team::create([
                'name' => $user->name . "'s Team",
                'personal_team' => true,
            ]);
            $team->members()->attach($user->id, ['role' => 'owner']);
        }

        try {
            $result = DB::transaction(function () use ($request, $user, $team, $currency) {
                $subtotalCents = 0;
                $processedItems = [];

                foreach ($request->input('items') as $rawItem) {
                    $tierId = $rawItem['pricing_tier_id'];
                    $quantity = (int) ($rawItem['quantity'] ?? 1);

                    // Find pricing tier by uuid or id
                    $tier = PricingTier::with('productVariant.product')
                        ->where('uuid', $tierId)
                        ->orWhere('id', is_numeric($tierId) ? (int)$tierId : 0)
                        ->first();

                    if (! $tier || ! $tier->is_active) {
                        throw new \Exception("Pricing tier '{$tierId}' is not found or no longer active.");
                    }

                    $unitPriceCents = ($currency === 'LKR' && $tier->price_lkr_cents)
                        ? $tier->price_lkr_cents
                        : $tier->price_cents;

                    $itemTotalCents = $unitPriceCents * $quantity;
                    $subtotalCents += $itemTotalCents;

                    $processedItems[] = [
                        'tier' => $tier,
                        'variant' => $tier->productVariant,
                        'product' => $tier->productVariant->product,
                        'quantity' => $quantity,
                        'unit_price_cents' => $unitPriceCents,
                        'total_price_cents' => $itemTotalCents,
                        'hostname' => $rawItem['hostname'] ?? null,
                        'public_ssh_key' => $rawItem['public_ssh_key'] ?? null,
                        'datacenter' => $rawItem['datacenter'] ?? 'fsn1',
                    ];
                }

                $taxCents = 0;
                $discountCents = 0;
                $totalCents = $subtotalCents - $discountCents + $taxCents;

                // 1. Create Order
                $order = Order::create([
                    'team_id' => $team->id,
                    'user_id' => $user->id,
                    'status' => 'pending',
                    'subtotal_cents' => $subtotalCents,
                    'discount_cents' => $discountCents,
                    'tax_cents' => $taxCents,
                    'total_cents' => $totalCents,
                    'currency' => $currency,
                    'customer_notes' => $request->input('customer_notes'),
                ]);

                // 2. Create Order Items
                foreach ($processedItems as $item) {
                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_variant_id' => $item['variant']->id,
                        'pricing_tier_id' => $item['tier']->id,
                        'quantity' => $item['quantity'],
                        'unit_price_cents' => $item['unit_price_cents'],
                        'total_price_cents' => $item['total_price_cents'],
                        'hostname' => $item['hostname'],
                        'public_ssh_key' => $item['public_ssh_key'],
                        'datacenter' => $item['datacenter'],
                    ]);
                }

                // 3. Create Invoice
                $invoiceNumber = 'INV-' . date('Ym') . '-' . strtoupper(Str::random(6));
                $invoice = Invoice::create([
                    'invoice_number' => $invoiceNumber,
                    'order_id' => $order->id,
                    'team_id' => $team->id,
                    'user_id' => $user->id,
                    'status' => 'unpaid',
                    'subtotal_cents' => $subtotalCents,
                    'tax_cents' => $taxCents,
                    'total_cents' => $totalCents,
                    'currency' => $currency,
                    'due_at' => now()->addDays(7),
                ]);

                // 4. Create Invoice Items
                foreach ($processedItems as $item) {
                    InvoiceItem::create([
                        'invoice_id' => $invoice->id,
                        'description' => $item['product']->name . ' - ' . $item['variant']->name . ' (' . ucfirst($item['tier']->billing_type) . ')',
                        'quantity' => $item['quantity'],
                        'unit_price_cents' => $item['unit_price_cents'],
                        'total_price_cents' => $item['total_price_cents'],
                    ]);
                }

                CommerceAuditLog::create([
                    'user_id' => $user->id,
                    'team_id' => $team->id,
                    'action' => 'order.created',
                    'target_type' => Order::class,
                    'target_id' => $order->id,
                    'payload' => [
                        'order_uuid' => $order->uuid,
                        'invoice_uuid' => $invoice->uuid,
                        'total_cents' => $totalCents,
                        'currency' => $currency,
                    ],
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'created_at' => now(),
                ]);

                return [
                    'order' => $order->load('items'),
                    'invoice' => $invoice->load('items'),
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Order created successfully.',
                'order' => [
                    'uuid' => $result['order']->uuid,
                    'status' => $result['order']->status,
                    'total' => $result['order']->total_cents / 100,
                    'currency' => $result['order']->currency,
                ],
                'invoice' => [
                    'uuid' => $result['invoice']->uuid,
                    'invoice_number' => $result['invoice']->invoice_number,
                    'status' => $result['invoice']->status,
                    'total' => $result['invoice']->total_cents / 100,
                    'currency' => $result['invoice']->currency,
                    'due_at' => $result['invoice']->due_at->toIso8601String(),
                ],
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }
}
