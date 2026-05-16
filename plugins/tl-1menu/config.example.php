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
    'default_show_header' => true,
    'default_background_color' => '#f1f5f9',
    // Plugin-relative paths or absolute URLs. Symbol sprites should include the #icon fragment.
    'environment_rating_icons' => [
        'co2' => 'assets/img/eco-leaf.svg#icon',
        'water' => 'assets/img/eco-drop.svg#icon',
        'animal_welfare' => 'assets/img/eco-heart.svg#icon',
        'rainforest' => 'assets/img/eco-tree.svg#icon',
    ],
    'pseudo_allergen_category_map' => [
        'VE' => 'vegetarian',
        'VN' => 'vegan',
        'Fi' => 'fish',
        'STF' => 'streetfood',
        'SHT' => 'sh_teller',
        'KK' => 'kuechenklassiker',
        'YF' => 'your_favorite',
        'AGS' => 'pork_higher_welfare',
        'S' => 'pork',
        'AGF' => 'fish_higher_welfare',
        'G' => 'poultry',
        'AGR' => 'beef_higher_welfare',
        'R' => 'beef',
        'MV' => 'mensa_vital',
        'INTERNATIONAL' => 'international',
    ],
    'mensen' => [
        'mensa' => [
            'label' => 'Mensa',
            'locations' => [111],
        ],
        'cafeteria' => [
            'label' => 'Cafeteria',
            'locations' => [112],
        ],
    ],
    'standort_namen' => [
        111 => 'Mensa',
        112 => 'Cafeteria',
    ],
    'categories' => [
        'vegan' => [
            'icon' => '🌿',
            'labels' => [
                'de' => 'Vegan',
                'en' => 'Vegan',
            ],
        ],
        'vegetarian' => [
            'icon' => '🥕',
            'labels' => [
                'de' => 'Vegetarisch',
                'en' => 'Vegetarian',
            ],
        ],
        'fish' => [
            'icon' => '🐟',
            'labels' => [
                'de' => 'Fisch',
                'en' => 'Fish',
            ],
        ],
        'meat' => [
            'icon' => '🍖',
            'labels' => [
                'de' => 'Fleisch',
                'en' => 'Meat',
            ],
        ],
        'streetfood' => [
            'icon' => '🍔',
            'labels' => [
                'de' => 'Streetfood',
                'en' => 'Streetfood',
            ],
        ],
        'sh_teller' => [
            'icon' => '🍽',
            'labels' => [
                'de' => 'SH Teller',
                'en' => 'SH Teller',
            ],
        ],
        'kuechenklassiker' => [
            'icon' => '👨‍🍳',
            'labels' => [
                'de' => 'Küchenklassiker',
                'en' => 'Kitchen classic',
            ],
        ],
        'your_favorite' => [
            'icon' => '⭐',
            'labels' => [
                'de' => 'Your Favorite',
                'en' => 'Your Favorite',
            ],
        ],
        'pork_higher_welfare' => [
            'icon' => '🐖',
            'labels' => [
                'de' => 'Schwein aus artgerechter Haltung',
                'en' => 'Pork from higher-welfare farming',
            ],
        ],
        'pork' => [
            'icon' => '🐖',
            'labels' => [
                'de' => 'Schwein',
                'en' => 'Pork',
            ],
        ],
        'fish_higher_welfare' => [
            'icon' => '🐟',
            'labels' => [
                'de' => 'Fisch aus artgerechter Haltung',
                'en' => 'Fish from higher-welfare sourcing',
            ],
        ],
        'poultry' => [
            'icon' => '🐔',
            'labels' => [
                'de' => 'Geflügel',
                'en' => 'Poultry',
            ],
        ],
        'beef_higher_welfare' => [
            'icon' => '🐄',
            'labels' => [
                'de' => 'Rind aus artgerechter Haltung',
                'en' => 'Beef from higher-welfare farming',
            ],
        ],
        'beef' => [
            'icon' => '🐄',
            'labels' => [
                'de' => 'Rind',
                'en' => 'Beef',
            ],
        ],
        'mensa_vital' => [
            'icon' => '💚',
            'labels' => [
                'de' => 'Mensa Vital',
                'en' => 'Mensa Vital',
            ],
        ],
        'international' => [
            'icon' => '🌍',
            'labels' => [
                'de' => 'International',
                'en' => 'International',
            ],
        ],
        'bio' => [
            'icon' => '🌱',
            'labels' => [
                'de' => 'Bio',
                'en' => 'Organic',
            ],
        ],
    ],
    'food_types' => [
        48 => [
            'key' => 'mensa_tipp_vegan_legacy',
            'categories' => ['vegan'],
            'labels' => [
                'de' => 'Mensa-Tipp vegan (alt)',
                'en' => 'Mensa tip vegan (legacy)',
            ],
        ],
        49 => [
            'key' => 'vegan',
            'categories' => ['vegan'],
            'labels' => [
                'de' => 'Vegan',
                'en' => 'Vegan',
            ],
        ],
        100 => [
            'key' => 'eintopf',
            'labels' => [
                'de' => 'Eintopf',
                'en' => 'Stew',
            ],
        ],
        101 => [
            'key' => 'eintopf_vegan',
            'categories' => ['vegan'],
            'labels' => [
                'de' => 'Eintopf vegan',
                'en' => 'Stew vegan',
            ],
        ],
        102 => [
            'key' => 'eintopf_vegetarian',
            'categories' => ['vegetarian'],
            'labels' => [
                'de' => 'Eintopf vegetarisch',
                'en' => 'Stew vegetarian',
            ],
        ],
    ],
];
