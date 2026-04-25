<?php

declare(strict_types=1);

return [
    'menu_url' => 'https://yourdomain/speiseplan/speiseplan.xml',
    'cache_ttl' => 1800,
    'debug_date' => null,
    'default_language' => 'de',
    'default_mensa' => 'mensa',
    'default_exclude' => [1,2,3],
    'default_display_co2' => true,
    'default_display_water' => false,
    'default_display_animal_welfare' => false,
    'default_display_rainforest' => false,
    'food_types' => [
        48 => 'mensa_tipp_vegan_legacy',
        49 => 'vegan',
        100 => 'eintopf',
        101 => 'eintopf_vegan',
        102 => 'eintopf_vegetarian',
        103 => 'eintopf_no_price',
        104 => 'main_1',
        105 => 'main_1_vegan',
        106 => 'main_1_vegetarian',
        107 => 'main_1_no_price',
    ],
    'mensen' => [
        'mensa' => '111',
        'cafeteria' => '112',
    ],
    'standort_namen' => [
        111 => 'Mensa',
        112 => 'Cafeteria',
    ],
    'category_display' => [
        'vegan' => 'vegan',
        'vegetarian' => 'vegetarian',
        'fish' => 'fish',
        'meat' => 'meat',
     ],
];
