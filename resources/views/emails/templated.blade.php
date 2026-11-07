<x-emails.stx-layout
    eyebrow="{{ config('storefront.name') }}"
    :title="$heading"
    :ctaText="$cta_text"
    :ctaUrl="$cta_url"
>
    {!! $html !!}
</x-emails.stx-layout>
