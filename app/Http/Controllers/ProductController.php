<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Category;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        /**
         * 1. N+1 Query Problems
         * GET /api/products — category loaded per product in a loop
         * 2. Missing Caching
         * GET /api/products no cache layer
         * Cache must invalidate when data changes
         * 3. No Pagination
         * /api/products return ALL records
         * Add proper pagination (15 per page)
         */
        $page = $request->input('page', 1);
        return Cache::remember("products_page_{$page}",3600, function() use ($page) {
            $products = Product::with('category')->paginate(15, ['*'], 'page', $page);
            $result = [];
            foreach ($products as $product) {
                $result[] = [
                    'id'       => $product->id,
                    'name'     => $product->name,
                    'price'    => $product->price,
                    'stock'    => $product->stock,
                    'category' => $product->category->name,
                ];
            }

            return response()->json([
                'data' => $result,
                'meta' => [
                    'current_page' => $page,
                    'last_page' => $products->lastPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                ]
            ]);
        });
    }

    public function salesReport(Request $request)
    {
        /**
         * 1. N+1 Query Problems
         * GET /api/products/sales-report — nested N+1 (order → items → product)
         * 2. Missing Caching
         * GET /api/products/sales-report no cache layer
         * Cache must invalidate when data changes
         * 3. No Pagination
         * /api/products/sales-report return ALL records
         * Add proper pagination (15 per page)
         */
        $page = $request->input('page', 1);
        return Cache::remember("sales_report_page_{$page}",3600, function() use ($page) {
            $orders = Order::with('items.product','customer')->paginate(15, ['*'], 'page', $page);

            $report = [];
            foreach ($orders as $order) {
                foreach ($order->items as $item) {
                    $report[] = [
                        'order_id'     => $order->id,
                        'product_name' => $item->product->name,
                        'qty'          => $item->quantity,
                        'total'        => $item->quantity * $item->product->price,
                        'customer'     => $order->customer->name,
                    ];
                }
            }

            return response()->json([
                'data' => $report,
                'meta' => [
                    'current_page' => $page,
                    'last_page' => $orders->lastPage(),
                    'per_page' => $orders->perPage(),
                    'total' => $orders->total()
                ]
            ]);
        });
    }

    public function dashboard()
    {

        /**
         * 2. Missing Caching
         * GET /api/products/dashboard hits DB every request
         * Cache must invalidate when data changes
         * 7. Inefficient Counting & AggregationProduct::all()->count() — loads all rows into memory just to count
         * Use Product::count() and DB:: aggregates instead
         */
    
        $totalProducts = Product::count();
        $totalOrders   = Order::count();
        $totalRevenue  = Order::sum('total_amount');
        $categories    = Category::all();
        $topProducts   = Product::orderByDesc('sold_count')->take(5)->get();
        return Cache::remember('dashboard_stats', 3600, function() use ($totalProducts, $totalOrders, $totalRevenue, $categories, $topProducts) {
            return [
                'total_products' => $totalProducts,
                'total_orders'   => $totalOrders,
                'total_revenue'  => $totalRevenue,
                'categories'     => $categories,
                'top_products'   => $topProducts,
            ];
        });
    }

    public function search(Request $request)
    {
        //added validation for search query for safer and more robust code
        $validate = $request->validate([
            'q' => 'required|string|max:255',
        ]);
        $keyword  = $validate['q'];
        $page = $request->input('page', 1);
        $products = Product::where('name', 'LIKE', "%{$keyword}%")
                           ->orWhere('description', 'LIKE', "%{$keyword}%")
                           ->paginate(15, ['*'], 'page', $page);

        return response()->json([
            'data' => $products,
            'meta' => [
                'current_page' => $page,
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ]
        ]);
    }

    public function store(Request $request)
    {
        /*
         * 2. Missing Caching
         * Cache must invalidate when data changes
         */
        $request->validate([
            'name'        => 'required|string|max:255',
            'price'       => 'required|numeric|min:0',
            'stock'       => 'required|integer|min:0',
            'category_id' => 'required|exists:categories,id',
        ]);

        $product = Product::create($request->only(['name', 'price', 'stock', 'category_id']));
        Cache::flush(); // Invalidate cache when data changes
        return response()->json($product, 201);
    }
}
