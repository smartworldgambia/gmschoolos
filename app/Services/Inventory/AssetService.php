<?php

namespace App\Services\Inventory;

use App\Exceptions\AcademicRuleException;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\AssetMaintenance;
use App\Models\InventoryLocation;
use App\Models\Staff;
use App\Models\User;
use App\Services\Ops\Ops;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Fixed assets: who holds an item, where it is, its repairs and servicing, and its disposal.
 * Status: in_store | in_use | under_maintenance | disposed | lost. An asset that is disposed or lost is final.
 */
class AssetService
{
    public function assign(Asset $asset, ?int $staffId, ?int $locationId, ?string $on, ?string $notes, User $actor): Asset
    {
        if (! $staffId && ! $locationId) {
            throw AcademicRuleException::because('err.asset.target');
        }

        return DB::transaction(function () use ($asset, $staffId, $locationId, $on, $notes, $actor) {
            $asset = $this->lock($asset);
            if (in_array($asset->status, ['disposed', 'lost'], true)) {
                throw AcademicRuleException::because('err.asset.final');
            }
            if ($asset->status === 'under_maintenance') {
                throw AcademicRuleException::because('err.asset.underMaintenance');
            }
            $staff = $staffId ? Staff::query()->where('status', 'active')->find($staffId) : null;
            if ($staffId && ! $staff) {
                throw AcademicRuleException::because('err.asset.staff');
            }
            if ($locationId) {
                InventoryLocation::query()->findOrFail($locationId);
            }
            $date = $on ? Carbon::parse($on) : today();
            $this->closeOpenAssignment($asset, $date);

            AssetAssignment::query()->create([
                'asset_id' => $asset->id, 'staff_id' => $staff?->id, 'inventory_location_id' => $locationId, 'assigned_on' => $date->toDateString(),
                'assigned_by' => $actor->id, 'notes' => $notes ? mb_substr(trim($notes), 0, 300) : null,
            ]);
            $asset->update([
                'assigned_staff_id' => $staff?->id,
                'inventory_location_id' => $locationId ?: $asset->inventory_location_id,
                'status' => $staff ? 'in_use' : 'in_store',
            ]);
            Ops::audit('asset.assigned', 'Asset assigned: '.$asset->tag, ['asset_id' => $asset->id, 'staff_id' => $staff?->id, 'location_id' => $locationId]);

            return $asset;
        });
    }

    public function returnToStore(Asset $asset, ?int $locationId, ?string $on, User $actor): Asset
    {
        return DB::transaction(function () use ($asset, $locationId, $on, $actor) {
            $asset = $this->lock($asset);
            if ($asset->status !== 'in_use') {
                throw AcademicRuleException::because('err.asset.notInUse');
            }
            if ($locationId) {
                InventoryLocation::query()->findOrFail($locationId);
            }
            $this->closeOpenAssignment($asset, $on ? Carbon::parse($on) : today());
            $asset->update(['assigned_staff_id' => null, 'status' => 'in_store', 'inventory_location_id' => $locationId ?: $asset->inventory_location_id]);
            Ops::audit('asset.returned', 'Asset returned to store: '.$asset->tag, ['asset_id' => $asset->id]);

            return $asset;
        });
    }

    /** Retire an asset for good: written off (disposed) or lost. A reason is mandatory and the open assignment and repairs are closed. */
    public function retire(Asset $asset, string $kind, ?string $reason, ?string $on, User $actor): Asset
    {
        if (! in_array($kind, ['disposed', 'lost'], true)) {
            throw AcademicRuleException::because('err.asset.retireKind');
        }
        $reason = $reason !== null ? mb_substr(trim($reason), 0, 300) : '';
        if ($reason === '') {
            throw AcademicRuleException::because('err.asset.retireReason');
        }

        return DB::transaction(function () use ($asset, $kind, $reason, $on, $actor) {
            $asset = $this->lock($asset);
            if (in_array($asset->status, ['disposed', 'lost'], true)) {
                throw AcademicRuleException::because('err.asset.final');
            }
            $date = $on ? Carbon::parse($on) : today();
            $this->closeOpenAssignment($asset, $date);
            AssetMaintenance::query()->where('asset_id', $asset->id)->where('status', 'scheduled')->update(['status' => 'cancelled']);
            $asset->update(['status' => $kind, 'assigned_staff_id' => null, 'disposed_on' => $date->toDateString(), 'disposal_reason' => $reason]);
            Ops::audit('asset.'.$kind, 'Asset '.$kind.': '.$asset->tag, ['asset_id' => $asset->id, 'reason' => $reason, 'by' => $actor->id]);

            return $asset;
        });
    }

