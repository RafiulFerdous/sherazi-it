<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesReportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'order_id' => $this->id,

            'customer' => $this->customer->name,

            'items' => $this->items->map(function ($item) {
                return [
                    'product_name' => $item->product->name,
                    'qty' => $item->quantity,
                    'total' => $item->quantity * $item->product->price,
                ];
            })
        ];
    }
}
