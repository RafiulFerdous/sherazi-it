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

### 2. Missing Caching (Plus Invalidation)

- `app/Http/Controllers/ProductController.php` `dashboard()`: computed stats hit DB every request.
- `app/Http/Controllers/ProductController.php` `index()`: list endpoint has no cache layer.
- Cache must invalidate when:
  - products change: `ProductController::store()` (and any update/delete endpoints you add)
  - orders change: `OrderController::store()` (affects sales report/dashboard totals)

### 3. No Pagination (Return-All Endpoints)

These endpoints currently load everything via `::all()` / `->get()` and return unbounded responses:

- `GET /api/products` (`ProductController@index`)
- `GET /api/orders` (`OrderController@index`)
- `GET /api/products/sales-report` (`ProductController@salesReport`)

Target: paginate at 15 per page.

### 4. Database Indexing

The task expects indexes for commonly searched/filtered/sorted columns. Check these migrations:

- `database/migrations/2024_01_01_000002_create_products_table.php`
- `database/migrations/2024_01_01_000004_create_orders_table.php`

Example of what is currently missing for products (no index on `name` / `sold_count`):

```php
$table->string('name');
$table->integer('sold_count')->default(0);
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

### 6. SQL Injection Risk

`app/Http/Controllers/OrderController.php` `filterByStatus()` uses raw string interpolation:

```php
$status = $request->input('status');
$orders = DB::select("SELECT * FROM orders WHERE status = '$status'");
```

Target: use query bindings or Eloquent (no raw interpolation).

### 7. Inefficient Counting & Aggregation

`app/Http/Controllers/ProductController.php` `dashboard()` loads entire tables into memory just to count/sum/sort:

```php
$totalProducts = Product::all()->count();
$totalOrders   = Order::all()->count();
$totalRevenue  = Order::all()->sum('total_amount');
$topProducts   = Product::all()->sortByDesc('sold_count')->take(5)->values();
```

Target: use database aggregates (`Product::count()`, `Order::sum(...)`, `orderByDesc(...)->limit(...)`, etc.).