    /** A repair takes the asset out of service until it is completed or cancelled; servicing and inspections do not. */
    public function scheduleMaintenance(Asset $asset, string $kind, string $description, string $scheduledOn): AssetMaintenance
    {
        if (! in_array($kind, ['preventive', 'repair', 'inspection'], true)) {
            throw AcademicRuleException::because('err.asset.maintKind');
        }

        return DB::transaction(function () use ($asset, $kind, $description, $scheduledOn) {
            $asset = $this->lock($asset);
            if (in_array($asset->status, ['disposed', 'lost'], true)) {
                throw AcademicRuleException::because('err.asset.final');
            }
            $m = AssetMaintenance::query()->create([
                'asset_id' => $asset->id, 'kind' => $kind, 'description' => mb_substr(trim($description), 0, 300), 'scheduled_on' => $scheduledOn, 'status' => 'scheduled',
            ]);
            if ($kind === 'repair') {
                $asset->update(['status' => 'under_maintenance']);
            }
            Ops::audit('asset.maintenance.scheduled', 'Maintenance scheduled: '.$asset->tag, ['asset_id' => $asset->id, 'kind' => $kind]);

            return $m;
        });
    }

    public function completeMaintenance(AssetMaintenance $m, ?string $completedOn, int $cost, ?string $performedBy): AssetMaintenance
    {
        return DB::transaction(function () use ($m, $completedOn, $cost, $performedBy) {
            $m = AssetMaintenance::query()->lockForUpdate()->findOrFail($m->id);
            if ($m->status !== 'scheduled') {
                throw AcademicRuleException::because('err.asset.maintClosed');
            }
            $date = $completedOn ? Carbon::parse($completedOn) : today();
            if ($date->gt(today())) {
                throw AcademicRuleException::because('err.asset.maintFuture');
            }
            $m->update(['status' => 'done', 'completed_on' => $date->toDateString(), 'cost' => max(0, $cost), 'performed_by' => $performedBy ? mb_substr(trim($performedBy), 0, 150) : null]);
            $this->restoreAfterRepair($this->lock($m->asset));
            Ops::audit('asset.maintenance.done', 'Maintenance completed', ['maintenance_id' => $m->id, 'asset_id' => $m->asset_id, 'cost' => $m->cost]);

            return $m;
        });
    }

    public function cancelMaintenance(AssetMaintenance $m): AssetMaintenance
    {
        return DB::transaction(function () use ($m) {
            $m = AssetMaintenance::query()->lockForUpdate()->findOrFail($m->id);
            if ($m->status !== 'scheduled') {
                throw AcademicRuleException::because('err.asset.maintClosed');
            }
            $m->update(['status' => 'cancelled']);
            $this->restoreAfterRepair($this->lock($m->asset));
            Ops::audit('asset.maintenance.cancelled', 'Maintenance cancelled', ['maintenance_id' => $m->id]);

            return $m;
        });
    }

    private function restoreAfterRepair(Asset $asset): void
    {
        if ($asset->status !== 'under_maintenance') {
            return;
        }
        $stillOpen = AssetMaintenance::query()->where('asset_id', $asset->id)->where('kind', 'repair')->where('status', 'scheduled')->exists();
        if (! $stillOpen) {
            $asset->update(['status' => $asset->assigned_staff_id ? 'in_use' : 'in_store']);
        }
    }

    private function closeOpenAssignment(Asset $asset, Carbon $on): void
    {
        $open = AssetAssignment::query()->where('asset_id', $asset->id)->whereNull('returned_on')->lockForUpdate()->get();
        foreach ($open as $a) {
            if ($on->lt($a->assigned_on)) {
                throw AcademicRuleException::because('err.asset.dateOrder');
            }
            $a->update(['returned_on' => $on->toDateString()]);
        }
    }

    private function lock(Asset $asset): Asset
    {
        return Asset::query()->lockForUpdate()->findOrFail($asset->id);
    }
}
