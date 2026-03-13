# 🧪 Sherazi IT — Senior Laravel Developer Interview Task

## ⏱ Time Limit: 2 Hours

---

## 📌 Task Overview

This Laravel project is a simplified **POS (Point of Sale) backend** for Sherazi IT.

The codebase has been **intentionally written with performance problems**.
Your job is to **identify all issues**, **fix them**, and **explain your decisions**.

---

## ⚙️ Setup Instructions

```bash
# 1. Clone / extract the project
cd sherazi-pos-task

# 2. Install dependencies
composer install

# 3. Copy environment file
cp .env.example .env

# 4. Generate app key
php artisan key:generate

# 5. Configure your DB in .env
DB_DATABASE=sherazi_pos
DB_USERNAME=root
DB_PASSWORD=

# 6. Run migrations & seed (seeds 500 products, 200 orders — intentionally large)
php artisan migrate --seed

# 7. Make sure Redis is running
# Update .env: CACHE_DRIVER=redis, QUEUE_CONNECTION=redis

# 8. Start server
php artisan serve
```

---

## ✅ What You Must Fix

### 1. N+1 Query Problems
- `GET /api/products` — category loaded per product in a loop
- `GET /api/orders` — customer & items loaded per order in a loop
- `GET /api/products/sales-report` — nested N+1 (order → items → product)

### 2. Missing Caching
- `GET /api/products/dashboard` hits DB every request
- `GET /api/products` no cache layer
- Cache must **invalidate** when data changes

### 3. No Pagination
- `/api/products`, `/api/orders`, `/api/products/sales-report` return ALL records
- Add proper pagination (15 per page)

### 4. Database Indexing
- `products.name` — missing index (used in LIKE search)
- `orders.status` — missing index (used in WHERE filter)
- `products.sold_count` — missing index (used in ORDER BY)

### 5. No DB Transaction in Order Creation
- `POST /api/orders` — if one item fails, partial data is saved
- Wrap in `DB::transaction()`

### 6. SQL Injection Risk
- `GET /api/orders/filter?status=` — raw query with direct variable
- Fix using query bindings or Eloquent

### 7. Inefficient Counting & Aggregation
- `Product::all()->count()` — loads all rows into memory just to count
- Use `Product::count()` and `DB::` aggregates instead

---

## 📦 Deliverables

1. **GitHub repo** with clean, meaningful commits (not one giant commit)
2. **Before vs After** screenshot — show query count & response time using Laravel Debugbar or Telescope
3. **Short README section** — explain what you fixed and why

---

## 🎁 Bonus (If Time Allows)

- Add **Laravel Sanctum** authentication to protect routes
- Add **Redis-based session handling**
- Setup **Laravel Horizon** for queue monitoring
- Add **API rate limiting** per user

---

## 🎤 After Submission

You will have a **15-minute live Q&A** where you will be asked:
- Why did you choose this approach over alternatives?
- How would this scale to 100k+ products?
- What would you do differently if you had more time?

> ⚠️ **Screen recording (face + screen) of your full work session is mandatory.**
> Edited or paused recordings will not be accepted.

---

Good luck! 💪
— Sherazi IT Team
