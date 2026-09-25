<?php

namespace App\Http\Controllers;

use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use Throwable;

class SaleController extends Controller
{
    public function __construct(private readonly InventoryService $inventoryService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $query = DB::table('sales')
            ->join('sale_items', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->join('payments', 'payments.sale_id', '=', 'sales.id')
            ->select(
                'sales.id',
                'sales.transaction_number',
                'sales.sale_date',
                'sales.customer_id',
                'sales.customer_name',
                'sales.customer_type',
                'sales.walk_in_number',
                'sales.total_amount as sale_total',
                'products.id as product_id',
                'products.name as product_name',
                'sale_items.quantity',
                'sale_items.price',
                'sale_items.total',
                'payments.payment_method'
            )
            ->orderByDesc('sales.sale_date')
            ->orderByDesc('sales.id');

        $this->applyFilters($query, $request);

        return new JsonResponse(['data' => $query->limit(250)->get()]);
    }

    public function show(int $id): JsonResponse
    {
        $sale = DB::table('sales')
            ->leftJoin('payments', 'payments.sale_id', '=', 'sales.id')
            ->where('sales.id', $id)
            ->select(
                'sales.*',
                'payments.payment_method',
                'payments.amount_paid',
                'payments.change_amount'
            )
            ->first();

        if ($sale === null) {
            return new JsonResponse(['message' => 'Sale not found.'], 404);
        }

        $items = DB::table('sale_items')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->where('sale_items.sale_id', $id)
            ->select('sale_items.*', 'products.name as product_name')
            ->get();

        return new JsonResponse(['data' => ['sale' => $sale, 'items' => $items]]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_type' => ['required', 'in:Regular Customer,Walk-in Customer'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'payment_method' => ['required', 'in:Cash,GCash,Credit'],
            'amount_paid' => ['nullable', 'numeric', 'min:0'],
        ]);

        if ($validator->fails()) {
            return new JsonResponse(['message' => 'Validation failed.', 'errors' => $validator->errors()], 422);
        }

        try {
            $sale = DB::transaction(function () use ($request): array {
                $timestamp = Carbon::now();
                $saleDate = $timestamp->toDateTimeString();
                $inventoryDate = $timestamp->toDateString();
                $customerData = $this->resolveCustomer(
                    $request->input('customer_id'),
                    $request->input('customer_name'),
                    $request->input('customer_type'),
                    $timestamp
                );
                $items = $this->prepareSaleItems($request->input('items'), $timestamp, $inventoryDate);
                $totalAmount = array_sum(array_column($items, 'total'));
                $payment = $this->preparePayment($request->input('payment_method'), $request->input('amount_paid'), $totalAmount);
                $transactionNumber = $this->makeTransactionNumber($timestamp);
                $walkInNumber = $customerData['customer_id'] === null ? $this->makeWalkInNumber($timestamp) : null;

                $saleId = DB::table('sales')->insertGetId([
                    'transaction_number' => $transactionNumber,
                    'customer_id' => $customerData['customer_id'],
                    'customer_name' => $customerData['customer_name'],
                    'customer_type' => $customerData['customer_type'],
                    'walk_in_number' => $walkInNumber,
                    'sale_date' => $saleDate,
                    'subtotal' => $totalAmount,
                    'total_amount' => $totalAmount,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);

                foreach ($items as $item) {
                    DB::table('sale_items')->insert([
                        'sale_id' => $saleId,
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'price' => $item['price'],
                        'total' => $item['total'],
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ]);
                }

                DB::table('payments')->insert([
                    'sale_id' => $saleId,
                    'payment_method' => $payment['payment_method'],
                    'total_amount' => $totalAmount,
                    'amount_paid' => $payment['amount_paid'],
                    'change_amount' => $payment['change_amount'],
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);

                return [
                    'id' => $saleId,
                    'transaction_number' => $transactionNumber,
                    'customer_name' => $customerData['customer_name'] ?? $walkInNumber,
                    'customer_type' => $customerData['customer_type'],
                    'total_amount' => $totalAmount,
                    'payment' => $payment,
                ];
            });
        } catch (InvalidArgumentException $exception) {
            return new JsonResponse(['message' => $exception->getMessage()], 422);
        } catch (Throwable $exception) {
            return new JsonResponse(['message' => 'Unable to save sale.', 'detail' => $exception->getMessage()], 500);
        }

        return new JsonResponse(['message' => 'Sale saved.', 'data' => $sale], 201);
    }

    private function applyFilters($query, Request $request): void
    {
        $period = $request->query('period');
        $today = Carbon::today();

        if ($period === 'today') {
            $query->whereDate('sales.sale_date', $today->toDateString());
        }

        if ($period === 'week') {
            $query->whereBetween('sales.sale_date', [
                $today->copy()->startOfWeek()->toDateTimeString(),
                $today->copy()->endOfWeek()->toDateTimeString(),
            ]);
        }

        if ($period === 'month') {
            $query->whereBetween('sales.sale_date', [
                $today->copy()->startOfMonth()->toDateTimeString(),
                $today->copy()->endOfMonth()->toDateTimeString(),
            ]);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('sales.sale_date', '>=', $request->query('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('sales.sale_date', '<=', $request->query('date_to'));
        }

        if ($request->filled('customer_id')) {
            $query->where('sales.customer_id', $request->query('customer_id'));
        }

        if ($request->filled('product_id')) {
            $query->where('sale_items.product_id', $request->query('product_id'));
        }
    }

    private function resolveCustomer(?int $customerId, ?string $customerName, string $customerType, Carbon $timestamp): array
    {
        $cleanName = trim((string) $customerName);

        if ($customerId !== null) {
            $customer = DB::table('customers')->where('id', $customerId)->first();

            if ($customer === null) {
                throw new InvalidArgumentException('Selected customer was not found.');
            }

            return [
                'customer_id' => (int) $customer->id,
                'customer_name' => $customer->name,
                'customer_type' => $customer->customer_type,
            ];
        }

        if ($customerType === 'Regular Customer' && $cleanName === '') {
            throw new InvalidArgumentException('Customer name is required for regular customers.');
        }

        if ($cleanName === '') {
            return [
                'customer_id' => null,
                'customer_name' => null,
                'customer_type' => 'Walk-in Customer',
            ];
        }

        $customer = DB::table('customers')
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($cleanName)])
            ->first();

        if ($customer !== null) {
            return [
                'customer_id' => (int) $customer->id,
                'customer_name' => $customer->name,
                'customer_type' => $customer->customer_type,
            ];
        }

        $newCustomerId = DB::table('customers')->insertGetId([
            'name' => $cleanName,
            'customer_type' => $customerType,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        return [
            'customer_id' => $newCustomerId,
            'customer_name' => $cleanName,
            'customer_type' => $customerType,
        ];
    }

    private function prepareSaleItems(array $requestItems, Carbon $timestamp, string $inventoryDate): array
    {
        $items = [];

        foreach ($requestItems as $requestItem) {
            $productId = (int) $requestItem['product_id'];
            $quantity = (int) $requestItem['quantity'];
            $product = DB::table('products')
                ->where('id', $productId)
                ->lockForUpdate()
                ->first();

            if ($product === null || $product->status !== 'Active') {
                throw new InvalidArgumentException('One selected product is not available.');
            }

            if ((int) $product->current_stock < $quantity) {
                throw new InvalidArgumentException($product->name . ' does not have enough stock.');
            }

            $currentStock = (int) $product->current_stock;
            $newStock = $currentStock - $quantity;
            $price = (float) $product->selling_price;
            $total = $quantity * $price;

            DB::table('products')
                ->where('id', $productId)
                ->update([
                    'current_stock' => $newStock,
                    'updated_at' => $timestamp,
                ]);

            $this->inventoryService->recordSale($productId, $inventoryDate, $currentStock, $quantity, $timestamp);

            $items[] = [
                'product_id' => $productId,
                'quantity' => $quantity,
                'price' => $price,
                'total' => $total,
            ];
        }

        return $items;
    }

    private function preparePayment(string $paymentMethod, mixed $amountPaid, float $totalAmount): array
    {
        $paid = $amountPaid === null ? 0 : (float) $amountPaid;

        if ($paymentMethod === 'GCash' && $paid === 0.0) {
            $paid = $totalAmount;
        }

        if ($paymentMethod !== 'Credit' && $paid < $totalAmount) {
            throw new InvalidArgumentException('Amount paid must cover the total unless payment is Credit.');
        }

        return [
            'payment_method' => $paymentMethod,
            'amount_paid' => $paid,
            'change_amount' => $paymentMethod === 'Cash' ? max(0, $paid - $totalAmount) : 0,
        ];
    }

    private function makeTransactionNumber(Carbon $timestamp): string
    {
        $prefix = 'TRX-' . $timestamp->format('Ymd') . '-';
        $nextNumber = DB::table('sales')
            ->whereDate('sale_date', $timestamp->toDateString())
            ->lockForUpdate()
            ->count() + 1;

        return $prefix . str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }

    private function makeWalkInNumber(Carbon $timestamp): string
    {
        $nextNumber = DB::table('sales')
            ->whereDate('sale_date', $timestamp->toDateString())
            ->whereNull('customer_id')
            ->lockForUpdate()
            ->count() + 1;

        return 'Walk-in Customer #' . str_pad((string) $nextNumber, 3, '0', STR_PAD_LEFT);
    }
}
