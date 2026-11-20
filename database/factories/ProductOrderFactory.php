<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductOrder;
use App\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductOrderFactory extends Factory
{
    protected $model = ProductOrder::class;

    public function definition()
    {
        return [
            'product_id' => Product::factory(),
            'seller_id' => User::factory(),
            'quantity' => 1,
            'status' => 'pending',
            'created_at' => time(),
        ];
    }
}