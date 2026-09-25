<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function today(): JsonResponse
    {
        $today = Carbon::today();
        $todayDate = $today->toDateString();
        $startOfWeek = $today->copy()->startOfWeek()->toDateTimeString();
        $endOfWeek = $today->copy()->endOfWeek()->toDateTimeString();
        $startOfMonth = $today->copy()->startOfMonth()->toDateTimeString();
        $endOfMonth = $today->copy()->endOfMonth()->toDateTimeString();

        $totalSales = (float) DB::table('sales')->whereDate('sale_date', $todayDate)->sum('total_amount');
        $transactions = DB::table('sales')->whereDate('sale_date', $todayDate)->count();
        $itemsSold = (int) DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->whereDate('sales.sale_date', $todayDate)
            ->sum('sale_items.quantity');
        $customersToday = $this->countUniqueCustomers($today->copy()->startOfDay(), $today->copy()->endOfDay());
        $customersWeek = $this->countUniqueCustomers(Carbon::parse($startOfWeek), Carbon::parse($endOfWeek));
        $customersMonth = $this->countUniqueCustomers(Carbon::parse($startOfMonth), Carbon::parse($endOfMonth));
        $totalCustomers = $this->countUniqueCustomers(null, null);
        $paymentTotals = $this->paymentTotals($todayDate);
        $currentStock = DB::table('products')->orderBy('name')->get();
        $lowStock = DB::table('products')
            ->whereColumn('current_stock', '<=', 'minimum_stock')
            ->where('status', 'Active')
            ->orderBy('name')
            ->get();

        return new JsonResponse([
            'data' => [
                'today_sales' => $totalSales,
                'customers_today' => $customersToday,
                'customers_this_week' => $customersWeek,
                'customers_this_month' => $customersMonth,
                'total_customers' => $totalCustomers,
                'transactions_today' => $transactions,
                'items_sold_today' => $itemsSold,
                'average_sale' => $customersToday > 0 ? round($totalSales / $customersToday, 2) : 0,
                'payment_totals' => $paymentTotals,
                'current_stock' => $currentStock,
                'low_stock' => $lowStock,
            ],
        ]);
    }

    private function countUniqueCustomers(?Carbon $start, ?Carbon $end): int
    {
        $namedQuery = DB::table('sales')->whereNotNull('customer_id');
        $walkInQuery = DB::table('sales')->whereNull('customer_id');

        if ($start !== null && $end !== null) {
            $namedQuery->whereBetween('sale_date', [$start->toDateTimeString(), $end->toDateTimeString()]);
            $walkInQuery->whereBetween('sale_date', [$start->toDateTimeString(), $end->toDateTimeString()]);
        }

        return $namedQuery->distinct()->count('customer_id') + $walkInQuery->count();
    }

    private function paymentTotals(string $todayDate): array
    {
        $totals = [
            'Cash' => 0,
            'GCash' => 0,
            'Credit' => 0,
        ];

        $rows = DB::table('payments')
            ->join('sales', 'sales.id', '=', 'payments.sale_id')
            ->whereDate('sales.sale_date', $todayDate)
            ->select('payments.payment_method', DB::raw('SUM(payments.total_amount) as total'))
            ->groupBy('payments.payment_method')
            ->get();

        foreach ($rows as $row) {
            $totals[$row->payment_method] = (float) $row->total;
        }

        return $totals;
    }
}
