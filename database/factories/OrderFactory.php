<?php

namespace Database\Factories;

use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition()
    {
        return [
            'user_id' => \App\User::factory(),
            'status' => Order::$paid,
            'amount' => 100,
            'total_amount' => 100,
            'created_at' => time(),
        ];
    }
}