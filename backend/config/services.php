<?php

return [

    'resend' => [
        'key' => getenv('RESEND_API_KEY') ?: env('RESEND_API_KEY', ''),
    ],

];
