<div>
    <x-slot:title>
        Server Variables | STX Hosting
    </x-slot>

    <x-shared-variables.editor :resource="$server"
        :variables="$server->environment_variables"
        :readOnlyKeys="['STX_SERVER_UUID', 'STX_SERVER_NAME', 'COOLIFY_SERVER_UUID', 'COOLIFY_SERVER_NAME']"
        type="server" title="{{ $server->name }}"
        :view="$view" variablesLabel="Server shared variables" />
</div>
