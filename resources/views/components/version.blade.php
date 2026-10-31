@php($version = config('constants.stx.version', config('constants.coolify.version')))

@if (str_contains($version, '-dev.'))
    <span {{ $attributes->merge(['class' => 'text-xs opacity-90']) }}>v{{ $version }}</span>
@else
    <a {{ $attributes->merge(['class' => 'text-xs cursor-pointer opacity-90 hover:opacity-100 dark:hover:text-white hover:text-black']) }}
        href="https://stxhosting.com/releases/tag/v{{ config('constants.stx.version', config('constants.coolify.version')) }}" target="_blank">
        v{{ $version }}
    </a>
@endif
