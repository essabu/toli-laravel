<?php

declare(strict_types=1);

return [
    /*
     * The key issued for this application on the gateway — toli_… for an API
     * key. Nothing works without it, and the SDK says so in one line rather
     * than attempting a call.
     */
    'key' => env('TOLI_API_KEY'),

    /*
     * The gateway. There is one, and this is only here so a staging gateway
     * can be pointed at; the engine underneath is not a thing to configure.
     */
    'base_url' => env('TOLI_BASE_URL', 'https://toli.essabu.com'),

    /* The model asked for when a call names none. Pin it. */
    'model' => env('TOLI_MODEL', 'toli-1'),

    /*
     * Where readings are kept. One row per subject and model, and a second
     * read of the same pair is served from here and never reaches the gateway.
     */
    'table' => env('TOLI_TABLE', 'toli_readings'),

    /*
     * Whether a recorded reading is broadcast (Reverb, Pusher — whatever the
     * application already uses). Off, the event still fires in-process; it is
     * just not put on a socket.
     */
    'broadcast' => (bool) env('TOLI_BROADCAST', true),
];
