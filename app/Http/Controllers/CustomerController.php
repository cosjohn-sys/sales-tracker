<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CustomerController extends Controller
{
    public function index(): JsonResponse
    {
        $itemTotals = DB::table('sale_items')
            ->select('sale_id', DB::raw('SUM(quantity) as items_purchased'))
            ->groupBy('sale_id');

        $customers = DB::table('customers')
            ->leftJoin('sales', 'sales.customer_id', '=', 'customers.id')
            ->leftJoinSub($itemTotals, 'item_totals', function ($join): void {
                $join->on('item_totals.sale_id', '=', 'sales.id');
            })
            ->select(
                'customers.id',
                'customers.name',
                'customers.customer_type',
                DB::raw('COUNT(sales.id) as transactions'),
                DB::raw('COALESCE(SUM(item_totals.items_purchased), 0) as items_purchased'),
                DB::raw('COALESCE(SUM(sales.total_amount), 0) as total_amount'),
                DB::raw('MAX(sales.sale_date) as last_purchase_date')
            )
            ->groupBy('customers.id', 'customers.name', 'customers.customer_type')
            ->orderBy('customers.name')
            ->get();

        return new JsonResponse(['data' => $customers]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255', 'unique:customers,name'],
            'customer_type' => ['required', 'in:Regular Customer,Walk-in Customer'],
        ]);

        if ($validator->fails()) {
            return new JsonResponse(['message' => 'Validation failed.', 'errors' => $validator->errors()], 422);
        }

        $timestamp = Carbon::now();
        $id = DB::table('customers')->insertGetId([
            'name' => $request->input('name'),
            'customer_type' => $request->input('customer_type'),
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        $customer = DB::table('customers')->where('id', $id)->first();

        return new JsonResponse(['message' => 'Customer saved.', 'data' => $customer], 201);
    }

    public function show(int $id): JsonResponse
    {
        $customer = DB::table('customers')->where('id', $id)->first();

        if ($customer === null) {
            return new JsonResponse(['message' => 'Customer not found.'], 404);
        }

        $history = DB::table('sales')
            ->where('sales.customer_id', $id)
            ->leftJoin('sale_items', 'sale_items.sale_id', '=', 'sales.id')
            ->leftJoin('products', 'products.id', '=', 'sale_items.product_id')
            ->select(
                'sales.id',
                'sales.transaction_number',
                'sales.sale_date',
                'sales.total_amount',
                DB::raw("GROUP_CONCAT(CONCAT(products.name, ' x', sale_items.quantity) SEPARATOR ', ') as items")
            )
            ->groupBy('sales.id', 'sales.transaction_number', 'sales.sale_date', 'sales.total_amount')
            ->orderByDesc('sales.sale_date')
            ->get();

        return new JsonResponse(['data' => ['customer' => $customer, 'history' => $history]]);
    }
}
