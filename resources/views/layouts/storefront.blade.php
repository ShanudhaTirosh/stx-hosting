<!DOCTYPE html>
<html lang="en" class="scroll-smooth dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#030014">
    <title>{{ $title ?? "STX Enterprises — Sri Lanka's #1 Cloud, VPS & Infrastructure Platform" }}</title>
    <meta name="description" content="Autonomous Cloud Hosting, Dedicated VPS, Game Server Hosting, High-Performance Databases & Platform Orchestration." />
    
    <link rel="icon" href="{{ asset('STKcdr.svg') }}" type="image/svg+xml" />
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @vite(['resources/js/app.js', 'resources/css/app.css'])

    <style>
        [x-cloak] { display: none !important; }
        body {
            background-color: #030014;
            color: #f8fafc;
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            overflow-x: hidden;
        }
        .text-gradient {
            background: linear-gradient(135deg, #a78bfa 0%, #38bdf8 50%, #22d3ee 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .text-gradient-purple {
            background: linear-gradient(135deg, #c084fc 0%, #7c3aed 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .glass-card {
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }
        .glass-card:hover {
            border-color: rgba(167, 139, 250, 0.3);
            box-shadow: 0 0 35px -10px rgba(124, 58, 237, 0.25);
        }
        .glass-nav {
            background: rgba(3, 0, 20, 0.75);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }
        .cosmic-glow {
            box-shadow: 0 0 60px -15px rgba(124, 58, 237, 0.35);
        }
        svg {
            box-sizing: border-box;
            max-width: 100%;
        }
        /* SVG guardrails */
        svg.animate-spin, .loading-indicator {
            width: 1.25rem !important;
            height: 1.25rem !important;
            display: inline-block !important;
        }
    </style>
    @livewireStyles
</head>
<body class="antialiased selection:bg-purple-500 selection:text-white min-h-screen flex flex-col justify-between">
    {{ $slot }}

    @livewireScripts
</body>
</html>
