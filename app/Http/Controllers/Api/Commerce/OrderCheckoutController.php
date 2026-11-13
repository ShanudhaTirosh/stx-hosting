<?php

namespace App\Http\Controllers\Api\Commerce;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Services\Commerce\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class OrderCheckoutController extends Controller
{
    /**
     * Create an order and invoice atomically from validated cart items via OrderService.
     */
    public function store(Request $request, OrderService $orderService): JsonResponse
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
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $currency = strtoupper($request->input('currency', 'USD'));
        $user = $request->user();

        // Orders require an authenticated account.
        if (! $user) {
            return response()->json([
                'success' => false,
                'error' => 'Authentication required.',
            ], 401);
        }

        // Resolve customer team
        $team = $user->currentTeam() ?? $user->teams()->first();
        if (! $team) {
            $team = Team::create([
                'name' => $user->name."'s Team",
                'personal_team' => true,
            ]);
            $team->members()->attach($user->id, ['role' => 'owner']);
        }

        try {
            [$order, $invoice] = $orderService->place(
                $user,
                $team,
                $request->input('items'),
                $currency,
                $request->input('customer_notes')
            );

            return response()->json([
                'success' => true,
                'message' => 'Order created successfully.',
                'order' => [
                    'uuid' => $order->uuid,
                    'status' => $order->status,
                    'total' => $order->total_cents / 100,
                    'currency' => $order->currency,
                ],
                'invoice' => [
                    'uuid' => $invoice->uuid,
                    'invoice_number' => $invoice->invoice_number,
                    'status' => $invoice->status,
                    'total' => $invoice->total_cents / 100,
                    'currency' => $invoice->currency,
                    'due_at' => $invoice->due_at->toIso8601String(),
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
