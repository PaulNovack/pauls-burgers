<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\OrderService;

class OrderController extends Controller
{
    public function show(OrderService $order)
    {
        // return plain array (not assoc) for Vue v-for
        return response()->json([
            'items' => array_values($order->all()),
        ]);
    }

    public function command(Request $request, OrderService $order)
    {
        $text = (string) $request->input('text', '');
        $result = $order->processCommand($text);

        return response()->json([
            'action' => $result['action'],
            'items'  => array_values($result['items']),
        ]);
    }

    public function clear(OrderService $order)
    {
        return response()->json([
            'items' => $order->clear(),
        ]);
    }
}
