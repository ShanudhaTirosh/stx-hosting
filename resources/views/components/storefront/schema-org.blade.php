@props([
    'title' => null,
    'description' => null,
    'type' => 'website', // website, product, article, faq
    'breadcrumbs' => [], // [['name' => 'Home', 'url' => 'https:...'], ...]
    'article' => null,   // ['title' => ..., 'date' => ..., 'author' => ..., 'summary' => ...]
    'faqs' => [],        // [['question' => ..., 'answer' => ...], ...]
    'plans' => [],       // [['name' => ..., 'price' => ..., 'currency' => ..., 'description' => ...], ...]
])
@php
    $baseUrl = url('/');
    $brand = config('storefront.name', 'STX Hosting');
    $parentBrand = config('storefront.parent_brand', 'STX Enterprises');
    $currentUrl = url()->current();
    $logoUrl = asset('STKcdr.svg');
    $defaultDesc = 'High-performance Cloud VPS, Docker app hosting, managed databases, and enterprise email hosting in Sri Lanka, paid via bank transfer in LKR or USD.';

    // Base Organization Schema
    $orgSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        '@id' => $baseUrl.'/#organization',
        'name' => $parentBrand,
        'alternateName' => $brand,
        'url' => $baseUrl,
        'logo' => $logoUrl,
        'description' => $defaultDesc,
        'address' => [
            '@type' => 'PostalAddress',
            'addressCountry' => 'LK',
            'addressLocality' => 'Colombo',
        ],
        'contactPoint' => [
            '@type' => 'ContactPoint',
            'contactType' => 'customer support',
            'email' => config('storefront.support.email', 'support@shanutechx.com'),
            'url' => route('store.contact'),
            'availableLanguage' => ['English', 'Sinhala'],
        ],
        'sameAs' => array_filter([
            config('storefront.support.discord'),
            config('storefront.support.whatsapp'),
        ]),
    ];

    // WebSite Schema
    $websiteSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        '@id' => $baseUrl.'/#website',
        'url' => $baseUrl,
        'name' => $brand,
        'publisher' => ['@id' => $baseUrl.'/#organization'],
    ];

    // SoftwareApplication Schema (STX Cloud Platform)
    $appSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'SoftwareApplication',
        'name' => $brand.' Cloud Platform',
        'operatingSystem' => 'Linux, Cloud',
        'applicationCategory' => 'DeveloperApplication, BusinessApplication',
        'offers' => [
            '@type' => 'AggregateOffer',
            'priceCurrency' => 'LKR',
            'lowPrice' => '1500',
            'offerCount' => '15',
        ],
    ];

    $schemas = [$orgSchema, $websiteSchema, $appSchema];

    // BreadcrumbList Schema
    if (!empty($breadcrumbs)) {
        $itemList = [];
        foreach ($breadcrumbs as $idx => $crumb) {
            $itemList[] = [
                '@type' => 'ListItem',
                'position' => $idx + 1,
                'name' => $crumb['name'],
                'item' => $crumb['url'],
            ];
        }
        $schemas[] = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $itemList,
        ];
    }

    // Article Schema
    if (!empty($article)) {
        $schemas[] = [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $article['title'] ?? $title,
            'description' => $article['summary'] ?? $description ?? $defaultDesc,
            'datePublished' => $article['date'] ?? date('Y-m-d'),
            'dateModified' => $article['date'] ?? date('Y-m-d'),
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id' => $currentUrl,
            ],
            'author' => [
                '@type' => 'Organization',
                'name' => $article['author'] ?? $parentBrand,
            ],
            'publisher' => ['@id' => $baseUrl.'/#organization'],
        ];
    }

    // FAQPage Schema
    if (!empty($faqs)) {
        $faqEntities = [];
        foreach ($faqs as $faq) {
            $faqEntities[] = [
                '@type' => 'Question',
                'name' => $faq['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $faq['answer'],
                ],
            ];
        }
        $schemas[] = [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $faqEntities,
        ];
    }

    // Product & Offer Schemas
    if (!empty($plans)) {
        foreach ($plans as $plan) {
            $schemas[] = [
                '@context' => 'https://schema.org',
                '@type' => 'Product',
                'name' => $plan['name'],
                'description' => $plan['description'] ?? ($plan['name'].' cloud plan by '.$brand),
                'brand' => ['@id' => $baseUrl.'/#organization'],
                'offers' => [
                    '@type' => 'Offer',
                    'price' => $plan['price'] ?? '0',
                    'priceCurrency' => $plan['currency'] ?? 'LKR',
                    'availability' => 'https://schema.org/InStock',
                    'url' => $currentUrl,
                    'seller' => ['@id' => $baseUrl.'/#organization'],
                    'deliveryLeadTime' => [
                        '@type' => 'QuantitativeValue',
                        'minValue' => 24,
                        'maxValue' => 72,
                        'unitCode' => 'HUR',
                    ],
                ],
            ];
        }
    }
@endphp

@foreach ($schemas as $item)
<script type="application/ld+json">
{!! json_encode($item, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}
</script>
@endforeach
