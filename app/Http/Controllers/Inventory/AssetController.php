<?php

namespace App\Http\Controllers\Inventory;

use App\Exceptions\AcademicRuleException;
use App\Http\Controllers\Concerns\HandlesAcademicRules;
use App\Http\Controllers\Concerns\SharesSchoolShell;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\AssetMaintenance;
use App\Models\InventoryCategory;
use App\Models\InventoryLocation;
use App\Models\Staff;
use App\Models\Supplier;
use App\Services\Inventory\AssetService;
use App\Services\Ops\Ops;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Fixed assets: tags, assignment, maintenance and disposal. */
class AssetController extends Controller
{
    use HandlesAcademicRules, SharesSchoolShell;

    public const CONDITIONS = ['new', 'good', 'fair', 'poor'];

    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');
        $rows = Asset::query()->with(['location:id,name', 'assignedStaff:id,first_name,last_name'])
            ->when($q !== '', fn ($b) => $b->where(fn ($w) => $w->where('name', 'like', "%$q%")->orWhere('tag', 'like', "%$q%")->orWhere('serial_number', 'like', "%$q%")))
            ->when($status !== '', fn ($b) => $b->where('status', $status))
            ->orderBy('tag')->paginate(25)->withQueryString();

        return Inertia::render('Inventory/Assets', array_merge($this->shellProps(), [
            'assets' => $rows->through(fn (Asset $a) => [
                'id' => $a->id, 'tag' => $a->tag, 'name' => $a->name, 'status' => $a->status, 'condition' => $a->condition,
                'location' => $a->location?->name, 'holder' => $a->assignedStaff?->full_name,
            ]),
            'q' => $q, 'filter' => $status,
            'categories' => InventoryCategory::query()->orderBy('name')->get(['id', 'name']),
            'locations' => InventoryLocation::query()->orderBy('name')->get(['id', 'name']),
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'conditions' => self::CONDITIONS,
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        $v = $this->assetRules($request);

        return $this->attempt(function () use ($v) {
            if (Asset::query()->where('tag', $v['tag'])->exists()) {
                throw AcademicRuleException::because('err.asset.tagExists');
            }
            $a = Asset::query()->create($v + ['status' => 'in_store']);
            Ops::audit('asset.created', 'Asset tagged '.$a->tag, ['asset_id' => $a->id]);

            return redirect()->route('inventory.assets.show', $a)->with('success', 'ok.asset.saved');
        });
    }

    public function show(Asset $asset): Response
    {
        $asset->load(['category:id,name', 'location:id,name', 'supplier:id,name', 'assignedStaff:id,first_name,last_name']);

        return Inertia::render('Inventory/AssetShow', array_merge($this->shellProps(), [
            'asset' => [
                'id' => $asset->id, 'tag' => $asset->tag, 'name' => $asset->name, 'status' => $asset->status, 'condition' => $asset->condition,
                'serial_number' => $asset->serial_number, 'acquired_on' => $asset->acquired_on?->toDateString(), 'cost' => (int) $asset->cost,
                'notes' => $asset->notes, 'inventory_category_id' => $asset->inventory_category_id, 'inventory_location_id' => $asset->inventory_location_id,
                'supplier_id' => $asset->supplier_id, 'category' => $asset->category?->name, 'location' => $asset->location?->name,
                'supplier' => $asset->supplier?->name, 'holder' => $asset->assignedStaff?->full_name, 'disposal_reason' => $asset->disposal_reason,
                'disposed_on' => $asset->disposed_on?->toDateString(),
            ],
            'assignments' => AssetAssignment::query()->with(['staff:id,first_name,last_name', 'location:id,name'])->where('asset_id', $asset->id)->latest('id')->get()
                ->map(fn (AssetAssignment $a) => [
                    'id' => $a->id, 'staff' => $a->staff?->full_name, 'location' => $a->location?->name, 'assigned_on' => $a->assigned_on?->toDateString(),
                    'returned_on' => $a->returned_on?->toDateString(), 'notes' => $a->notes,
                ]),
            'maintenance' => AssetMaintenance::query()->where('asset_id', $asset->id)->latest('id')->get()->map(fn (AssetMaintenance $m) => [
                'id' => $m->id, 'kind' => $m->kind, 'description' => $m->description, 'scheduled_on' => $m->scheduled_on?->toDateString(),
                'completed_on' => $m->completed_on?->toDateString(), 'cost' => (int) $m->cost, 'performed_by' => $m->performed_by, 'status' => $m->status,
            ]),
            'staff' => Staff::query()->where('status', 'active')->orderBy('last_name')->get(['id', 'first_name', 'last_name'])->map(fn ($s) => ['id' => $s->id, 'name' => $s->full_name]),
            'locations' => InventoryLocation::query()->orderBy('name')->get(['id', 'name']),
            'categories' => InventoryCategory::query()->orderBy('name')->get(['id', 'name']),
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'conditions' => self::CONDITIONS,
        ]));
    }

    public function update(Request $request, Asset $asset): RedirectResponse
    {
        $v = $this->assetRules($request, $asset);

        return $this->attempt(function () use ($v, $asset) {
            if (in_array($asset->status, ['disposed', 'lost'], true)) {
                throw AcademicRuleException::because('err.asset.final');
            }
            if (Asset::query()->where('tag', $v['tag'])->where('id', '!=', $asset->id)->exists()) {
                throw AcademicRuleException::because('err.asset.tagExists');
            }
            $asset->update($v);
            Ops::audit('asset.updated', 'Asset updated: '.$asset->tag, ['asset_id' => $asset->id]);
        }, 'ok.asset.saved');
    }

    public function assign(Request $request, Asset $asset, AssetService $service): RedirectResponse
    {
        $v = $request->validate([
            'staff_id' => ['nullable', $this->inSchool('staff')],
            'inventory_location_id' => ['nullable', $this->inSchool('inventory_locations')],
            'assigned_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:300'],
        ]);

        return $this->attempt(fn () => $service->assign($asset, isset($v['staff_id']) ? (int) $v['staff_id'] : null, isset($v['inventory_location_id']) ? (int) $v['inventory_location_id'] : null, $v['assigned_on'] ?? null, $v['notes'] ?? null, $request->user()), 'ok.asset.assigned');
    }

    public function returnToStore(Request $request, Asset $asset, AssetService $service): RedirectResponse
    {
        $v = $request->validate(['inventory_location_id' => ['nullable', $this->inSchool('inventory_locations')], 'returned_on' => ['nullable', 'date']]);

        return $this->attempt(fn () => $service->returnToStore($asset, isset($v['inventory_location_id']) ? (int) $v['inventory_location_id'] : null, $v['returned_on'] ?? null, $request->user()), 'ok.asset.returned');
    }

    public function retire(Request $request, Asset $asset, AssetService $service): RedirectResponse
    {
        $v = $request->validate(['kind' => ['required', 'in:disposed,lost'], 'reason' => ['required', 'string', 'max:300'], 'on' => ['nullable', 'date']]);

        return $this->attempt(fn () => $service->retire($asset, $v['kind'], $v['reason'], $v['on'] ?? null, $request->user()), 'ok.asset.retired');
    }

    public function scheduleMaintenance(Request $request, Asset $asset, AssetService $service): RedirectResponse
    {
        $v = $request->validate(['kind' => ['required', 'in:preventive,repair,inspection'], 'description' => ['required', 'string', 'max:300'], 'scheduled_on' => ['required', 'date']]);

        return $this->attempt(fn () => $service->scheduleMaintenance($asset, $v['kind'], $v['description'], $v['scheduled_on']), 'ok.asset.maintScheduled');
    }

    public function completeMaintenance(Request $request, AssetMaintenance $maintenance, AssetService $service): RedirectResponse
    {
        $v = $request->validate(['completed_on' => ['nullable', 'date'], 'cost' => ['nullable', 'integer', 'min:0', 'max:100000000'], 'performed_by' => ['nullable', 'string', 'max:150']]);

        return $this->attempt(fn () => $service->completeMaintenance($maintenance, $v['completed_on'] ?? null, (int) ($v['cost'] ?? 0), $v['performed_by'] ?? null), 'ok.asset.maintDone');
    }

    public function cancelMaintenance(AssetMaintenance $maintenance, AssetService $service): RedirectResponse
    {
        return $this->attempt(fn () => $service->cancelMaintenance($maintenance), 'ok.asset.maintCancelled');
    }

    private function assetRules(Request $request, ?Asset $asset = null): array
    {
        return $request->validate([
            'tag' => ['required', 'string', 'max:60', 'regex:/^[A-Za-z0-9._-]+$/'],
            'name' => ['required', 'string', 'max:160'],
            'inventory_category_id' => ['nullable', $this->inSchool('inventory_categories')],
            'inventory_location_id' => ['nullable', $this->inSchool('inventory_locations')],
            'supplier_id' => ['nullable', $this->inSchool('suppliers')],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'acquired_on' => ['nullable', 'date'],
            'cost' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'condition' => ['required', Rule::in(self::CONDITIONS)],
            'notes' => ['nullable', 'string', 'max:300'],
        ]);
    }
}
