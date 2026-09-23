<?php

namespace App\Enums;

/**
 * События воронки. Приход в бота, заявку и оплату отдельно не пишем:
 * их видно по telegram_users, orders и payments.
 */
enum BotEventType: string
{
    case CatalogViewed = 'catalog_viewed';
    case OrderStarted = 'order_started';
}
