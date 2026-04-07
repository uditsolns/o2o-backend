<?php

return [
    'base_url' => env('SEPIO_BASE_URL', 'https://api-test.sepioproducts.com'),
    'register_from_type' => 'ILGIC',        // confirmed field name
    'distributor_id' => env('SEPIO_DISTRIBUTOR_ID', 'D100247'),
    'encrypt_key' => env('SEPIO_ENCRYPT_KEY'),   // 32-char UTF-8
    'encrypt_iv' => env('SEPIO_ENCRYPT_IV'),    // 16-char UTF-8
];
