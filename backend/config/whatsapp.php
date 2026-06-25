<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Driver d'envoi
    |--------------------------------------------------------------------------
    | 'log'    : n'envoie rien réellement (écrit dans les logs) — zéro config.
    | 'twilio' : envoie via l'API Twilio WhatsApp si les clés sont renseignées.
    |
    | Dans tous les cas, l'API renvoie un lien wa.me permettant d'ouvrir
    | WhatsApp avec le message pré-rempli (gratuit, sans compte).
    */
    'driver' => env('WHATSAPP_DRIVER', 'log'),

    // Indicatif pays par défaut pour les numéros locaux (Tunisie = 216).
    'country_code' => env('WHATSAPP_COUNTRY_CODE', '216'),

    'twilio' => [
        'sid'   => env('TWILIO_SID'),
        'token' => env('TWILIO_TOKEN'),
        'from'  => env('TWILIO_WHATSAPP_FROM'), // ex : +14155238886
    ],

    'templates' => [
        'reminder' => "Bonjour :client,\nNous vous rappelons que votre solde impayé chez :org s'élève à :solde.\nMerci de bien vouloir régulariser. Cordialement, :org.",
        'stock'    => "⚠️ Alerte stock — :org\nProduits à réapprovisionner :\n:liste",
    ],
];
