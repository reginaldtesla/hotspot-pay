<?php

declare(strict_types=1);

return [
    'app_name' => 'TesNet Pay',

    // Public URL (no trailing slash). Set in config.local.php after domain is live.
    'app_url' => 'https://pay.tesnet.xyz',

    // Map MikroTik profile names (CSV import) → package slug.
    'profile_to_slug' => [
        'Quick_Surf_1GB' => 'quick-surf',
        'Student_Choice_3GB' => 'student-choice',
        'Big_Bundle_7GB' => 'big-bundle',
        'Heavy_User_15GB' => 'heavy-user',
        'Hostel_Legend_45GB' => 'hostel-legend',
    ],

    'packages' => [
        [
            'slug' => 'quick-surf',
            'name' => 'Quick Surf',
            'data_label' => '1GB',
            'amount_pesewas' => 350,
            'mikrotik_profile' => 'Quick_Surf_1GB',
            'sort_order' => 1,
        ],
        [
            'slug' => 'student-choice',
            'name' => 'Student Choice',
            'data_label' => '3GB',
            'amount_pesewas' => 900,
            'mikrotik_profile' => 'Student_Choice_3GB',
            'sort_order' => 2,
        ],
        [
            'slug' => 'big-bundle',
            'name' => 'Big Bundle',
            'data_label' => '7GB',
            'amount_pesewas' => 1800,
            'mikrotik_profile' => 'Big_Bundle_7GB',
            'sort_order' => 3,
        ],
        [
            'slug' => 'heavy-user',
            'name' => 'Heavy User',
            'data_label' => '15GB',
            'amount_pesewas' => 3500,
            'mikrotik_profile' => 'Heavy_User_15GB',
            'sort_order' => 4,
        ],
        [
            'slug' => 'hostel-legend',
            'name' => 'Hostel Legend',
            'data_label' => '45GB',
            'amount_pesewas' => 9500,
            'mikrotik_profile' => 'Hostel_Legend_45GB',
            'sort_order' => 5,
        ],
    ],
];
