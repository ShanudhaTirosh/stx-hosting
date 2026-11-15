@php
    $isAdmin = request()->is('admin*');
    $settingsMenuSections = [
        'Configuration' => [
            ['label' => 'General', 'route' => $isAdmin ? 'admin.settings.index' : 'settings.index', 'icon' => 'settings'],
            ['label' => 'Advanced', 'route' => $isAdmin ? 'admin.settings.advanced' : 'settings.advanced', 'icon' => 'grid'],
            ['label' => 'Updates', 'route' => $isAdmin ? 'admin.settings.updates' : 'settings.updates', 'icon' => 'refresh3'],
        ],
        'Instance' => [
            ['label' => 'Email', 'route' => $isAdmin ? 'admin.settings.email' : 'settings.email', 'icon' => 'mail'],
            ['label' => 'Backup', 'route' => $isAdmin ? 'admin.settings.backup' : 'settings.backup', 'icon' => 'database'],
        ],
    ];
@endphp

{{-- The pages are in the Settings submenu of the main sidebar (components/navbar.blade.php). --}}
<section class="application-settings-workspace w-full max-w-none">
    <header class="settings-mobile-header xl:hidden">
        <h1 class="settings-mobile-title">Instance Settings</h1>
        <p class="settings-mobile-description">Configure global settings for this STX Hosting instance.</p>
    </header>
    {{-- Feeds the page search with the settings pages. --}}
    <div hidden data-settings-search-items="{{ json_encode(settingsSearchItems($settingsMenuSections, [])) }}"></div>
    @isset($submenu)
        {{-- Sub-items of the current page, under its entry in the main sidebar. --}}
        @teleport('#main-nav-active-children')
            <div>{{ $submenu }}</div>
        @endteleport
    @endisset
    <div class="min-w-0">
        {{ $slot }}
    </div>
</section>
