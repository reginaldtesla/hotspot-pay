<?php

declare(strict_types=1);

return [
    'app_name' => 'TesNet Pay',

    // Public URL (no trailing slash). Set in config.local.php after domain is live.
    'app_url' => 'https://pay.tesnet.xyz',

    // Hotspot login page (MikroTik). Used on payment success for "Go to login" link.
    'hotspot_login_url' => 'http://192.168.88.1',

    // Map MikroTik profile names (CSV import) → package slug. Repopulate when new packages are added.
    'profile_to_slug' => [
    ],

    // Package catalog — empty until new packages are configured. See docs/ADD_NEW_PACKAGE.md.
    'packages' => [
    ],
];
