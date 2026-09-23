<?php

/*
|--------------------------------------------------------------------------
| Bot texts for clients
|--------------------------------------------------------------------------
| Markup is Telegram HTML. Admin notifications are not translated.
*/

return [
    'greeting' => "Hello, <b>:name</b>!\n\nI'll help you pick a service and place a request — it takes less than a minute.\nChoose a section:",
    'main_menu' => "<b>Main menu</b>\n\nWhat are you interested in?",

    'help' => [
        'title' => '<b>How it works</b>',
        'steps' => "1. Open the service catalog and pick a service.\n2. Tap “Place a request” and answer three short questions.\n3. We'll contact you and confirm the request.",
        'payments' => 'You can pay by card right in the bot — the “Pay” button appears once the request is placed.',
        'status' => 'The request status is always shown in “My requests”.',
        'commands' => 'Commands: /start — main menu, /help — this help, /orders — my requests, /language — language.',
    ],

    'catalog' => [
        'empty' => 'The catalog is empty for now. Check back a little later.',
        'title' => "<b>Service catalog</b>\n\nChoose a category:",
        'empty_category' => 'There are no services in this category yet. Choose another one.',
        'choose_service' => 'Choose a service:',
        'price' => 'Price: <b>:price</b>',
        'duration' => 'Duration: :minutes min',
    ],

    'order' => [
        'ask_name' => "New request: <b>:service</b>\n\nWhat's your name?",
        'ask_phone' => 'Leave your phone number — tap the button below or type it in.',
        'ask_comment' => 'Add a comment (convenient time, wishes). Nothing to add? Send “-”.',
        'invalid_name' => 'Please type your name — up to 100 characters.',
        'invalid_phone' => "That doesn't look like a phone number. Example: +7 900 123-45-67",
        'confirm_title' => '<b>Please check your request</b>',
        'service' => 'Service: :value',
        'price' => 'Price: :value',
        'name' => 'Name: :value',
        'phone' => 'Phone: :value',
        'comment' => 'Comment: :value',
        'confirm_question' => 'Is everything correct?',
        'press_confirm' => 'Tap “Confirm” or “Cancel”.',
        'finish_first' => 'Finish the request first or tap “Cancel”.',
        'service_unavailable' => 'This service is no longer available.',
        'service_unavailable_restart' => 'This service is no longer available. Start over: /start',
        'created' => '✅ Request <b>#:number</b> received!',
        'created_footer' => "We'll contact you shortly. You can track the status in “My requests”.",
        'created_pay' => 'You can pay right away — use the button below.',
        'contents' => 'Items:',
        'cancelled' => 'Request cancelled. Back to the main menu.',
    ],

    'orders' => [
        'empty' => "You don't have any requests yet. Take a look at the catalog.",
        'title' => '<b>Your requests</b>',
        'line' => '<b>#:number</b> — :status',
        'from' => 'from :date',
        'paid' => '💳 Paid',
        'unpaid' => 'Not paid',
        'and_more' => 'and :count more',
        'status_changed' => 'Request <b>#:number</b> — status: <b>:status</b>',
    ],

    'status' => [
        'new' => 'New',
        'confirmed' => 'Confirmed',
        'in_progress' => 'In progress',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ],

    'status_text' => [
        'new' => 'The request has been created and is waiting to be processed.',
        'confirmed' => "We've confirmed your request and will contact you soon.",
        'in_progress' => "We're working on your request.",
        'completed' => 'The request is complete. Thank you for choosing us!',
        'cancelled' => 'The request has been cancelled. If this is a mistake, please write to us.',
    ],

    'payment' => [
        'invoice_title' => 'Request #:number',
        'invoice_description' => 'Payment: :name',
        'unavailable' => "This request can't be paid right now: it's already paid or cancelled.",
        'failed' => "Couldn't create the invoice. Please try again later or write to us.",
        'received' => "✅ Payment for request <b>#:number</b> received: :amount.\n\nThank you! The status is in “My requests”.",
        'duplicate' => "Request <b>#:number</b> had already been paid, and we received another payment of :amount.\n\nWe'll refund the extra money — the administrator has been notified.",
        'refunded' => "Refund for request <b>#:number</b> issued: :amount.\n\nThe money will return to the card you paid with. Timing depends on your bank.",
        'not_found' => 'Invoice not found. Open “My requests” and request a new one.',
        'order_cancelled' => 'The request was cancelled — no payment needed.',
        'already_paid' => 'This request is already paid.',
        'amount_changed' => 'The amount has changed. Request a new invoice in “My requests”.',
    ],

    'language' => [
        'choose' => 'Выберите язык / Choose your language:',
        'changed' => 'Done, I speak English now.',
    ],

    'unknown_input' => "I didn't understand that. Open the main menu with /start.",

    'buttons' => [
        'mini_app' => '🛒 Catalog & cart',
        'catalog' => '🛍 Service catalog',
        'my_orders' => '📋 My requests',
        'about' => 'ℹ️ About & contacts',
        'language' => '🌐 Язык / Language',
        'back' => '⬅️ Back',
        'order' => '✅ Place a request',
        'cancel_order' => '✖️ Cancel request',
        'confirm' => '✅ Confirm',
        'cancel' => '✖️ Cancel',
        'share_phone' => '📱 Share my number',
        'pay_amount' => '💳 Pay :amount',
        'pay_number' => '💳 Pay #:number',
    ],

    'commands' => [
        'start' => 'Main menu',
        'help' => 'How to place a request',
        'orders' => 'My requests',
        'language' => 'Change language',
    ],

    'menu_button' => 'Catalog',
];
