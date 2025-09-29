<?php

namespace App\Http\Controllers;

use App\Services\OrderService;
use Illuminate\Http\Request;

class OrderFromTextController extends Controller
{
    public function __invoke(Request $request, OrderService $order)
    {
        $text = (string) $request->input('text', '');
        $result = $order->processCommand($text);
        return response()->json($result);
    }
}
