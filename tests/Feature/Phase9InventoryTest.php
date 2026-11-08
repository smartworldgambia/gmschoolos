<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Asset;
use App\Models\AssetMaintenance;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\StockMovement;
use App\Models\User;
use App\Notifications\SchoolAnnouncement;
use Illuminate\Support\Facades\Notification;

/** Phase 9D: consumable stock, signed movements, low-stock alerts, tagged assets. */
class Phase9InventoryTest extends Phase9TestCase
{
    private User $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->store = $this->user('storekeeper');
    }

    private function location(string $name = 'Store A'): InventoryLocation
    {
        $this->actAs($this->store)->post(route('inventory.locations.store'), ['name' => $name])->assertSessionHas('success', 'ok.inv.locationSaved');

        return InventoryLocation::query()->withoutGlobalScopes()->where('name', $name)->firstOrFail();
    }

    private function item(string $sku = 'PEN-1', int $reorder = 5): InventoryItem
    {
        $this->actAs($this->store)->post(route('inventory.items.store'), [
            'name' => 'Exercise pens', 'sku' => $sku, 'unit' => 'pcs', 'reorder_level' => $reorder,
        ])->assertSessionHas('success', 'ok.inv.itemSaved');

        return InventoryItem::query()->withoutGlobalScopes()->where('sku', $sku)->firstOrFail();
    }

    public function test_only_store_staff_can_use_inventory(): void
    {
        foreach ([$this->teacher, $this->user('parent')] as $u) {
            $this->actAs($u)->get(route('inventory.items.index'))->assertForbidden();
            $this->actAs($u)->post(route('inventory.items.store'), ['name' => 'X', 'sku' => 'X1', 'unit' => 'pcs', 'reorder_level' => 0])->assertForbidden();
        }
        $this->actAs($this->store)->get(route('inventory.items.index'))->assertOk()->assertInertia(fn ($p) => $p->component('Inventory/Index'));
        $this->actAs($this->store)->get(route('inventory.assets.index'))->assertOk()->assertInertia(fn ($p) => $p->component('Inventory/Assets'));
    }

    public function test_another_schools_stock_is_invisible(): void
    {
        $loc = $this->location();
        $mine = $this->item();
        $other = $this->otherSchool();
        $foreignItem = $this->inSchool(fn () => InventoryItem::query()->create(['sku' => 'FOR-1', 'name' => 'Foreign', 'unit' => 'pcs', 'reorder_level' => 0]), $other);
        $foreignLoc = $this->inSchool(fn () => InventoryLocation::query()->create(['name' => 'Away store']), $other);

        $this->actAs($this->store)->get(route('inventory.items.show', $foreignItem->id))->assertNotFound();
        $this->actAs($this->store)->post(route('inventory.stock.move', $foreignItem->id), [
            'type' => 'receipt', 'inventory_location_id' => $loc->id, 'quantity' => 1,
        ])->assertNotFound();
        $this->actAs($this->store)->post(route('inventory.stock.move', $mine->id), [
            'type' => 'receipt', 'inventory_location_id' => $foreignLoc->id, 'quantity' => 1,
        ])->assertSessionHasErrors('inventory_location_id');
        $this->assertSame(0, StockMovement::query()->withoutGlobalScopes()->count());
    }

    public function test_sku_and_location_names_are_unique_and_stock_cannot_go_below_zero(): void
    {
        $this->item('PEN-1');
        $this->actAs($this->store)->post(route('inventory.items.store'), [
            'name' => 'Again', 'sku' => 'PEN-1', 'unit' => 'pcs', 'reorder_level' => 0,
        ])->assertSessionHas('error', 'err.inv.skuExists');

        $loc = $this->location('Main');
        $this->actAs($this->store)->post(route('inventory.locations.store'), ['name' => 'Main'])->assertSessionHas('error', 'err.inv.locationExists');

        $item = InventoryItem::query()->withoutGlobalScopes()->where('sku', 'PEN-1')->firstOrFail();
        $this->actAs($this->store)->post(route('inventory.stock.move', $item->id), [
            'type' => 'receipt', 'inventory_location_id' => $loc->id, 'quantity' => 4,
        ])->assertSessionHas('success', 'ok.inv.moved');
        $this->actAs($this->store)->post(route('inventory.stock.move', $item->id), [
            'type' => 'issue', 'inventory_location_id' => $loc->id, 'quantity' => 5,
        ])->assertSessionHas('error', 'err.inv.insufficient');
        $this->assertSame(4, (int) \DB::table('inventory_stock')->where('inventory_item_id', $item->id)->sum('quantity'));
    }

    public function test_transfers_and_adjustments_keep_a_signed_ledger(): void
    {
        $from = $this->location('A');
        $to = $this->location('B');
        $item = $this->item('INK-1', 0);

        $this->actAs($this->store)->post(route('inventory.stock.move', $item->id), [
            'type' => 'receipt', 'inventory_location_id' => $from->id, 'quantity' => 10,
        ]);
        $this->actAs($this->store)->post(route('inventory.stock.move', $item->id), [
            'type' => 'transfer', 'inventory_location_id' => $from->id, 'to_location_id' => $to->id, 'quantity' => 4,
        ])->assertSessionHas('success');
        $this->actAs($this->store)->post(route('inventory.stock.move', $item->id), [
            'type' => 'adjustment', 'inventory_location_id' => $from->id, 'quantity' => -1, 'reason' => 'Count correction',
        ])->assertSessionHas('success');
        $this->actAs($this->store)->post(route('inventory.stock.move', $item->id), [
            'type' => 'adjustment', 'inventory_location_id' => $from->id, 'quantity' => -1,
        ])->assertSessionHas('error', 'err.inv.adjustReason');

        $this->assertSame(5, (int) \DB::table('inventory_stock')->where('inventory_item_id', $item->id)->where('inventory_location_id', $from->id)->value('quantity'));
        $this->assertSame(4, (int) \DB::table('inventory_stock')->where('inventory_item_id', $item->id)->where('inventory_location_id', $to->id)->value('quantity'));
        $this->assertSame(['receipt', 'transfer_out', 'transfer_in', 'adjustment'], StockMovement::query()->withoutGlobalScopes()->orderBy('id')->pluck('type')->all());
    }

    public function test_low_stock_notifies_once_until_the_item_recovers(): void
    {
        Notification::fake();
        $loc = $this->location();
        $item = $this->item('CHALK-1', 5);

        $this->actAs($this->store)->post(route('inventory.stock.move', $item->id), [
            'type' => 'receipt', 'inventory_location_id' => $loc->id, 'quantity' => 8,
        ]);
        Notification::assertNothingSent();

        $this->actAs($this->store)->post(route('inventory.stock.move', $item->id), [
            'type' => 'issue', 'inventory_location_id' => $loc->id, 'quantity' => 4,
        ]);
        Notification::assertSentTo($this->store, SchoolAnnouncement::class);
        Notification::fake();

        $this->actAs($this->store)->post(route('inventory.stock.move', $item->id), [
            'type' => 'issue', 'inventory_location_id' => $loc->id, 'quantity' => 1,
        ]);
        Notification::assertNothingSent();

        $this->actAs($this->store)->post(route('inventory.stock.move', $item->id), [
            'type' => 'receipt', 'inventory_location_id' => $loc->id, 'quantity' => 10,
        ]);
        $this->actAs($this->store)->post(route('inventory.stock.move', $item->id), [
            'type' => 'issue', 'inventory_location_id' => $loc->id, 'quantity' => 10,
        ]);
        Notification::assertSentTo($this->store, SchoolAnnouncement::class);
        $this->assertTrue(ActivityLog::query()->withoutGlobalScopes()->where('event', 'inv.stock.low')->exists());
    }

    public function test_assets_are_tagged_assigned_maintained_and_retired_once(): void
    {
        $staff = $this->staffRecord();
        $this->actAs($this->store)->post(route('inventory.assets.store'), [
            'tag' => 'LAP-01', 'name' => 'Staff laptop', 'condition' => 'good',
        ])->assertSessionHas('success', 'ok.asset.saved');
        $this->actAs($this->store)->post(route('inventory.assets.store'), [
            'tag' => 'LAP-01', 'name' => 'Duplicate', 'condition' => 'new',
        ])->assertSessionHas('error', 'err.asset.tagExists');

        $asset = Asset::query()->withoutGlobalScopes()->where('tag', 'LAP-01')->firstOrFail();
        $this->actAs($this->store)->post(route('inventory.assets.assign', $asset->id), [])->assertSessionHas('error', 'err.asset.target');
        $this->actAs($this->store)->post(route('inventory.assets.assign', $asset->id), ['staff_id' => $staff->id])->assertSessionHas('success', 'ok.asset.assigned');
        $this->assertSame('in_use', $asset->fresh()->status);
        $this->actAs($this->store)->post(route('inventory.assets.return', $asset->id), [])->assertSessionHas('success', 'ok.asset.returned');

        $this->actAs($this->store)->post(route('inventory.assets.maintenance.store', $asset->id), [
            'kind' => 'repair', 'description' => 'Screen', 'scheduled_on' => now()->toDateString(),
        ])->assertSessionHas('success', 'ok.asset.maintScheduled');
        $this->assertSame('under_maintenance', $asset->fresh()->status);
        $this->actAs($this->store)->post(route('inventory.assets.assign', $asset->id), ['staff_id' => $staff->id])->assertSessionHas('error', 'err.asset.underMaintenance');

        $maint = AssetMaintenance::query()->withoutGlobalScopes()->firstOrFail();
        $this->actAs($this->store)->post(route('inventory.maintenance.complete', $maint->id), ['cost' => 50])->assertSessionHas('success', 'ok.asset.maintDone');
        $this->actAs($this->store)->post(route('inventory.assets.retire', $asset->id), [
            'kind' => 'disposed', 'reason' => 'Beyond repair',
        ])->assertSessionHas('success', 'ok.asset.retired');
        $this->actAs($this->store)->post(route('inventory.assets.assign', $asset->id), ['staff_id' => $staff->id])->assertSessionHas('error', 'err.asset.final');
        $this->assertSame('disposed', $asset->fresh()->status);
    }
}
