<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class OrderController extends Controller
{
    public function store(Request $request)
    {
        /*
         * 2. Missing Caching
         * Cache must invalidate when data changes
         * 5. No DB Transaction in Order Creation
         * POST /api/orders — if one item fails, partial data is saved
         * Wrap in DB::transaction()
         */
        $validate = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'items'       => 'required|array',
        ]);

        $totalAmount = 0;
        try {
            return DB::transaction(function() use ($validate, $request, &$totalAmount) {
                $order = Order::create([
                    'customer_id'  => $validate['customer_id'],
                    'total_amount' => 0,
                    'status'       => 'pending',
                ]);

                foreach ($request->items as $item) {
                    $product = Product::findOrFail($item['product_id']);

                    if (!$product || $product->stock < $item['quantity']) {
                        throw new \Exception('Product unavailable');
                    }

                    OrderItem::create([
                        'order_id'   => $order->id,
                        'product_id' => $item['product_id'],
                        'quantity'   => $item['quantity'],
                        'unit_price' => $product->price,
                    ]);

                    $product->decrement('stock', $item['quantity']);
                    $product->increment('sold_count', $item['quantity']);

                    $totalAmount += $product->price * $item['quantity'];
                }

                $order->update(['total_amount' => $totalAmount]);
                
                Cache::flush(); // Invalidate cache when data changes
                return response()->json($order, 201);
            });
        } catch (\Exception $e) {
            Cache::flush(); // Invalidate cache when data changes
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function index(Request $request)
    {
        
        /**
         * 1. N+1 Query Problems
         * GET /api/orders — nested N+1 (order → items → product)
         * 2. Missing Caching
         * GET /api/orders no cache layer
         * Cache must invalidate when data changes
         * 3. No Pagination
         * /api/orders return ALL records
         * Add proper pagination (15 per page)
         */

        $page = $request->input('page', 1);
        return Cache::remember("orders_page_{$page}",3600, function() use ($page) {
            $orders = Order::with('items.product','customer')->paginate(15, ['*'], 'page', $page);
            $data = [];
            foreach ($orders as $order) {
                $data[] = [
                    'id'          => $order->id,
                    'customer'    => $order->customer->name,
                    'total'       => $order->total_amount,
                    'status'      => $order->status,
                    'items_count' => $order->items->count(),
                    'created_at'  => $order->created_at,
                ];
            }

            return response()->json([
                'data' => $data,
                'meta' => [
                    'current_page' => $page,
                    'last_page' => $orders->lastPage(),
                    'per_page' => $orders->perPage(),
                    'total' => $orders->total(),
                ]
            ]);
        });
    }

    public function filterByStatus(Request $request)
    {
        /**
         * @var mixed
         * 6. SQL Injection Risk
         * GET /api/orders/filter?status= — raw query with direct variable
         * Fix using Eloquent
         */
        $validate = $request->validate([
            'status' => 'required|string|in:pending,completed,cancelled',
        ]);
        $page = $request->input('page', 1);
        $status = $validate['status'];

        $orders = Order::where('status', $status)->paginate(15, ['*'], 'page', $page);
        return response()->json([
            'data' => $orders,
            'meta' => [
                'current_page' => $page,
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ]
        ]);
    }
}
