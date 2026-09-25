<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function customerReport(): JsonResponse
    {
        $itemTotals = DB::table('sale_items')
            ->select('sale_id', DB::raw('SUM(quantity) as items_purchased'))
            ->groupBy('sale_id');

        $namedCustomers = DB::table('sales')
            ->join('customers', 'customers.id', '=', 'sales.customer_id')
            ->leftJoinSub($itemTotals, 'item_totals', function ($join): void {
                $join->on('item_totals.sale_id', '=', 'sales.id');
            })
            ->whereNotNull('sales.customer_id')
            ->select(
                'customers.name as customer_name',
                DB::raw('COUNT(sales.id) as transactions'),
                DB::raw('COALESCE(SUM(item_totals.items_purchased), 0) as items_purchased'),
                DB::raw('SUM(sales.total_amount) as total_amount'),
                DB::raw('MAX(sales.sale_date) as last_purchase_date')
            )
            ->groupBy('sales.customer_id', 'customers.name');

        $walkIn = DB::table('sales')
            ->leftJoinSub($itemTotals, 'item_totals', function ($join): void {
                $join->on('item_totals.sale_id', '=', 'sales.id');
            })
            ->whereNull('sales.customer_id')
            ->select(
                DB::raw("'Walk-in' as customer_name"),
                DB::raw('COUNT(sales.id) as transactions'),
                DB::raw('COALESCE(SUM(item_totals.items_purchased), 0) as items_purchased'),
                DB::raw('COALESCE(SUM(sales.total_amount), 0) as total_amount'),
                DB::raw('MAX(sales.sale_date) as last_purchase_date')
            );

        $report = DB::query()
            ->fromSub($namedCustomers->unionAll($walkIn), 'customer_sales')
            ->orderByDesc('total_amount')
            ->get();

        return new JsonResponse(['data' => $report]);
    }

    public function productReport(): JsonResponse
    {
        $report = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->select(
                'products.id',
                'products.name',
                DB::raw("COUNT(DISTINCT CASE WHEN sales.customer_id IS NOT NULL THEN CONCAT('C', sales.customer_id) ELSE CONCAT('W', sales.id) END) as customers_who_bought"),
                DB::raw('SUM(sale_items.quantity) as quantity_sold'),
                DB::raw('SUM(sale_items.total) as total_sales')
            )
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('quantity_sold')
            ->get();

        return new JsonResponse(['data' => $report]);
    }

    public function trends(): JsonResponse
    {
        $today = Carbon::today();
        $yesterday = $today->copy()->subDay();
        $startOfMonth = $today->copy()->startOfMonth();
        $endOfMonth = $today->copy()->endOfMonth();
        $monthCustomers = $this->countUniqueCustomers($startOfMonth, $endOfMonth);
        $monthSales = (float) DB::table('sales')
            ->whereBetween('sale_date', [$startOfMonth->toDateTimeString(), $endOfMonth->toDateTimeString()])
            ->sum('total_amount');
        $daysElapsed = max(1, $today->day);

        return new JsonResponse([
            'data' => [
                'customers_today' => $this->countUniqueCustomers($today->copy()->startOfDay(), $today->copy()->endOfDay()),
                'customers_yesterday' => $this->countUniqueCustomers($yesterday->copy()->startOfDay(), $yesterday->copy()->endOfDay()),
                'customers_this_week' => $this->countUniqueCustomers($today->copy()->startOfWeek(), $today->copy()->endOfWeek()),
                'customers_this_month' => $monthCustomers,
                'average_customers_per_day' => round($monthCustomers / $daysElapsed, 2),
                'average_spent_per_customer' => $monthCustomers > 0 ? round($monthSales / $monthCustomers, 2) : 0,
                'total_sales_this_month' => $monthSales,
            ],
        ]);
    }

    public function inventoryReport(Request $request): JsonResponse
    {
        $query = DB::table('inventory')
            ->join('products', 'products.id', '=', 'inventory.product_id')
            ->select('inventory.*', 'products.name as product_name')
            ->orderByDesc('inventory.inventory_date')
            ->orderBy('products.name');

        if ($request->filled('date_from')) {
            $query->whereDate('inventory.inventory_date', '>=', $request->query('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('inventory.inventory_date', '<=', $request->query('date_to'));
        }

        return new JsonResponse(['data' => $query->limit(250)->get()]);
    }

    private function countUniqueCustomers(Carbon $start, Carbon $end): int
    {
        $named = DB::table('sales')
            ->whereNotNull('customer_id')
            ->whereBetween('sale_date', [$start->toDateTimeString(), $end->toDateTimeString()])
            ->distinct()
            ->count('customer_id');

        $walkIn = DB::table('sales')
            ->whereNull('customer_id')
            ->whereBetween('sale_date', [$start->toDateTimeString(), $end->toDateTimeString()])
            ->count();

        return $named + $walkIn;
    }
}
