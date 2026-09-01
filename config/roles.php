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
        'operator' => [
            'label' => 'Operator',
            'guard' => 'admin',
            'admin_access' => true,
            'permissions' => ['dashboard.view', 'orders.operate'],
        ],
        'barista' => [
            'label' => 'Barista',
            'guard' => 'admin',
            'admin_access' => true,
            'permissions' => ['dashboard.view'],
        ],
        'chef' => [
            'label' => 'Chef',
            'guard' => 'admin',
            'admin_access' => true,
            'permissions' => ['dashboard.view'],
        ],
        'waiter' => [
            'label' => 'Waiter',
            'guard' => 'admin',
            'admin_access' => true,
            'permissions' => ['dashboard.view', 'dining.operate'],
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
