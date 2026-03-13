# Sherazi POS Interview Task (Laravel)

This is a simplified POS (Point of Sale) backend used as an interview task. The code is intentionally written with performance and correctness issues to identify, fix, and explain.

If you want the original task brief, see `README_TASK.md`.

## Project Layout

- Runnable Laravel app: this folder (`sherazi-pos-task/`)
- Original extracted task code (not runnable by itself): `../sherazi-pos-task-clean/` (contains only `app/`, `routes/`, `database/`)

## Setup (MySQL)

1. Install PHP dependencies:

```bash
composer install
```

2. Configure environment:

```bash
cp .env.example .env
php artisan key:generate
```

3. Create database and a dedicated user (recommended). Root without a password often fails with:
`SQLSTATE[HY000] [1045] Access denied for user 'root'@'localhost' (using password: NO)`.

```sql
CREATE DATABASE IF NOT EXISTS sherazi_pos;
CREATE USER 'sherazi'@'%' IDENTIFIED BY 'sherazi_pass';
GRANT ALL PRIVILEGES ON sherazi_pos.* TO 'sherazi'@'%';
FLUSH PRIVILEGES;
```

Then update `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sherazi_pos
DB_USERNAME=root
DB_PASSWORD=
```

4. Run migrations and seed data:

```bash
php artisan migrate --seed
```

5. Start the server:

```bash
php artisan serve
```

Notes:

- The default Laravel skeleton in this repo uses `SESSION_DRIVER=database`, so DB connectivity and migrations are required even for basic browsing.
- API routes are wired in `bootstrap/app.php` via `->withRouting(api: ...)`.

## API Endpoints

- `GET /api/products`
- `POST /api/products`
- `GET /api/products/search?q=...`
- `GET /api/products/dashboard`
- `GET /api/products/sales-report`
- `GET /api/orders`
- `POST /api/orders`
- `GET /api/orders/filter?status=...`

## What To Fix (Specific)

The issues below reference the current code in this repo (controllers/migrations copied from the task).

### 1. N+1 Query Problems

- `app/Http/Controllers/ProductController.php` `index()`: `Product::all()` then per-item relation access (`$product->category`) triggers one query per product.

```php
$products = Product::all();
foreach ($products as $product) {
    $result[] = [
        'category' => $product->category->name,
    ];
}
```

Solution (eager load + paginate):

```php
public function index(Request $request)
{
    $products = Product::query()
        ->with('category')
        ->paginate(15);

    return response()->json($products);
}
```

- `app/Http/Controllers/OrderController.php` `index()`: `Order::all()` then per-order relation access triggers extra queries.

```php
$orders = Order::all();
foreach ($orders as $order) {
    $data[] = [
        'customer'    => $order->customer->name,
        'items_count' => $order->items->count(),
    ];
}
```

Solution (eager load + `withCount` + paginate):

```php
public function index()
{
    $orders = Order::query()
        ->with('customer')
        ->withCount('items')
        ->paginate(15);

    return response()->json($orders);
}
```

- `app/Http/Controllers/ProductController.php` `salesReport()`: nested N+1 across `orders -> items -> product` and `order -> customer`.

```php
$orders = Order::all();
foreach ($orders as $order) {
    foreach ($order->items as $item) {
        $item->product->name;
        $order->customer->name;
    }
}
```

Solution (paginate the report rows, eager load through relationships):

```php
public function salesReport()
{
    $rows = OrderItem::query()
        ->with(['product', 'order.customer'])
        ->paginate(15);

    $report = $rows->getCollection()->map(function (OrderItem $item) {
        return [
            'order_id'     => $item->order_id,
            'product_name' => $item->product->name,
            'qty'          => $item->quantity,
            'total'        => $item->quantity * $item->product->price,
            'customer'     => $item->order->customer->name,
        ];
    });

    return response()->json([
        'data' => $report,
        'meta' => [
            'current_page' => $rows->currentPage(),
            'per_page'     => $rows->perPage(),
            'total'        => $rows->total(),
            'last_page'    => $rows->lastPage(),
        ],
    ]);
}
```

### 2. Missing Caching (Plus Invalidation)

- `app/Http/Controllers/ProductController.php` `dashboard()`: computed stats hit DB every request.
- `app/Http/Controllers/ProductController.php` `index()`: list endpoint has no cache layer.
- Cache must invalidate when:
  - products change: `ProductController::store()` (and any update/delete endpoints you add)
  - orders change: `OrderController::store()` (affects sales report/dashboard totals)

