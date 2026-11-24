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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
    | تِلِغرام — إشعارات المبيعات
    |
    | كان هذا القسم يمرّر **القيمة** إلى `env()` بدل اسم المتغيّر:
    |
    |     'bot_token' => env('7642508596:AAHQ8sST762ErqUpX3Ni0f1WTeGZxiQWyXU'),
    |     'chat_id'   => env('@Salesfatrasmart'),
    |
    | و`env()` تبحث عن متغيّرٍ بيئيّ بهذا الاسم — ولا وجود له — فتردّ
    | `null` دائماً. فالإعداد قائمٌ في الملف ومعطَّلٌ في العمل، ولذلك
    | كتبت المتحكّمات الرمز في شيفرتها مباشرةً.
    |
    | الرمز المسرَّب أعلاه يجب أن يُبطَل من BotFather ويُولَّد غيره: كلّ من
    | لديه نسخةٌ من المستودع يملكه.
    */
    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'chat_id' => env('TELEGRAM_CHAT_ID'),
        'sales_chat_id' => env('TELEGRAM_SALES_CHAT_ID', env('TELEGRAM_CHAT_ID')),
        'api_url' => 'https://api.telegram.org/bot',
    ],

    // Firebase Cloud Messaging — إشعارات النقاط
    'firebase' => [
        'server_key' => env('FIREBASE_SERVER_KEY'),
    ],

    // خرائط جوجل — تُقرأ من config لا من env() داخل القوالب،
    // لأن env() يعيد null بعد `php artisan config:cache` فتظهر الخريطة فارغة.
    'google_maps' => [
        'key' => env('GOOGLE_MAPS_API_KEY'),
    ],

    // صور تطبيق الجوال: خادم التطبيق (Node) يرسلها بهذا المفتاح إلى
    // `POST api/mobile-media` — انظر `MobileMediaController`. فارغٌ = النقطة مطفأة
    'mobile_media' => [
        'token' => env('MOBILE_MEDIA_TOKEN'),
    ],

];
