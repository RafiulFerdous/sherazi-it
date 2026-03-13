<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Category;
use App\Models\Order;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::all();

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

        return response()->json($result);
    }

    public function salesReport()
    {
        $orders = Order::all();

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

        return response()->json($report);
    }

    public function dashboard()
    {
        $totalProducts = Product::all()->count();
        $totalOrders   = Order::all()->count();
        $totalRevenue  = Order::all()->sum('total_amount');
        $categories    = Category::all();

        $topProducts = Product::all()
            ->sortByDesc('sold_count')
            ->take(5)
            ->values();

        return response()->json([
            'total_products' => $totalProducts,
            'total_orders'   => $totalOrders,
            'total_revenue'  => $totalRevenue,
            'categories'     => $categories,
            'top_products'   => $topProducts,
        ]);
    }

    public function search(Request $request)
    {
        $keyword  = $request->input('q');
        $products = Product::where('name', 'LIKE', '%' . $keyword . '%')
                           ->orWhere('description', 'LIKE', '%' . $keyword . '%')
                           ->get();

        return response()->json($products);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'        => 'required|string|max:255',
            'price'       => 'required|numeric|min:0',
            'stock'       => 'required|integer|min:0',
            'category_id' => 'required|exists:categories,id',
        ]);

        $product = Product::create($request->all());

        return response()->json($product, 201);
    }
}
