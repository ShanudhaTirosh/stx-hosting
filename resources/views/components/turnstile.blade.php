@inject('turnstile', 'App\Services\Security\TurnstileService')

@if ($turnstile->isEnabled())
    <div class="my-2 flex justify-center" wire:ignore>
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
        <div class="cf-turnstile"
             data-sitekey="{{ $turnstile->getSiteKey() }}"
             data-theme="auto"
             data-size="flexible">
        </div>
    </div>
@endif
