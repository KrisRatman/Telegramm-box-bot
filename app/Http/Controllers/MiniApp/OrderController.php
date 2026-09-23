<?php

namespace App\Http\Controllers\MiniApp;

use App\Http\Controllers\Controller;
use App\Http\Requests\MiniApp\StoreOrderRequest;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class OrderController extends Controller
{
    public function store(StoreOrderRequest $request, OrderService $orders, PaymentService $payments): JsonResponse
    {
        $order = $orders->createFromCart(
            user: $request->attributes->get('telegram_user'),
            quantities: $request->quantities(),
            contactName: trim($request->validated('contact_name')),
            contactPhone: trim($request->validated('contact_phone')),
            comment: filled($request->validated('comment')) ? trim($request->validated('comment')) : null,
        );

        return response()->json([
            'number' => $order->number,
            'total' => $order->formatted_price,
            // null, если оплата не подключена: тогда Mini App просто закрывается.
            'invoice_link' => $payments->invoiceLink($order),
        ], Response::HTTP_CREATED);
    }
}
