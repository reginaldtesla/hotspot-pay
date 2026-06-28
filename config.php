<?php

declare(strict_types=1);

return [
    'app_name' => 'TesNet Pay',

    // Public URL (no trailing slash). Set in config.local.php after domain is live.
    'app_url' => 'https://pay.tesnet.xyz',

    // Hotspot login page (MikroTik). Used on payment success for "Go to login" link.
    'hotspot_login_url' => 'http://192.168.88.1',

    // Map MikroTik profile names (CSV import) → package slug.
    'profile_to_slug' => [
        'Quick_Surf_1GB' => 'quick-surf',
        'Student_Choice_3GB' => 'student-choice',
        'Big_Bundle_7GB' => 'big-bundle',
        'Heavy_User_15GB' => 'heavy-user',
        'Hostel_Legend_45GB' => 'hostel-legend',
        'Two_Hour' => '2-hour',
        'Four_Hour' => '4-hour',
        'Eight_Hour' => '8-hour',
        'Full_Day' => 'full-day',
        'Two_Week' => '2-week',
        'Month' => 'month',
    ],

    'packages' => [
        [
            'slug' => 'quick-surf',
            'name' => 'Quick Surf',
            'data_label' => '1GB',
            'amount_pesewas' => 350,
            'mikrotik_profile' => 'Quick_Surf_1GB',
            'kind' => 'data',
            'sort_order' => 1,
        ],
        [
            'slug' => 'student-choice',
            'name' => 'Student Choice',
            'data_label' => '3GB',
            'amount_pesewas' => 900,
            'mikrotik_profile' => 'Student_Choice_3GB',
            'kind' => 'data',
            'sort_order' => 2,
        ],
        [
            'slug' => 'big-bundle',
            'name' => 'Big Bundle',
            'data_label' => '7GB',
            'amount_pesewas' => 1800,
            'mikrotik_profile' => 'Big_Bundle_7GB',
            'kind' => 'data',
            'sort_order' => 3,
        ],
        [
            'slug' => 'heavy-user',
            'name' => 'Heavy User',
            'data_label' => '15GB',
            'amount_pesewas' => 3500,
            'mikrotik_profile' => 'Heavy_User_15GB',
            'kind' => 'data',
            'sort_order' => 4,
        ],
        [
            'slug' => 'hostel-legend',
            'name' => 'Hostel Legend',
            'data_label' => '45GB',
            'amount_pesewas' => 9500,
            'mikrotik_profile' => 'Hostel_Legend_45GB',
            'kind' => 'data',
            'sort_order' => 5,
        ],
        [
            'slug' => '2-hour',
            'name' => '2-Hour',
            'data_label' => '2 Hours',
            'amount_pesewas' => 400,
            'mikrotik_profile' => 'Two_Hour',
            'kind' => 'time',
            'sort_order' => 6,
        ],
        [
            'slug' => '4-hour',
            'name' => '4-Hour',
            'data_label' => '4 Hours',
            'amount_pesewas' => 800,
            'mikrotik_profile' => 'Four_Hour',
            'kind' => 'time',
            'sort_order' => 7,
        ],
        [
            'slug' => '8-hour',
            'name' => '8-Hour',
            'data_label' => '8 Hours',
            'amount_pesewas' => 1600,
            'mikrotik_profile' => 'Eight_Hour',
            'kind' => 'time',
            'sort_order' => 8,
        ],
        [
            'slug' => 'full-day',
            'name' => 'Full Day',
            'data_label' => '24 Hours',
            'amount_pesewas' => 2500,
            'mikrotik_profile' => 'Full_Day',
            'kind' => 'time',
            'sort_order' => 9,
        ],
        [
            'slug' => '2-week',
            'name' => '2-Week',
            'data_label' => '2 Weeks',
            'amount_pesewas' => 9900,
            'mikrotik_profile' => 'Two_Week',
            'kind' => 'time',
            'sort_order' => 10,
        ],
        [
            'slug' => 'month',
            'name' => 'Month',
            'data_label' => '30 Days',
            'amount_pesewas' => 19900,
            'mikrotik_profile' => 'Month',
            'kind' => 'time',
            'sort_order' => 11,
        ],
    ],
];
