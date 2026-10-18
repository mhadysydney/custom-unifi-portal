<?php
/**
 * Configuration des 3 UDR7
 * Remplace les valeurs par les tiennes
 */
$secrets = require __DIR__ . '/secrets.php';
return [
    // Clé Site Manager (une seule si les 3 consoles sont sur le même compte UI)
    // Générée sur https://unifi.ui.com → Account → API
    'site_manager_api_key' => $secrets['site_manager_api_key'],

    'sites' => [
        'site1' => [   
            'name'        => 'WIFI ZONE IRAFOULOUN(KENIEN)',
            'country'     => 'GN',
            'currency'    => 'GNF',
            'console_id'  => '84784898C4860000000008D72567000000000950012C0000000067CFD3F8:103891448', // depuis l’URL unifi.ui.com
            'site_id'     => 'default',   // short name du site Network
            'method'      => 'site_manager', // ou 'direct'
            // Si method = direct :
            // 'url'      => 'https://IP_OU_HOSTNAME_UDR7',
            // 'api_key'  => 'API_KEY_LOCALE',   // ou user/pass
            // 'user'     => 'localadmin',
            // 'pass'     => 'password',
            'packages' => [
                ['name' => '12 heure',  'minutes' => 720,   'price' => 3000,  'desc' => 'Accès 12h'],
                ['name' => '1 jour',   'minutes' => 1440, 'price' => 5000,  'desc' => 'Accès 24h'],
                ['name' => '7 jours',  'minutes' => 10080,'price' => 25000, 'desc' => 'Accès 1 semaine'],
                ['name' => '1 mois',  'minutes' => 43200,'price' => 85000, 'desc' => 'Accès 1 mois'],
            ],
            'welcome' => 'Bienvenue sur notre portail',
            'lang'    => 'fr',
            'supportText' => 'Pour toute assistance, contactez nous au 00224 622 22 22 24',
        ],
        'site2' => [   
            'name'        => 'WIFI ZONE IRAFOULOUN (KANIA)',
            'country'     => 'GN',
            'currency'    => 'GNF',
            'console_id'  => '84784894B92B0000000008BA23A30000000009318F3A0000000067A88527:964234772', // depuis l’URL unifi.ui.com
            'site_id'     => 'default',   // short name du site Network
            'method'      => 'site_manager', // ou 'direct'
            // Si method = direct :
            // 'url'      => 'https://IP_OU_HOSTNAME_UDR7',
            // 'api_key'  => 'API_KEY_LOCALE',   // ou user/pass
            // 'user'     => 'localadmin',
            // 'pass'     => 'password',
            'packages' => [
                ['name' => '12 heure',  'minutes' => 720,   'price' => 3000,  'desc' => 'Accès 12h'],
                ['name' => '1 jour',   'minutes' => 1440, 'price' => 5000,  'desc' => 'Accès 24h'],
                ['name' => '7 jours',  'minutes' => 10080,'price' => 25000, 'desc' => 'Accès 1 semaine'],
                ['name' => '1 mois',  'minutes' => 43200,'price' => 85000, 'desc' => 'Accès 1 mois'],
            ],
            'welcome' => 'Bienvenue sur notre portail',
            'lang'    => 'fr',
            'supportText' => 'Pour toute assistance, contactez nous au 00224 622 22 22 22',
        ],
        'site3' => [
            'name'        => 'BillosHome Wifi',
            'country'     => 'LBR',
            'currency'    => 'LBR',
            'console_id'  => '9041B27656190000000009D5E89E000000000A6AAFF10000000069893F09:380727508', // depuis l’URL unifi.ui.com
            'site_id'     => 'default',   // short name du site Network
            'method'      => 'site_manager', // ou 'direct'
            // Si method = direct :
            // 'url'      => 'https://IP_OU_HOSTNAME_UDR7',
            // 'api_key'  => 'API_KEY_LOCALE',   // ou user/pass
            // 'user'     => 'localadmin',
            // 'pass'     => 'password',
            'packages' => [
                
                ['name' => '1 day',   'minutes' => 1440, 'price' => 100,  'desc' => '24h unlimited access'],
                ['name' => '2 days',  'minutes' => 2880,   'price' => 250,  'desc' => '2 days unlimited access'],
                ['name' => '7 days',  'minutes' => 10080,'price' => 500, 'desc' => '1 week unlimited access'],
                ['name' => '1 month',  'minutes' => 43200,'price' => 1000, 'desc' => '1 month unlimited access'],
            ],
            'welcome' => 'Welcome to our portal',
            'lang'    => 'en',
            'supportText' => 'For any assistance, contact us at +231 77 731 8666',
        ],
    ],

    // Mapping rapide si tu veux identifier le site autrement
    // (SSID, ou paramètre ?site= dans l’URL du portal)
    'default_site' => 'site2',
];