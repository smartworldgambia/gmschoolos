<?php

namespace App\Services\Inventory;

use App\Exceptions\AcademicRuleException;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryStock;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Ops\Ops;
use Illuminate\Support\Facades\DB;

/**
 * Consumable stock. Every change is a signed stock movement with the balance it produced, so the ledger always explains the count.
 * Stock can never go below zero. A low-stock alert goes out once when an item falls to its reorder level and again only after it recovered.
 */
class InventoryService
{
    public const MOVES = ['receipt', 'issue', 'adjustment', 'transfer'];

    /**
     * @param  string  $type  receipt | issue | adjustment | transfer
     *                        receipt / issue / transfer: positive quantity. adjustment: signed, non-zero, reason required.
     */
    public function move(InventoryItem $item, int $locationId, string $type, int $quantity, ?string $reason, ?string $reference, User $actor, ?int $toLocationId = null): void
    {
        if (! in_array($type, self::MOVES, true)) {
            throw AcademicRuleException::because('err.inv.moveType');
        }
        $reason = $reason !== null ? mb_substr(trim($reason), 0, 300) : null;
        if ($type === 'adjustment') {
            if ($quantity === 0) {
                throw AcademicRuleException::because('err.inv.quantity');
            }
            if ($reason === null || $reason === '') {
                throw AcademicRuleException::because('err.inv.adjustReason');
            }
        } elseif ($quantity < 1) {
            throw AcademicRuleException::because('err.inv.quantity');
        }
        if ($type === 'transfer' && (! $toLocationId || $toLocationId === $locationId)) {
            throw AcademicRuleException::because('err.inv.transferTarget');
        }

        DB::transaction(function () use ($item, $locationId, $type, $quantity, $reason, $reference, $actor, $toLocationId) {
            $item = InventoryItem::query()->lockForUpdate()->findOrFail($item->id);
            if (! $item->is_active) {
                throw AcademicRuleException::because('err.inv.itemInactive');
            }
            InventoryLocation::query()->findOrFail($locationId);
            if ($toLocationId) {
                InventoryLocation::query()->findOrFail($toLocationId);
            }

            match ($type) {
                'receipt' => $this->apply($item, $locationId, $quantity, 'receipt', $reason, $reference, $actor),
                'issue' => $this->apply($item, $locationId, -$quantity, 'issue', $reason, $reference, $actor),
                'adjustment' => $this->apply($item, $locationId, $quantity, 'adjustment', $reason, $reference, $actor),
                'transfer' => $this->transfer($item, $locationId, (int) $toLocationId, $quantity, $reason, $reference, $actor),
            };

            Ops::audit('inv.stock.'.$type, 'Stock '.$type.': '.$item->name, ['item_id' => $item->id, 'location_id' => $locationId, 'quantity' => $quantity, 'reference' => $reference]);
            $this->checkLowStock($item);
        });
    }

    /** Stock received against a purchase order. Runs inside the caller's transaction. */
    public function receiveFromOrder(InventoryItem $item, int $locationId, int $quantity, string $reference, User $actor): void
    {
        $item = InventoryItem::query()->lockForUpdate()->findOrFail($item->id);
        $this->apply($item, $locationId, $quantity, 'receipt', null, $reference, $actor);
        $this->checkLowStock($item);
    }

    public function total(InventoryItem $item): int
    {
        return (int) InventoryStock::query()->where('inventory_item_id', $item->id)->sum('quantity');
    }

    private function transfer(InventoryItem $item, int $from, int $to, int $qty, ?string $reason, ?string $reference, User $actor): void
    {
        $this->apply($item, $from, -$qty, 'transfer_out', $reason, $reference, $actor);
        $this->apply($item, $to, $qty, 'transfer_in', $reason, $reference, $actor);
    }

    private function apply(InventoryItem $item, int $locationId, int $signed, string $type, ?string $reason, ?string $reference, User $actor): void
    {
        $stock = InventoryStock::query()->where('inventory_item_id', $item->id)->where('inventory_location_id', $locationId)->lockForUpdate()->first()
            ?? InventoryStock::query()->create(['inventory_item_id' => $item->id, 'inventory_location_id' => $locationId, 'quantity' => 0]);

        $balance = (int) $stock->quantity + $signed;
        if ($balance < 0) {
            throw AcademicRuleException::because('err.inv.insufficient');
        }
        $stock->update(['quantity' => $balance]);
        StockMovement::query()->create([
            'inventory_item_id' => $item->id, 'inventory_location_id' => $locationId, 'type' => $type, 'quantity' => $signed,
            'balance_after' => $balance, 'reason' => $reason, 'reference' => $reference ? mb_substr($reference, 0, 80) : null, 'user_id' => $actor->id,
        ]);
    }

    /** One alert when the total reaches the reorder level; re-armed once the stock is back above it. */
    private function checkLowStock(InventoryItem $item): void
    {
        $level = (int) $item->reorder_level;
        if ($level < 1) {
            return;
        }
        $total = $this->total($item);

        if ($total <= $level && $item->low_stock_alerted_at === null) {
            $item->forceFill(['low_stock_alerted_at' => now()])->save();
            Ops::notify(
                Ops::usersWith('inventory.manage'),
                'Low stock: '.$item->name,
                $item->name.' ('.$item->sku.') is down to '.$total.' '.$item->unit.' (reorder level '.$level.').',
                route('inventory.items.show', $item->id),
                'warning',
                'inventory',
            );
            Ops::audit('inv.stock.low', 'Low stock alert: '.$item->name, ['item_id' => $item->id, 'total' => $total, 'reorder_level' => $level]);
        } elseif ($total > $level && $item->low_stock_alerted_at !== null) {
            $item->forceFill(['low_stock_alerted_at' => null])->save();
        }
    }
}
