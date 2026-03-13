<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Seed Categories
        $categories = [];
        $categoryNames = ['Electronics', 'Groceries', 'Beverages', 'Dairy', 'Snacks', 'Stationery'];
        foreach ($categoryNames as $name) {
            $categories[] = Category::create(['name' => $name]);
        }

        // Seed 500 Products (large data to expose N+1 clearly)
        $products = [];
        for ($i = 1; $i <= 500; $i++) {
            $products[] = Product::create([
                'name'        => "Product #$i",
                'description' => "This is the description for product number $i",
                'price'       => rand(10, 500) + 0.99,
                'stock'       => rand(0, 200),
                'sold_count'  => rand(0, 1000),
                'category_id' => $categories[array_rand($categories)]->id,
            ]);
        }

        // Seed 100 Customers
        $customers = [];
        for ($i = 1; $i <= 100; $i++) {
            $customers[] = Customer::create([
                'name'  => "Customer $i",
                'email' => "customer{$i}@example.com",
                'phone' => '017' . rand(10000000, 99999999),
            ]);
        }

        // Seed 200 Orders with items (to make N+1 very visible)
        for ($i = 0; $i < 200; $i++) {
            $order = Order::create([
                'customer_id'  => $customers[array_rand($customers)]->id,
                'total_amount' => 0,
                'status'       => ['pending', 'completed', 'cancelled'][rand(0, 2)],
            ]);

            $total = 0;
            $itemCount = rand(1, 5);
            for ($j = 0; $j < $itemCount; $j++) {
                $product = $products[array_rand($products)];
                $qty     = rand(1, 10);
                OrderItem::create([
                    'order_id'   => $order->id,
                    'product_id' => $product->id,
                    'quantity'   => $qty,
                    'unit_price' => $product->price,
                ]);
                $total += $qty * $product->price;
            }

            $order->update(['total_amount' => $total]);
        }
    }
}
