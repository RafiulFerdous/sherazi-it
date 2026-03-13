<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'items'       => 'required|array',
        ]);
        DB::beginTransaction();
        try{
            $totalAmount = 0;

            $order = Order::create([
                'customer_id'  => $request->customer_id,
                'total_amount' => 0,
                'status'       => 'pending',
            ]);

            foreach ($request->items as $item) {
                $product = Product::where('id', $item['product_id'])->lockForUpdate()->first();

                if (!$product || $product->stock < $item['quantity']) {
                    throw new \Exception('Product out of stock');
//                    return response()->json(['error' => 'Product unavailable'], 422);
                }

                OrderItem::create([
                    'order_id'   => $order->id,
                    'product_id' => $item['product_id'],
                    'quantity'   => $item['quantity'],
                    'unit_price' => $product->price,
                ]);

                $product->decrement('stock', $item['quantity']);
                $totalAmount += $product->price * $item['quantity'];
                $order->update(['total_amount' => $totalAmount]);
                DB::commit();
                return response()->json($order, 201);


            }



        }catch (\Exception $exception){
            DB::rollBack();
        }

    }

    public function index()
    {
        $orders = Order::all();

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

        return response()->json($data);
    }

    public function filterByStatus(Request $request):JsonResponse
    {
        $validated=$request->validate([
            'status' => 'required|string|in:pending,completed,cancelled',
        ]);
        $orders=Order::query()
            ->where('status',$validated['status'])
            ->latest()
            ->paginate(15);

        return response()->json($orders);
//        $status = $request->input('status');
//
//        $orders = DB::select("SELECT * FROM orders WHERE status = '$status'");
//
//        return response()->json($orders);
    }
}
