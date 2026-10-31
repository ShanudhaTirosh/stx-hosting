<div class="w-full">
    <x-slot:title>
        New Server | STX Enterprises
    </x-slot>

    <div class="mb-5 flex min-h-9 flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <h1 class="min-w-0 text-[24px]! leading-7! font-semibold! tracking-tight!">Connect Server</h1>
    </div>

    <div class="application-settings-form">
        <livewire:server.new.by-ip :private_keys="$private_keys" :limit_reached="$limit_reached"
            key="new-server-manual" />
    </div>
</div>
