<?php

namespace Database\Seeders;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $timestamp = Carbon::now();
        $adminEmail = env('APP_ADMIN_EMAIL', 'admin@example.com');

        if (! DB::table('users')->where('email', $adminEmail)->exists()) {
            DB::table('users')->insert([
                'name' => env('APP_ADMIN_NAME', 'Store Admin'),
                'email' => $adminEmail,
                'email_verified_at' => $timestamp,
                'password' => Hash::make(env('APP_ADMIN_PASSWORD', 'password')),
                'remember_token' => null,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        }

        DB::table('products')->upsert([
            [
                'name' => 'Ice',
                'selling_price' => 10,
                'current_stock' => 100,
                'minimum_stock' => 20,
                'status' => 'Active',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'name' => 'Ice Water',
                'selling_price' => 5,
                'current_stock' => 50,
                'minimum_stock' => 15,
                'status' => 'Active',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'name' => 'Ice Candy',
                'selling_price' => 5,
                'current_stock' => 80,
                'minimum_stock' => 20,
                'status' => 'Active',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
        ], ['name'], ['selling_price', 'current_stock', 'minimum_stock', 'status', 'updated_at']);
    }
}
