<?php
return [
    // AI App Database: used for chat, analytics logs, admin panel.
    'AI_DB_HOST' => 'YOUR_AI_DB_HOST',
    'AI_DB_NAME' => 'YOUR_AI_DB_NAME',
    'AI_DB_USER' => 'YOUR_AI_DB_USER',
    'AI_DB_PASS' => 'YOUR_AI_DB_PASS',

    // PulseCore Database (read-only credentials).
    'PCORE_DB_HOST' => 'YOUR_PCORE_DB_HOST',
    'PCORE_DB_NAME' => 'YOUR_PCORE_DB_NAME',
    'PCORE_DB_USER' => 'YOUR_PCORE_DB_USER',
    'PCORE_DB_PASS' => 'YOUR_PCORE_DB_PASS',

    // Optional PulseCore bridge-writer credentials.
    'PCORE_BRIDGE_DB_HOST' => 'YOUR_PCORE_BRIDGE_DB_HOST',
    'PCORE_BRIDGE_DB_NAME' => 'YOUR_PCORE_BRIDGE_DB_NAME',
    'PCORE_BRIDGE_DB_USER' => 'YOUR_PCORE_BRIDGE_DB_USER',
    'PCORE_BRIDGE_DB_PASS' => 'YOUR_PCORE_BRIDGE_DB_PASS',

    // Secret for PHP sessions; replace with a long random string.
    'SESSION_SECRET' => 'YOUR_LONG_RANDOM_SESSION_SECRET',
];
