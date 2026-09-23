<?php

/*
|--------------------------------------------------------------------------
| Mini App texts
|--------------------------------------------------------------------------
| ui goes to the frontend as a whole, errors are API responses.
*/

return [
    'ui' => [
        'title' => 'Catalog',
        'browser_notice' => 'This page is open in a browser. You can browse the catalog, but requests can only be placed from the Telegram bot.',
        'empty' => 'The catalog is empty for now. Check back a little later.',
        'add_to_cart' => 'Add to cart',
        'minutes' => ':count min',
        'remove_one' => 'Remove one',
        'add_one' => 'Add one more',
        'back_to_catalog' => '← Back to catalog',
        'cart' => 'Cart',
        'total' => 'Total',
        'contacts' => 'Contacts',
        'name' => 'Your name',
        'phone' => 'Phone',
        'comment' => 'Comment (optional)',
        'comment_placeholder' => 'Convenient time, wishes',
        'submit' => 'Place a request',
        'done_title' => 'Request #:number received',
        'done_text' => "Total: :total. We'll contact you shortly — the confirmation is already in the bot chat.",
        'paid_text' => 'Payment complete. The receipt and request status will arrive in the bot chat.',
        'cart_button' => 'Cart · :count · :total',
        'submit_button' => 'Place a request · :total',
        'pay_button' => 'Pay :total',
        'close' => 'Close',
        'only_in_telegram' => 'Requests can only be placed from Telegram — open the catalog with the bot button.',
        'network_error' => "Couldn't reach the server.",
    ],

    'errors' => [
        'unauthorized' => 'Open the catalog from the Telegram bot.',
        'services_unavailable' => 'Some services are no longer available. Refresh the catalog and check your cart.',
        'phone' => 'Enter the full phone number, e.g. +7 900 123-45-67.',
    ],

    'fields' => [
        'items' => 'cart',
        'contact_name' => 'name',
        'contact_phone' => 'phone',
        'comment' => 'comment',
    ],
];
