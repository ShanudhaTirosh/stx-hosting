<?php

namespace App\Http\Controllers\Api\Commerce;

use App\Http\Controllers\Controller;
use App\Models\Commerce\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ProductCatalogController extends Controller
{
    /**
     * Return active products and their pricing plans conforming to web/lib/api.ts Product interface.
     */
    public function index(Request $request): JsonResponse
    {
        $category = $request->query('category');
        $currency = strtoupper($request->query('currency', 'USD'));

        $cacheKey = "api_products_{$category}_{$currency}";

        $products = Cache::remember($cacheKey, 60, function () use ($category, $currency) {
            $query = Product::with(['variants.pricingTiers' => function ($q) {
                $q->where('is_active', true);
            }])->where('is_active', true);

            if ($category) {
                $query->where('category', $category);
            }

            return $query->get()->map(function (Product $product) use ($currency) {
                return $this->formatProduct($product, $currency);
            });
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
        $cacheKey = "api_product_slug_{$slug}_{$currency}";

        $product = Cache::remember($cacheKey, 60, function () use ($slug, $currency) {
            $product = Product::with(['variants.pricingTiers' => function ($q) {
                $q->where('is_active', true);
            }])->where('slug', $slug)->where('is_active', true)->first();

            if (! $product) {
                return null;
            }

            return $this->formatProduct($product, $currency);
        });

        if (! $product) {
            return response()->json(['success' => false, 'message' => 'Product not found'], 404);
        }

        return response()->json([
            'success' => true,
            'product' => $product,
        ]);
    }

    private function formatProduct(Product $product, string $currency): array
    {
        $plans = [];

        foreach ($product->variants as $variant) {
            foreach ($variant->pricingTiers as $tier) {
                $price = $currency === 'LKR' && ($tier->price_lkr_cents ?? 0) > 0
                    ? (float) ($tier->price_lkr_cents / 100)
                    : (float) (($tier->price_cents ?? 0) / 100);

                $plans[] = [
                    'id' => $tier->uuid,
                    'productId' => $product->uuid,
                    'variantId' => $variant->uuid,
                    'name' => $variant->name,
                    'price' => $price,
                    'currency' => $currency,
                    'billingType' => $tier->billing_type,
                    'isPopular' => (bool) ($variant->is_popular ?? $product->is_popular),
                    'badge' => $product->badge,
                    'specsJson' => $variant->specs_json,
                    'isActive' => (bool) $tier->is_active,
                ];
            }
        }

        return [
            'id' => $product->uuid,
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
