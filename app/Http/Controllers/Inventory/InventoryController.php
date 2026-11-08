<?php

namespace App\Http\Controllers\Inventory;

use App\Exceptions\AcademicRuleException;
use App\Http\Controllers\Concerns\HandlesAcademicRules;
use App\Http\Controllers\Concerns\SharesSchoolShell;
use App\Http\Controllers\Controller;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryStock;
use App\Models\StockMovement;
use App\Services\Inventory\InventoryService;
use App\Services\Ops\Ops;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Consumable stock: items, locations, categories, signed movements and low-stock alerts. */
class InventoryController extends Controller
{
    use HandlesAcademicRules, SharesSchoolShell;

    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));
        $items = InventoryItem::query()->with('category:id,name')->withSum('stock as qty', 'quantity')
            ->when($q !== '', fn ($b) => $b->where(fn ($w) => $w->where('name', 'like', "%$q%")->orWhere('sku', 'like', "%$q%")))
            ->orderBy('name')->paginate(25)->withQueryString();

        return Inertia::render('Inventory/Index', array_merge($this->shellProps(), [
            'items' => $items->through(fn (InventoryItem $i) => [
                'id' => $i->id, 'sku' => $i->sku, 'name' => $i->name, 'unit' => $i->unit, 'category' => $i->category?->name,
                'quantity' => (int) $i->qty, 'reorder_level' => (int) $i->reorder_level, 'is_active' => (bool) $i->is_active, 'low' => (int) $i->qty <= (int) $i->reorder_level && (int) $i->reorder_level > 0,
            ]),
            'q' => $q,
            'categories' => InventoryCategory::query()->orderBy('name')->get(['id', 'name']),
            'locations' => InventoryLocation::query()->orderBy('name')->get(['id', 'name']),
            'low' => InventoryItem::query()->where('reorder_level', '>', 0)->withSum('stock as qty', 'quantity')->get()
                ->filter(fn ($i) => (int) $i->qty <= (int) $i->reorder_level)->count(),
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        $v = $this->itemRules($request);

        return $this->attempt(function () use ($v) {
            if (InventoryItem::query()->where('sku', $v['sku'])->exists()) {
                throw AcademicRuleException::because('err.inv.skuExists');
            }
            $item = InventoryItem::query()->create($v + ['is_active' => true]);
            Ops::audit('inv.item.created', 'Stock item created: '.$item->name, ['item_id' => $item->id]);

            return redirect()->route('inventory.items.show', $item)->with('success', 'ok.inv.itemSaved');
        });
    }

    public function show(InventoryItem $item, InventoryService $service): Response
    {
        $item->load(['category:id,name', 'stock.location:id,name']);

        return Inertia::render('Inventory/ItemShow', array_merge($this->shellProps(), [
            'item' => [
                'id' => $item->id, 'sku' => $item->sku, 'name' => $item->name, 'unit' => $item->unit, 'reorder_level' => (int) $item->reorder_level,
                'is_active' => (bool) $item->is_active, 'notes' => $item->notes, 'inventory_category_id' => $item->inventory_category_id,
                'total' => $service->total($item),
            ],
            'stock' => $item->stock->map(fn (InventoryStock $s) => ['id' => $s->id, 'location_id' => $s->inventory_location_id, 'location' => $s->location?->name, 'quantity' => (int) $s->quantity]),
            'movements' => StockMovement::query()->with(['location:id,name', 'user:id,name'])->where('inventory_item_id', $item->id)->latest('id')->limit(40)->get()
                ->map(fn (StockMovement $m) => [
                    'id' => $m->id, 'type' => $m->type, 'quantity' => (int) $m->quantity, 'balance_after' => (int) $m->balance_after,
                    'location' => $m->location?->name, 'reason' => $m->reason, 'reference' => $m->reference, 'by' => $m->user?->name, 'at' => $m->created_at?->toDateTimeString(),
                ]),
            'categories' => InventoryCategory::query()->orderBy('name')->get(['id', 'name']),
            'locations' => InventoryLocation::query()->orderBy('name')->get(['id', 'name']),
            'moveTypes' => InventoryService::MOVES,
        ]));
    }

    public function update(Request $request, InventoryItem $item): RedirectResponse
    {
        $v = $this->itemRules($request, $item) + $request->validate(['is_active' => ['required', 'boolean']]);

        return $this->attempt(function () use ($v, $item) {
            if (InventoryItem::query()->where('sku', $v['sku'])->where('id', '!=', $item->id)->exists()) {
                throw AcademicRuleException::because('err.inv.skuExists');
            }
            $item->update($v);
            Ops::audit('inv.item.updated', 'Stock item updated: '.$item->name, ['item_id' => $item->id]);
        }, 'ok.inv.itemSaved');
    }

    public function move(Request $request, InventoryItem $item, InventoryService $service): RedirectResponse
    {
        $v = $request->validate([
            'type' => ['required', Rule::in(InventoryService::MOVES)],
            'inventory_location_id' => ['required', $this->inSchool('inventory_locations')],
            'to_location_id' => ['nullable', $this->inSchool('inventory_locations')],
            'quantity' => ['required', 'integer', 'min:-1000000', 'max:1000000'],
            'reason' => ['nullable', 'string', 'max:300'],
            'reference' => ['nullable', 'string', 'max:80'],
        ]);

        return $this->attempt(fn () => $service->move($item, (int) $v['inventory_location_id'], $v['type'], (int) $v['quantity'], $v['reason'] ?? null, $v['reference'] ?? null, $request->user(), isset($v['to_location_id']) ? (int) $v['to_location_id'] : null), 'ok.inv.moved');
    }

    public function stock(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));
        $rows = StockMovement::query()->with(['item:id,name,sku', 'location:id,name', 'user:id,name'])
            ->when($q !== '', fn ($b) => $b->whereHas('item', fn ($w) => $w->where('name', 'like', "%$q%")->orWhere('sku', 'like', "%$q%")))
            ->latest('id')->paginate(40)->withQueryString();

        return Inertia::render('Inventory/Stock', array_merge($this->shellProps(), [
            'movements' => $rows->through(fn (StockMovement $m) => [
                'id' => $m->id, 'item' => $m->item?->name, 'sku' => $m->item?->sku, 'type' => $m->type, 'quantity' => (int) $m->quantity,
                'balance_after' => (int) $m->balance_after, 'location' => $m->location?->name, 'reason' => $m->reason, 'reference' => $m->reference,
                'by' => $m->user?->name, 'at' => $m->created_at?->toDateTimeString(),
            ]),
            'q' => $q,
        ]));
    }

    public function setup(): Response
    {
        return Inertia::render('Inventory/Setup', array_merge($this->shellProps(), [
            'locations' => InventoryLocation::query()->orderBy('name')->get(['id', 'name', 'description']),
            'categories' => InventoryCategory::query()->orderBy('name')->get(['id', 'name']),
        ]));
    }

    public function storeLocation(Request $request): RedirectResponse
    {
        $v = $request->validate(['name' => ['required', 'string', 'max:120'], 'description' => ['nullable', 'string', 'max:300']]);

        return $this->attempt(function () use ($v) {
            if (InventoryLocation::query()->where('name', $v['name'])->exists()) {
                throw AcademicRuleException::because('err.inv.locationExists');
            }
            InventoryLocation::query()->create($v);
            Ops::audit('inv.location.created', 'Store location created', ['name' => $v['name']]);
        }, 'ok.inv.locationSaved');
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $v = $request->validate(['name' => ['required', 'string', 'max:120']]);

        return $this->attempt(function () use ($v) {
            if (InventoryCategory::query()->where('name', $v['name'])->exists()) {
                throw AcademicRuleException::because('err.inv.categoryExists');
            }
            InventoryCategory::query()->create($v);
            Ops::audit('inv.category.created', 'Stock category created', ['name' => $v['name']]);
        }, 'ok.inv.categorySaved');
    }

    private function itemRules(Request $request, ?InventoryItem $item = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'sku' => ['required', 'string', 'max:60', 'regex:/^[A-Za-z0-9._-]+$/'],
            'unit' => ['required', 'string', 'max:20'],
            'reorder_level' => ['required', 'integer', 'min:0', 'max:1000000'],
            'inventory_category_id' => ['nullable', $this->inSchool('inventory_categories')],
            'notes' => ['nullable', 'string', 'max:300'],
        ]);
    }
}
