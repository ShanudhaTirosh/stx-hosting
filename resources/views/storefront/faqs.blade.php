<x-storefront.layout title="FAQ">
    <div class="wrap">
        <div class="pagehead"><div><h1>Questions and answers</h1><p>Cannot find yours? <a href="{{ route('store.contact') }}">Ask us</a>.</p></div></div>
        <div class="prose">
            @foreach ($sections as $heading => $items)
                <h2>{{ $heading }}</h2>
                @foreach ($items as [$q, $a])
                    <details class="faq">
                        <summary>{{ $q }}</summary>
                        <p>{{ $a }}</p>
                    </details>
                @endforeach
            @endforeach
        </div>
    </div>
</x-storefront.layout>
