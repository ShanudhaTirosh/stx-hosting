<?php

namespace App\Http\Controllers\Api\Commerce;

use App\Http\Controllers\Controller;
use App\Models\Commerce\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductCatalogController extends Controller
{
    /**
     * Return active products and their pricing plans conforming to web/lib/api.ts Product interface.
     */
    public function index(Request $request): JsonResponse
    {
        $category = $request->query('category');
        $currency = strtoupper($request->query('currency', 'USD'));

        $query = Product::with(['variants.pricingTiers' => function ($q) {
            $q->where('is_active', true);
        }])->where('is_active', true);

        if ($category) {
            $query->where('category', $category);
        }

        $products = $query->get()->map(function (Product $product) use ($currency) {
            return $this->formatProduct($product, $currency);
        });

        return response()->json([
            'success' => true,
            'products' => $products,
        ]);
    }

    /**
     * Return single product by slug.
     */
    public function show(string $slug, Request $request): JsonResponse
    {
        $currency = strtoupper($request->query('currency', 'USD'));

        $product = Product::with(['variants.pricingTiers' => function ($q) {
            $q->where('is_active', true);
        }])->where('slug', $slug)
          ->where('is_active', true)
          ->first();

        if (! $product) {
            return response()->json(['error' => 'Product not found'], 404);
        }

        return response()->json([
            'success' => true,
            'product' => $this->formatProduct($product, $currency),
        ]);
    }

    /**
     * Map internal models to the exact TypeScript interface required by Next.js storefront.
     */
    private function formatProduct(Product $product, string $currency): array
    {
        $plans = [];

        foreach ($product->variants as $variant) {
            foreach ($variant->pricingTiers as $tier) {
                // If currency is LKR and LKR cents is set, convert to main currency units
                $price = ($currency === 'LKR' && $tier->price_lkr_cents)
                    ? ($tier->price_lkr_cents / 100)
                    : ($tier->price_cents / 100);

                $plans[] = [
                    'id' => (string) $tier->uuid,
                    'productId' => (string) $product->uuid,
                    'variantId' => (string) $variant->uuid,
                    'name' => $variant->name,
                    'price' => $price,
                    'currency' => $currency === 'LKR' && $tier->price_lkr_cents ? 'LKR' : 'USD',
                    'billingType' => $tier->billing_type,
                    'isPopular' => $product->is_popular,
                    'badge' => $product->badge,
                    'specsJson' => $variant->specs_json,
                    'isActive' => $tier->is_active,
                ];
            }
        }

        return [
            'id' => (string) $product->uuid,
            'category' => $product->category,
            'name' => $product->name,
            'slug' => $product->slug,
            'description' => $product->description,
            'iconUrl' => $product->icon_url,
            'isPopular' => (bool) $product->is_popular,
            'badge' => $product->badge,
            'isActive' => (bool) $product->is_active,
            'plans' => $plans,
        ];
    }
}

