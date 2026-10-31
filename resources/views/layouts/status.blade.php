<!DOCTYPE html>
<html lang="en" class="dark" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? "STX Status — Cloud & Infrastructure Health" }}</title>
    <meta name="description" content="Real-time availability, operational telemetry, and uptime status for STX platform services." />
    
    <link rel="icon" href="{{ asset('STKcdr.svg') }}" type="image/svg+xml" />
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Space Grotesk Google Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/js/app.js', 'resources/css/app.css'])

    <style>
        [x-cloak] { display: none !important; }
        :root {
            --background: #ffffff;
            --foreground: #171717;
            --card-bg: #ffffff;
            --card-border: #e5e7eb;
            --muted: #6b7280;
            --input-bg: #f9fafb;
            --secondary-bg: #f3f4f6;
        }
        .dark, [data-theme='dark'] {
            --background: #09090b;
            --foreground: #fafafa;
            --card-bg: #18181b;
            --card-border: #27272a;
            --muted: #a1a1aa;
            --input-bg: #27272a;
            --secondary-bg: #27272a;
        }
        body {
            background-color: #09090b;
            color: #fafafa;
            font-family: 'Space Grotesk', ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }
        .text-muted {
            color: #a1a1aa;
        }
    </style>
    @livewireStyles
</head>
<body class="antialiased bg-[#09090b] text-[#fafafa] min-h-screen">
    {{ $slot }}

    @livewireScripts
</body>
</html>

