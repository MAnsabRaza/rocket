<?php

namespace Database\Factories;

use App\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected $model = User::class;

    protected function resolveRoleId(): int
    {
        $roleId = DB::table('roles')->value('id');

        if (!empty($roleId)) {
            return $roleId;
        }

        return DB::table('roles')->insertGetId([
            'name' => 'user',
            'users_count' => 0,
            'is_admin' => 0,
            'created_at' => time(),
        ]);
    }

    public function definition()
    {
        return [
            'full_name' => $this->faker->name,
            'username' => $this->faker->unique()->userName,
            'role_name' => 'user',
            'role_id' => $this->resolveRoleId(),
            'email' => $this->faker->unique()->safeEmail,
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // password
            'remember_token' => Str::random(10),
            'created_at' => time(),
        ];
    }
}