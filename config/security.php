<?php

return [
    /*
     | Platform staff (owner and admins) must have two-factor sign-in turned on to use any staff page or power.
     | Customers can use it optionally. Without it a staff member is treated as a customer.
     | Turn off only for local development.
     */
    'require_staff_2fa' => (bool) env('STX_REQUIRE_STAFF_2FA', true),

    // The queues the worker listens to. Customer emails and alerts use "high".
    'queues' => env('HORIZON_QUEUES', 'high,default'),
];
