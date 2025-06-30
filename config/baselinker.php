<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Baselinker Integration Settings
    |--------------------------------------------------------------------------
    |
    | Tutaj znajdują się wszystkie ustawienia dotyczące integracji z Baselinker,
    | dostosowane do nowego API "Product Catalog".
    |
    */

    'api' => [
        'token' => env('BASELINKER_TOKEN'),
        'url' => env('BASELINKER_API_URL', 'https://api.baselinker.com'),
    ],

    /**
     * Domyślny ID katalogu produktów w Baselinker (z metody getInventories).
     */
    'inventory_id' => 61105,

    /**
     * Domyślny ID grupy cenowej w Baselinker (z metody getInventories).
     */
    'price_group_id' => 53415,

    /*
    |--------------------------------------------------------------------------
    | Mapowanie Magazynów
    |--------------------------------------------------------------------------
    |
    | Klucz: ID magazynu w Twojej bazie danych (tabela `warehouses`).
    | Wartość: ID magazynu w Baselinker (z metody getInventories, np. 'bl_12345').
    |
    */
    'warehouses' => [
        // Zaktualizowano na podstawie odpowiedzi z API
        '1' => 'bl_89554',
    ],

    /*
    |--------------------------------------------------------------------------
    | Mapowanie Statusów Zamówień (do wykorzystania w przyszłości)
    |--------------------------------------------------------------------------
    */
    'order_statuses' => [
        // 'nasz_status_id' => 'baselinker_status_id',
    ],
    'statuses' => [
        'new' => env('BASELINKER_STATUS_NEW', 8),
        'shipped' => env('BASELINKER_STATUS_SHIPPED', 4),
        'cancelled' => env('BASELINKER_STATUS_CANCELLED', 6),
        'returned' => env('BASELINKER_STATUS_RETURNED', 7),
    ],
];
