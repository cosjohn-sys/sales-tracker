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

class StockAdjustmentController extends Controller
{
    public function __construct(private readonly InventoryService $inventoryService)
    {
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'adjustment_type' => ['required', 'in:Add,Deduct,Damaged'],
            'quantity' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return new JsonResponse(['message' => 'Validation failed.', 'errors' => $validator->errors()], 422);
        }

        try {
            $adjustment = DB::transaction(function () use ($request): array {
                $timestamp = Carbon::now();
                $product = DB::table('products')
                    ->where('id', $request->input('product_id'))
                    ->lockForUpdate()
                    ->first();

                if ($product === null) {
                    throw new InvalidArgumentException('Product not found.');
                }

                $quantity = (int) $request->input('quantity');
                $adjustmentType = $request->input('adjustment_type');
                $currentStock = (int) $product->current_stock;
                $delta = $adjustmentType === 'Add' ? $quantity : -$quantity;
                $newStock = $currentStock + $delta;

                if ($newStock < 0) {
                    throw new InvalidArgumentException('Stock cannot go below zero.');
                }

                DB::table('products')
                    ->where('id', $product->id)
                    ->update([
                        'current_stock' => $newStock,
                        'updated_at' => $timestamp,
                    ]);

                $id = DB::table('stock_adjustments')->insertGetId([
                    'product_id' => $product->id,
                    'adjustment_type' => $adjustmentType,
                    'quantity' => $quantity,
                    'reason' => $request->input('reason'),
                    'adjustment_date' => $timestamp->toDateTimeString(),
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);

                $this->inventoryService->recordAdjustment(
                    (int) $product->id,
                    $timestamp->toDateString(),
                    $currentStock,
                    $adjustmentType,
                    $quantity,
                    $timestamp
                );

                return [
                    'id' => $id,
                    'product_name' => $product->name,
                    'adjustment_type' => $adjustmentType,
                    'quantity' => $quantity,
                    'current_stock' => $newStock,
                ];
            });
        } catch (InvalidArgumentException $exception) {
            return new JsonResponse(['message' => $exception->getMessage()], 422);
        } catch (Throwable $exception) {
            return new JsonResponse(['message' => 'Unable to save adjustment.', 'detail' => $exception->getMessage()], 500);
        }

        return new JsonResponse(['message' => 'Stock updated.', 'data' => $adjustment], 201);
    }
}
