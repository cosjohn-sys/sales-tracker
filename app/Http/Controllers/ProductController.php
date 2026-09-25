<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{
    public function index(): JsonResponse
    {
        $products = DB::table('products')
            ->orderBy('name')
            ->get();

        return new JsonResponse(['data' => $products]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255', 'unique:products,name'],
            'selling_price' => ['required', 'numeric', 'min:0'],
            'current_stock' => ['required', 'integer', 'min:0'],
            'minimum_stock' => ['required', 'integer', 'min:0'],
            'status' => ['required', 'in:Active,Inactive'],
        ]);

        if ($validator->fails()) {
            return new JsonResponse(['message' => 'Validation failed.', 'errors' => $validator->errors()], 422);
        }

        $timestamp = Carbon::now();
        $id = DB::table('products')->insertGetId([
            'name' => $request->input('name'),
            'selling_price' => $request->input('selling_price'),
            'current_stock' => $request->input('current_stock'),
            'minimum_stock' => $request->input('minimum_stock'),
            'status' => $request->input('status'),
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        $product = DB::table('products')->where('id', $id)->first();

        return new JsonResponse(['message' => 'Product saved.', 'data' => $product], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255', 'unique:products,name,' . $id],
            'selling_price' => ['required', 'numeric', 'min:0'],
            'current_stock' => ['required', 'integer', 'min:0'],
            'minimum_stock' => ['required', 'integer', 'min:0'],
            'status' => ['required', 'in:Active,Inactive'],
        ]);

        if ($validator->fails()) {
            return new JsonResponse(['message' => 'Validation failed.', 'errors' => $validator->errors()], 422);
        }

        $updated = DB::table('products')
            ->where('id', $id)
            ->update([
                'name' => $request->input('name'),
                'selling_price' => $request->input('selling_price'),
                'current_stock' => $request->input('current_stock'),
                'minimum_stock' => $request->input('minimum_stock'),
                'status' => $request->input('status'),
                'updated_at' => Carbon::now(),
            ]);

        if ($updated === 0) {
            return new JsonResponse(['message' => 'Product not found.'], 404);
        }

        $product = DB::table('products')->where('id', $id)->first();

        return new JsonResponse(['message' => 'Product updated.', 'data' => $product]);
    }
}
