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
        // Staff console
        'admin.console' => ['owner', 'admin'],
        'payments.verify' => ['owner', 'admin'],
        'catalog.manage' => ['owner', 'admin'],      // products and variants
        'pricing.manage' => ['owner'],                // prices and payment methods
        'payment_methods.manage' => ['owner'],
        'vps.inventory' => ['owner', 'admin'],
        'vps.provision' => ['owner', 'admin'],
        'deployments.approve' => ['owner', 'admin'],
        'status.manage' => ['owner', 'admin'],

        // Nodes
        'nodes.view' => ['owner', 'admin'],
        'nodes.add' => ['owner', 'admin'],
        'nodes.remove' => ['owner'],
        'nodes.migrate' => ['owner', 'admin'],
        'nodes.stats' => ['owner', 'admin'],
        'nodes.terminal' => ['owner', 'admin'],       // web terminal, SFTP, power (admin or owner)
        'nodes.power' => ['owner', 'admin'],

        // Platform
        'platform.settings' => ['owner', 'admin'],    // SMTP, auth, updates, instance settings (set to ['owner'] to make owner-only)
        'integrations.manage' => ['owner', 'admin'],  // sources, destinations, registries
        'users.impersonate' => ['owner'],
        'notifications.manage' => ['owner'],         // Discord channels per event
        'email_templates.manage' => ['owner'],
        'chat.reply' => ['owner', 'admin'],          // staff support inbox
        'backups.manage' => ['owner', 'admin'],      // see all backups, restore for customers
        'backups.storage' => ['owner'],              // choose the R2 storage, set quotas

        // Everyone signed in
        'portal.use' => ['owner', 'admin', 'client'],
    ],
];
