<?php

return [
    'require_cloud_disk' => env(
        'MEDIA_REQUIRE_CLOUD_DISK',
        env('APP_ENV', 'production') === 'production',
    ),
];
