<?php

/*
|--------------------------------------------------------------------------
| STX platform permission map
|--------------------------------------------------------------------------
| One source of truth for "who may do what" across the API, Livewire
| components, route middleware and the sidebar. Roles are derived from the
| root team (id 0) pivot role: owner, admin. Everyone else is a client.
|
| Use RbacService::can($user, 'capability') everywhere. Do not test roles
| directly in views or components, and never use User::isAdmin() to mean
| "platform staff" (it is true for every client inside their own team).
*/

return [
    'roles' => ['owner', 'admin', 'client'],

    'capabilities' => [
        // Consoles
        'admin.console' => ['admin', 'owner'],
        'owner.console' => ['owner'],
        'admins.manage' => ['owner'],

        // Operational management tasks (Admin & Owner)
        'payments.verify' => ['admin', 'owner'],
        'vps.provision' => ['admin', 'owner'],
        'vps.inventory' => ['admin', 'owner'],
        'chat.reply' => ['admin', 'owner'],
        'status.manage' => ['admin', 'owner'],
        'audit.view' => ['admin', 'owner'],

        // Governance, Catalog & Pricing (Owner Only)
        'catalog.manage' => ['owner'],
        'pricing.manage' => ['owner'],
        'payment_methods.manage' => ['owner'],
        'deployments.approve' => ['owner'],

        // Fleet Infrastructure & Nodes (Owner Only)
        'nodes.view' => ['owner'],
        'nodes.add' => ['owner'],
        'nodes.remove' => ['owner'],
        'nodes.migrate' => ['owner'],
        'nodes.stats' => ['owner'],
        'nodes.terminal' => ['owner'],
        'nodes.power' => ['owner'],

        // Platform & System Settings (Owner Only)
        'platform.settings' => ['owner'],
        'integrations.manage' => ['owner'],
        'users.impersonate' => ['owner'],
        'notifications.manage' => ['owner'],
        'email_templates.manage' => ['owner'],
        'backups.manage' => ['owner'],
        'backups.storage' => ['owner'],

        // Client Portal (Owner & Client - Admins are strictly prohibited)
        'portal.use' => ['owner', 'client'],
    ],
];
