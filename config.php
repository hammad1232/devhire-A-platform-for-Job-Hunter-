<?php

return [
    'app' => [
        'base_path' => '/',
    ],
    'database' => [
    'host' => 'Your_database_host_here',  // ← You need YOUR database host
    'name' => 'Database_name_here',  // ← You need YOUR database name
    'user' => 'Username_here',  // ← You need YOUR database username
    'pass' => 'Password_here',  // ← You need YOUR database password
],
    'services' => [
        'gemini' => [
            'api_key' => 'Your_Gemini_API_Key_here',  // ← You need YOUR Gemini API Key
            'model' => 'Your_Gemini_Model_here',  // ← You need YOUR Gemini Model (e.g., 'gemma-3-4b-it')
            'fallback_models' => 'Your_Gemini_Fallback_Models_here',  // ← You need YOUR Gemini Fallback Models (e.g., 'gemma-3-4b-it,gemma-3-4b-it-v2')
            'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
        ],
        'oauth' => [
            'google' => [
                'client_id' => 'Your_Google_Client_ID_here',
                'client_secret' => 'Your_Google_Client_Secret_here',
                'redirect_uri' => 'https://devhirehub.infinityfreeapp.com/auth/social_auth.php?provider=google&action=callback',
            ],
            'github' => [
                'client_id' => 'Your_GitHub_Client_ID_here',
                'client_secret' => 'Your_GitHub_Client_Secret_here',
                'redirect_uri' => 'https://devhirehub.infinityfreeapp.com/auth/social_auth.php?provider=github&action=callback',
            ],
        ],
    ],
];