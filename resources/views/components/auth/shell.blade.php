@props([
    'title',
    'description' => null,
])

<section class="auth-shell application-settings-form">
    <div class="auth-shell-content">
        <div class="auth-card">
            <div class="auth-card-heading">
                <a href="/" class="flex justify-center mb-4">
                    <img src="/STKcdr.svg" alt="STX Enterprises" class="h-12 w-auto" />
                </a>
                <h1>{{ $title }}</h1>
                @if ($description)
                    <p>{{ $description }}</p>
                @endif
            </div>

            <div class="auth-card-body">
                {{ $slot }}
            </div>

            @isset($footer)
                <footer class="auth-card-footer">
                    {{ $footer }}
                </footer>
            @endisset
        </div>
    </div>
</section>
