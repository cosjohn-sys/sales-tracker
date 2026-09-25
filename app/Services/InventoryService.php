<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    public function recordSale(int $productId, string $inventoryDate, int $beginningStock, int $quantitySold, CarbonInterface $timestamp): void
    {
        $record = $this->getInventoryRecord($productId, $inventoryDate);

        if ($record === null) {
            DB::table('inventory')->insert([
                'product_id' => $productId,
                'inventory_date' => $inventoryDate,
                'beginning_stock' => $beginningStock,
                'stock_added' => 0,
                'quantity_sold' => $quantitySold,
                'damaged' => 0,
                'adjustments' => 0,
                'ending_stock' => $beginningStock - $quantitySold,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);

            return;
        }

        $newQuantitySold = (int) $record->quantity_sold + $quantitySold;
        $endingStock = $this->calculateEndingStock(
            (int) $record->beginning_stock,
            (int) $record->stock_added,
            $newQuantitySold,
            (int) $record->damaged,
            (int) $record->adjustments
        );

        DB::table('inventory')
            ->where('id', $record->id)
            ->update([
                'quantity_sold' => $newQuantitySold,
                'ending_stock' => $endingStock,
                'updated_at' => $timestamp,
            ]);
    }

    public function recordAdjustment(
        int $productId,
        string $inventoryDate,
        int $beginningStock,
        string $adjustmentType,
        int $quantity,
        CarbonInterface $timestamp
    ): void {
        $record = $this->getInventoryRecord($productId, $inventoryDate);

        $stockAdded = 0;
        $damaged = 0;
        $adjustments = 0;

        if ($adjustmentType === 'Add') {
            $stockAdded = $quantity;
        }

        if ($adjustmentType === 'Deduct') {
            $adjustments = -$quantity;
        }

        if ($adjustmentType === 'Damaged') {
            $damaged = $quantity;
        }

        if ($record === null) {
            DB::table('inventory')->insert([
                'product_id' => $productId,
                'inventory_date' => $inventoryDate,
                'beginning_stock' => $beginningStock,
                'stock_added' => $stockAdded,
                'quantity_sold' => 0,
                'damaged' => $damaged,
                'adjustments' => $adjustments,
                'ending_stock' => $this->calculateEndingStock($beginningStock, $stockAdded, 0, $damaged, $adjustments),
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);

            return;
        }

        $newStockAdded = (int) $record->stock_added + $stockAdded;
        $newDamaged = (int) $record->damaged + $damaged;
        $newAdjustments = (int) $record->adjustments + $adjustments;
        $endingStock = $this->calculateEndingStock(
            (int) $record->beginning_stock,
            $newStockAdded,
            (int) $record->quantity_sold,
            $newDamaged,
            $newAdjustments
        );

        DB::table('inventory')
            ->where('id', $record->id)
            ->update([
                'stock_added' => $newStockAdded,
                'damaged' => $newDamaged,
                'adjustments' => $newAdjustments,
                'ending_stock' => $endingStock,
                'updated_at' => $timestamp,
            ]);
    }

    private function getInventoryRecord(int $productId, string $inventoryDate): ?object
    {
        return DB::table('inventory')
            ->where('product_id', $productId)
            ->whereDate('inventory_date', $inventoryDate)
            ->lockForUpdate()
            ->first();
    }

    private function calculateEndingStock(int $beginningStock, int $stockAdded, int $quantitySold, int $damaged, int $adjustments): int
    {
        return $beginningStock + $stockAdded - $quantitySold - $damaged + $adjustments;
    }
}
