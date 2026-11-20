<?php

namespace Database\Factories;

use App\Models\Product;
use App\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition()
    {
        return [
            'creator_id' => User::factory(),
            'type' => Product::$physical,
            'status' => Product::$active,
            'commission_type' => 'percent',
            'price' => $this->faker->randomFloat(2, 10, 200),
            'inventory' => 100,
            'unlimited_inventory' => false,
            'erp_post_sale_enabled' => false,
            'erp_staff_ids' => [],
            'erp_task_templates' => [],
        ];
    }

    public function create($attributes = [], ?Model $parent = null)
    {
        $attributes = array_merge($this->definition(), $attributes);

        // Nested factory objects (jaise creator_id => User::factory()) ko real ID mein resolve karein
        foreach ($attributes as $key => $value) {
            if ($value instanceof Factory) {
                $attributes[$key] = $value->create()->getKey();
            }
        }

        $title = $attributes['title'] ?? ('Product ' . Str::random(6));
        $locale = $attributes['locale'] ?? config('translatable.locale', 'en');
        unset($attributes['title'], $attributes['locale']);

        if (empty($attributes['slug'])) {
            $attributes['slug'] = Str::slug($title) . '-' . Str::random(5);
        }

        $attributes['created_at'] = $attributes['created_at'] ?? time();
        $attributes['updated_at'] = $attributes['updated_at'] ?? time();

        /** @var Product $product */
        $product = Product::create($attributes);

        $product->translateOrNew($locale)->title = $title;
        $product->save();

        return $product->fresh();
    }
}