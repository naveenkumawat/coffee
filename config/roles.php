<?php

return [
    'roles' => [
        'owner' => [
            'label' => 'Owner',
            'guard' => 'admin',
            'admin_access' => true,
            'permissions' => ['*'],
        ],
        'manager' => [
            'label' => 'Manager',
            'guard' => 'admin',
            'admin_access' => true,
            'permissions' => ['menu.manage', 'dashboard.view'],
        ],
        'barista' => [
            'label' => 'Barista',
            'guard' => 'admin',
            'admin_access' => true,
            'permissions' => ['dashboard.view'],
        ],
        'cashier' => [
            'label' => 'Cashier',
            'guard' => 'admin',
            'admin_access' => true,
            'permissions' => ['dashboard.view'],
        ],
        'customer' => [
            'label' => 'Customer',
            'guard' => 'web',
            'admin_access' => false,
            'permissions' => ['menu.view'],
        ],
    ],
];
