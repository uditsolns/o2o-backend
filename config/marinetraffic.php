<?php

return [
    'vessel_api_key' => env('MT_VESSEL_API_KEY'),
    'vessel_base_url' => 'https://services.marinetraffic.com/api',
    'container_api_key' => env('MT_CONTAINER_API_KEY'),
    'container_base_url' => 'https://api.kpler.com/v1/logistics/containers',
    // AIS poll: every 30 min. Vessel position timespan window (minutes)
    'vessel_timespan' => env('MT_VESSEL_TIMESPAN', 60),
];