Solution (Redis cache tags recommended):

- Set `.env` to use Redis: `CACHE_STORE=redis`
- Cache reads with `Cache::tags(...)->remember(...)`
- Invalidate with `Cache::tags(...)->flush()` on writes

Example for `ProductController@index`:

```php
use Illuminate\Support\Facades\Cache;

$page = (int) request('page', 1);

$products = Cache::tags(['products'])
    ->remember("products:index:page:$page", 60, function () {
        return Product::query()->with('category')->paginate(15);
    });
```

Example invalidation after creating a product:

```php
Cache::tags(['products', 'dashboard', 'sales-report'])->flush();
```

### 3. No Pagination (Return-All Endpoints)

These endpoints currently load everything via `::all()` / `->get()` and return unbounded responses:

- `GET /api/products` (`ProductController@index`)
- `GET /api/orders` (`OrderController@index`)
- `GET /api/products/sales-report` (`ProductController@salesReport`)

Target: paginate at 15 per page.

Solution examples:

```php
Product::query()->with('category')->paginate(15);
Order::query()->with('customer')->withCount('items')->paginate(15);
OrderItem::query()->with(['product', 'order.customer'])->paginate(15);
```

### 4. Database Indexing

The task expects indexes for commonly searched/filtered/sorted columns. Check these migrations:

- `database/migrations/2024_01_01_000002_create_products_table.php`
- `database/migrations/2024_01_01_000004_create_orders_table.php`

Example of what is currently missing for products (no index on `name` / `sold_count`):

```php
$table->string('name');
$table->integer('sold_count')->default(0);
```

Solution (add indexes in migrations):

```php
$table->string('name')->index();
$table->integer('sold_count')->default(0)->index();
```

For order status in `create_orders_table`:

```php
$table->string('status')->index();
```

### 5. No DB Transaction In Order Creation

`app/Http/Controllers/OrderController.php` `store()` creates an order, then items, then decrements stock. If one item fails, partial data can be persisted:

```php
$order = Order::create([...]);
foreach ($request->items as $item) {
    // early return here can leave a partially-created order
    if (!$product || $product->stock < $item['quantity']) {
        return response()->json(['error' => 'Product unavailable'], 422);
    }
    OrderItem::create([...]);
    $product->decrement('stock', $item['quantity']);
}
```

Target: wrap the whole operation in `DB::transaction(...)` and fail atomically.

Solution (outline):

```php
return DB::transaction(function () use ($request) {
    $order = Order::create([
        'customer_id'  => $request->customer_id,
        'total_amount' => 0,
        'status'       => 'pending',
    ]);

    $totalAmount = 0;

    foreach ($request->items as $item) {
        $product = Product::query()->lockForUpdate()->find($item['product_id']);
        if (!$product || $product->stock < $item['quantity']) {
            abort(422, 'Product unavailable');
        }

        OrderItem::create([
            'order_id'   => $order->id,
            'product_id' => $product->id,
            'quantity'   => $item['quantity'],
            'unit_price' => $product->price,
        ]);

        $product->decrement('stock', $item['quantity']);
        $totalAmount += $product->price * $item['quantity'];
    }

    $order->update(['total_amount' => $totalAmount]);

    return response()->json($order, 201);
});
```

### 6. SQL Injection Risk

`app/Http/Controllers/OrderController.php` `filterByStatus()` uses raw string interpolation:

```php
$status = $request->input('status');
$orders = DB::select("SELECT * FROM orders WHERE status = '$status'");
```

Target: use query bindings or Eloquent (no raw interpolation).

Solution (Eloquent + optional pagination):

```php
$status = $request->input('status');
$orders = Order::query()->where('status', $status)->paginate(15);
return response()->json($orders);
```

### 7. Inefficient Counting & Aggregation

`app/Http/Controllers/ProductController.php` `dashboard()` loads entire tables into memory just to count/sum/sort:

```php
$totalProducts = Product::all()->count();
$totalOrders   = Order::all()->count();
$totalRevenue  = Order::all()->sum('total_amount');
$topProducts   = Product::all()->sortByDesc('sold_count')->take(5)->values();
```

Target: use database aggregates (`Product::count()`, `Order::sum(...)`, `orderByDesc(...)->limit(...)`, etc.).

Solution:

```php
$totalProducts = Product::count();
$totalOrders   = Order::count();
$totalRevenue  = Order::sum('total_amount');
$topProducts   = Product::query()->orderByDesc('sold_count')->limit(5)->get();
```
