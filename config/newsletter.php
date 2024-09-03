<?php

return [

    /*
     * The driver to use to interact with MailChimp API.
     * You may use "log" or "null" to prevent calling the
     * API directly from your environment.
     */
    'driver' => env('NEWSLETTER_DRIVER', Spatie\Newsletter\Drivers\MailChimpDriver::class),

    /**
     * These arguments will be given to the driver.
     */
    'driver_arguments' => [
        'api_key' => env('NEWSLETTER_API_KEY'),
    ],

    'lists' => [
        'subscribers' => [

            'id' => env('NEWSLETTER_LIST_ID'),
        ],
    ],
];
