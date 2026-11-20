<?php

namespace Database\Factories;

use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition()
    {
        return [
            'amount' => 100,
            'tax_price' => 0,
            'commission_price' => 0,
            'discount' => 0,
            'total_amount' => 100,
            'quantity' => 1,
            'created_at' => time(),
        ];
    }
}