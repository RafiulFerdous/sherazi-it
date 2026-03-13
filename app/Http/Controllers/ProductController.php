<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProductResource;
use App\Http\Resources\SalesReportResource;
use App\Models\Product;
use App\Models\Category;
use App\Models\Order;
use http\Env\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ProductController extends Controller
{
    public function index()
    {
        try{
            $page=\request()->get('page',1);
            $data=Cache::tags(['products'])->remember('products_page_{$page}.'.$page, 60*60*24, function() {
                $products=Product::with('category')->orderBy('id','desc')->paginate(15);

                return[
                    'items'=>ProductResource::collection($products)->response()->getData()->data,
                    'meta'=>[
                        'current_page'=>$products->currentPage(),
                        'last_page'=>$products->lastPage(),
                        'total'=>$products->total(),
                    ],
                    'links'=>[
                        'next'=>$products->nextPageUrl(),
                        'prev'=>$products->previousPageUrl(),
                    ]
                ];


            });

            return response()->json([
                'status'=>'success',
                'message'=>'Product List',
                'data'=>$data['items'],
                'meta'=>$data['meta'],
                'links'=>$data['links'],
            ],200);
        }catch(\Exception $e){
            return response()->json([
                'status'=>'error',
                'message'=>'Failed to fetch products',
                'message'=>$e->getMessage(),
            ],500);
        }

//        $products = Product::all();
//
//        $result = [];
//        foreach ($products as $product) {
//            $result[] = [
//                'id'       => $product->id,
//                'name'     => $product->name,
//                'price'    => $product->price,
//                'stock'    => $product->stock,
//                'category' => $product->category->name,
//            ];
//        }
//
//        return response()->json($result);
    }

    public function salesReport():JsonResponse
    {
        try{
            $orders=Order::with(['customer','items.product'])->latest()->paginate(15);

            return response()->json([
                'status'=>'success',
                'message'=>'Sales report List',
                'data'=>SalesReportResource::collection($orders),
                'meta'=>[
                    'current_page'=>$orders->currentPage(),
                    'last_page'=>$orders->lastPage(),
                    'total'=>$orders->total(),
                ]
            ],200);
        }catch (\Exception $e){
            return response()->json([
                'status'=>'error',
                'message'=>'Failed to fetch sales report',
                'message'=>$e->getMessage(),
            ]);
        }

//        $orders = Order::all();
//
//        $report = [];
//        foreach ($orders as $order) {
//            foreach ($order->items as $item) {
//                $report[] = [
//                    'order_id'     => $order->id,
//                    'product_name' => $item->product->name,
//                    'qty'          => $item->quantity,
//                    'total'        => $item->quantity * $item->product->price,
//                    'customer'     => $order->customer->name,
//                ];
//            }
//        }
//
//        return response()->json($report);
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
