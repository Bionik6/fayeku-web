<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'whatsapp' => [
        'base_url' => env('WHATSAPP_BASE_URL', 'https://graph.facebook.com'),
        'api_version' => env('WHATSAPP_API_VERSION', 'v21.0'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
        'default_language' => env('WHATSAPP_DEFAULT_LANGUAGE', 'fr'),
        'templates' => [
            // Clé = offset en jours par rapport à l'échéance ( -3 = 3 jours avant, +7 = 7 jours après, …)
            'reminder' => [
                -3 => env('WHATSAPP_TEMPLATE_REMINDER_M3', 'fayeku_reminder_invoice_due_auto_m3'),
                0 => env('WHATSAPP_TEMPLATE_REMINDER_0', 'fayeku_reminder_invoice_due_auto_0'),
                3 => env('WHATSAPP_TEMPLATE_REMINDER_P3', 'fayeku_reminder_invoice_due_auto_p3'),
                7 => env('WHATSAPP_TEMPLATE_REMINDER_P7', 'fayeku_reminder_invoice_due_auto_p7'),
                15 => env('WHATSAPP_TEMPLATE_REMINDER_P15', 'fayeku_reminder_invoice_due_auto_p15'),
                30 => env('WHATSAPP_TEMPLATE_REMINDER_P30', 'fayeku_reminder_invoice_due_auto_p30'),
                60 => env('WHATSAPP_TEMPLATE_REMINDER_P60', 'fayeku_reminder_invoice_due_auto_p60'),
            ],
            'notifications' => [
                'invoice_sent' => env('WHATSAPP_TEMPLATE_NOTIF_INVOICE_SENT', 'fayeku_notification_invoice_sent'),
                'invoice_sent_with_due' => env('WHATSAPP_TEMPLATE_NOTIF_INVOICE_SENT_WITH_DUE', 'fayeku_notification_invoice_sent_with_due'),
                'invoice_paid_full' => env('WHATSAPP_TEMPLATE_NOTIF_INVOICE_PAID_FULL', 'fayeku_notification_invoice_paid_full'),
                'invoice_partially_paid' => env('WHATSAPP_TEMPLATE_NOTIF_INVOICE_PARTIALLY_PAID', 'fayeku_notification_invoice_partially_paid'),
                'quote_sent' => env('WHATSAPP_TEMPLATE_NOTIF_QUOTE_SENT', 'fayeku_notification_quote_sent'),
            ],
        ],
    ],

    'orange_sms' => [
        'base_url' => env('ORANGE_SMS_BASE_URL', 'https://api.orange.com'),
        'client_id' => env('ORANGE_SMS_CLIENT_ID'),
        'client_secret' => env('ORANGE_SMS_CLIENT_SECRET'),
        'sender_address' => env('ORANGE_SMS_SENDER_ADDRESS'),
        'sender_name' => env('ORANGE_SMS_SENDER_NAME', 'Fayeku'),
    ],

];
