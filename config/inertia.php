<?php

declare(strict_types=1);

return [

    'ssr' => [
        'enabled' => false,
    ],

    // Encrypt history-state snapshots so sensitive page props never sit in
    // the browser's history storage in clear text.
    'history' => [
        'encrypt' => true,
    ],

];
