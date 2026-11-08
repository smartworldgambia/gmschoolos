<?php

namespace App\Services\Procurement;

use App\Exceptions\AcademicRuleException;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\User;
use App\Services\Inventory\InventoryService;
use App\Services\Ops\Ops;
use App\Services\Ops\OpsSettings;
use Illuminate\Support\Facades\DB;

/**
 * Purchase request -> approval -> purchase order -> goods received (stock updated) -> supplier invoice -> approved -> paid.
 * Nobody approves their own request or invoice unless the school switched `procurement.self_approval` on.
 * An invoice can only be approved for goods that were actually received.
 */
class ProcurementService
{
    public function __construct(private readonly OpsSettings $settings, private readonly InventoryService $inventory) {}

    // ------------------------------------------------------------------ requests

    /** @param  array<int,array{description:string,quantity:int,estimated_unit_cost?:int,inventory_item_id?:int|null}>  $items */
    public function createRequest(User $actor, array $data, array $items): PurchaseRequest
    {
        $items = $this->cleanRequestItems($items);

        return Ops::numbered(function () use ($actor, $data, $items) {
            $pr = PurchaseRequest::query()->create($data + [
                'number' => Ops::nextNumber('purchase_requests', 'PR'), 'status' => 'draft', 'requested_by' => $actor->id, 'estimated_total' => $this->requestTotal($items),
            ]);
            $this->putRequestItems($pr, $items);
            Ops::audit('proc.request.created', 'Purchase request '.$pr->number, ['request_id' => $pr->id]);

            return $pr;
        });
    }

    public function updateRequest(PurchaseRequest $pr, array $data, array $items): PurchaseRequest
    {
        $items = $this->cleanRequestItems($items);

        return DB::transaction(function () use ($pr, $data, $items) {
            $pr = PurchaseRequest::query()->lockForUpdate()->findOrFail($pr->id);
            if ($pr->status !== 'draft') {
                throw AcademicRuleException::because('err.proc.notDraft');
            }
            $pr->update($data + ['estimated_total' => $this->requestTotal($items)]);
            $pr->items()->delete();
            $this->putRequestItems($pr, $items);
            Ops::audit('proc.request.updated', 'Purchase request '.$pr->number.' edited', ['request_id' => $pr->id]);

            return $pr;
        });
    }

    public function submit(PurchaseRequest $pr, User $actor): PurchaseRequest
    {
        $pr = DB::transaction(function () use ($pr) {
            $pr = PurchaseRequest::query()->lockForUpdate()->findOrFail($pr->id);
            if ($pr->status !== 'draft') {
                throw AcademicRuleException::because('err.proc.notDraft');
            }
            if ($pr->items()->count() < 1) {
                throw AcademicRuleException::because('err.proc.noItems');
            }
            $pr->update(['status' => 'submitted']);
            Ops::audit('proc.request.submitted', 'Purchase request '.$pr->number.' submitted', ['request_id' => $pr->id]);

            return $pr;
        });
        Ops::notify(Ops::usersWith('procurement.approve', $actor->id), 'Purchase request to review: '.$pr->number, $pr->title, route('procurement.requests.show', $pr->id), 'info', 'procurement');

        return $pr;
    }

    public function decide(PurchaseRequest $pr, User $actor, string $decision, ?string $note): PurchaseRequest
    {
        if (! in_array($decision, ['approved', 'rejected'], true)) {
            throw AcademicRuleException::because('err.proc.decision');
        }
        $note = $note !== null ? mb_substr(trim($note), 0, 500) : null;
        if ($decision === 'rejected' && ($note === null || $note === '')) {
            throw AcademicRuleException::because('err.proc.rejectNote');
        }

        $pr = DB::transaction(function () use ($pr, $actor, $decision, $note) {
            $pr = PurchaseRequest::query()->lockForUpdate()->findOrFail($pr->id);
            if ($pr->status !== 'submitted') {
                throw AcademicRuleException::because('err.proc.notSubmitted');
            }
            if ((int) $pr->requested_by === $actor->id && ! $this->settings->get('procurement', 'self_approval', false)) {
                throw AcademicRuleException::because('err.proc.selfApprove');
            }
            $pr->update(['status' => $decision, 'decided_by' => $actor->id, 'decided_at' => now(), 'decision_note' => $note]);
            Ops::audit('proc.request.'.$decision, 'Purchase request '.$pr->number.' '.$decision, ['request_id' => $pr->id]);

            return $pr;
        });
        if ($pr->requester && $pr->requester->id !== $actor->id) {
            Ops::notify([$pr->requester], 'Purchase request '.$decision.': '.$pr->number, $pr->title.($note ? ' - '.$note : ''), route('procurement.requests.show', $pr->id), $decision === 'approved' ? 'success' : 'warning', 'procurement');
        }

        return $pr;
    }

    public function cancelRequest(PurchaseRequest $pr): PurchaseRequest
    {
        return DB::transaction(function () use ($pr) {
            $pr = PurchaseRequest::query()->lockForUpdate()->findOrFail($pr->id);
            if (! in_array($pr->status, ['draft', 'submitted', 'approved'], true)) {
                throw AcademicRuleException::because('err.proc.cannotCancel');
            }
            $pr->update(['status' => 'cancelled']);
            Ops::audit('proc.request.cancelled', 'Purchase request '.$pr->number.' cancelled', ['request_id' => $pr->id]);

            return $pr;
        });
    }

    // ------------------------------------------------------------------ purchase orders

    /**
     * New draft order. With a request, the order must come from an approved one and (when no lines are given) copies its lines.
     *
     * @param  array<int,array{description:string,quantity:int,unit_cost:int,inventory_item_id?:int|null}>  $items
     */
    public function createOrder(User $actor, int $supplierId, array $data, array $items, ?int $requestId = null): PurchaseOrder
    {
        return Ops::numbered(function () use ($actor, $supplierId, $data, $items, $requestId) {
            $supplier = Supplier::query()->findOrFail($supplierId);
            if (! $supplier->is_active) {
                throw AcademicRuleException::because('err.proc.supplierInactive');
            }

            $request = null;
            if ($requestId) {
                $request = PurchaseRequest::query()->lockForUpdate()->findOrFail($requestId);
                if ($request->status !== 'approved') {
                    throw AcademicRuleException::because('err.proc.requestNotApproved');
                }
                if ($items === []) {
                    $items = $request->items()->get()->map(fn (PurchaseRequestItem $i) => [
                        'description' => $i->description, 'quantity' => (int) $i->quantity, 'unit_cost' => (int) $i->estimated_unit_cost, 'inventory_item_id' => $i->inventory_item_id,
                    ])->all();
                }
            }
            $items = $this->cleanOrderItems($items);

            $po = PurchaseOrder::query()->create($data + [
                'number' => Ops::nextNumber('purchase_orders', 'PO'), 'supplier_id' => $supplier->id, 'purchase_request_id' => $request?->id,
                'status' => 'draft', 'total_amount' => $this->orderTotal($items), 'created_by' => $actor->id,
            ]);
            foreach ($items as $i) {
                PurchaseOrderItem::query()->create($i + ['purchase_order_id' => $po->id]);
            }
            $request?->update(['status' => 'ordered']);
            Ops::audit('proc.order.created', 'Purchase order '.$po->number, ['order_id' => $po->id, 'supplier_id' => $supplier->id, 'total' => $po->total_amount]);

            return $po;
        });
    }

    public function issueOrder(PurchaseOrder $po): PurchaseOrder
    {
        return DB::transaction(function () use ($po) {
            $po = PurchaseOrder::query()->lockForUpdate()->findOrFail($po->id);
            if ($po->status !== 'draft') {
                throw AcademicRuleException::because('err.proc.orderNotDraft');
            }
            if (! $po->supplier?->is_active) {
                throw AcademicRuleException::because('err.proc.supplierInactive');
            }
            $po->update(['status' => 'issued', 'ordered_on' => today()->toDateString()]);
            Ops::audit('proc.order.issued', 'Purchase order '.$po->number.' issued', ['order_id' => $po->id]);

            return $po;
        });
    }

    /** Only an order that has received nothing can be cancelled; its request goes back to "approved" so it can be re-ordered. */
    public function cancelOrder(PurchaseOrder $po): PurchaseOrder
    {
        return DB::transaction(function () use ($po) {
            $po = PurchaseOrder::query()->lockForUpdate()->findOrFail($po->id);
            if (! in_array($po->status, ['draft', 'issued'], true) || $po->receipts()->exists()) {
                throw AcademicRuleException::because('err.proc.orderCannotCancel');
            }
            if (SupplierInvoice::query()->where('purchase_order_id', $po->id)->whereIn('status', ['approved', 'paid'])->exists()) {
                throw AcademicRuleException::because('err.proc.orderHasInvoices');
            }
            $po->update(['status' => 'cancelled']);
            PurchaseRequest::query()->where('id', $po->purchase_request_id)->where('status', 'ordered')->update(['status' => 'approved']);
            Ops::audit('proc.order.cancelled', 'Purchase order '.$po->number.' cancelled', ['order_id' => $po->id]);

            return $po;
        });
    }

    /**
     * Book goods in. `$lines` maps purchase_order_item_id => quantity received now. Items linked to a stock item are added to
     * stock at `$locationId`; quantities above what is still outstanding are refused.
     *
     * @param  array<int,int>  $lines
     */
    public function receive(PurchaseOrder $po, array $lines, ?int $locationId, string $receivedOn, ?string $note, User $actor): GoodsReceipt
    {
        $lines = array_filter(array_map('intval', $lines), fn ($q) => $q > 0);
        if ($lines === []) {
            throw AcademicRuleException::because('err.proc.noLines');
        }

        return Ops::numbered(function () use ($po, $lines, $locationId, $receivedOn, $note, $actor) {
            $po = PurchaseOrder::query()->lockForUpdate()->findOrFail($po->id);
            if (! in_array($po->status, ['issued', 'partially_received'], true)) {
                throw AcademicRuleException::because('err.proc.orderNotReceivable');
            }
            if (\Illuminate\Support\Carbon::parse($receivedOn)->gt(today())) {
                throw AcademicRuleException::because('err.proc.receiveFuture');
            }
            $orderItems = PurchaseOrderItem::query()->where('purchase_order_id', $po->id)->lockForUpdate()->get()->keyBy('id');

            $stocked = false;
            foreach ($lines as $id => $qty) {
                $oi = $orderItems->get($id);
                if (! $oi) {
                    throw AcademicRuleException::because('err.proc.lineForeign');
                }
                if ($qty > (int) $oi->quantity - (int) $oi->received_quantity) {
                    throw AcademicRuleException::because('err.proc.overReceive');
                }
                $stocked = $stocked || $oi->inventory_item_id !== null;
            }
            if ($stocked) {
                if (! $locationId) {
                    throw AcademicRuleException::because('err.proc.locationRequired');
                }
                InventoryLocation::query()->findOrFail($locationId);
            }

            $grn = GoodsReceipt::query()->create([
                'number' => Ops::nextNumber('goods_receipts', 'GRN'), 'purchase_order_id' => $po->id, 'inventory_location_id' => $stocked ? $locationId : null,
                'received_on' => $receivedOn, 'note' => $note ? mb_substr(trim($note), 0, 300) : null, 'received_by' => $actor->id,
            ]);
            foreach ($lines as $id => $qty) {
                $oi = $orderItems->get($id);
                GoodsReceiptItem::query()->create(['goods_receipt_id' => $grn->id, 'purchase_order_item_id' => $oi->id, 'quantity' => $qty]);
                $oi->update(['received_quantity' => (int) $oi->received_quantity + $qty]);
                if ($oi->inventory_item_id) {
                    $this->inventory->receiveFromOrder(InventoryItem::query()->findOrFail($oi->inventory_item_id), (int) $locationId, $qty, $grn->number, $actor);
                }
            }

            $complete = $orderItems->every(fn ($oi) => (int) $oi->fresh()->received_quantity >= (int) $oi->quantity);
            $po->update(['status' => $complete ? 'received' : 'partially_received']);
            Ops::audit('proc.goods.received', 'Goods received '.$grn->number.' for '.$po->number, ['order_id' => $po->id, 'receipt_id' => $grn->id]);

            return $grn;
        });
    }

    // ------------------------------------------------------------------ supplier invoices

    public function recordInvoice(User $actor, int $supplierId, ?int $orderId, array $data): SupplierInvoice
    {
        $supplier = Supplier::query()->findOrFail($supplierId);
        if ((int) $data['amount'] < 1) {
            throw AcademicRuleException::because('err.proc.invoiceAmount');
        }
        if ($orderId) {
            $po = PurchaseOrder::query()->findOrFail($orderId);
            if ((int) $po->supplier_id !== (int) $supplier->id) {
                throw AcademicRuleException::because('err.proc.invoiceSupplier');
            }
            if ($po->status === 'cancelled') {
                throw AcademicRuleException::because('err.proc.orderCancelled');
            }
        }
        if (SupplierInvoice::query()->where('supplier_id', $supplier->id)->where('invoice_number', $data['invoice_number'])->exists()) {
            throw AcademicRuleException::because('err.proc.invoiceDuplicate');
        }

        $inv = SupplierInvoice::query()->create($data + ['supplier_id' => $supplier->id, 'purchase_order_id' => $orderId, 'status' => 'recorded', 'recorded_by' => $actor->id]);
        Ops::audit('proc.invoice.recorded', 'Supplier invoice '.$inv->invoice_number, ['invoice_id' => $inv->id, 'amount' => $inv->amount]);

        return $inv;
    }

    /** Approve for payment. Needs a linked order, a second person (unless self-approval is on), and received goods that cover the amount. */
    public function approveInvoice(SupplierInvoice $inv, User $actor): SupplierInvoice
    {
        return DB::transaction(function () use ($inv, $actor) {
            $inv = SupplierInvoice::query()->lockForUpdate()->findOrFail($inv->id);
            if ($inv->status !== 'recorded') {
                throw AcademicRuleException::because('err.proc.invoiceNotRecorded');
            }
            if ((int) $inv->recorded_by === $actor->id && ! $this->settings->get('procurement', 'self_approval', false)) {
                throw AcademicRuleException::because('err.proc.selfApproveInvoice');
            }
            if (! $inv->purchase_order_id) {
                throw AcademicRuleException::because('err.proc.invoiceNoOrder');
            }
            $already = (int) SupplierInvoice::query()->where('purchase_order_id', $inv->purchase_order_id)->whereIn('status', ['approved', 'paid'])->where('id', '!=', $inv->id)->sum('amount');
            if ($already + (int) $inv->amount > $this->receivedValue($inv->purchase_order_id)) {
                throw AcademicRuleException::because('err.proc.notReceived');
            }
            $inv->update(['status' => 'approved', 'approved_by' => $actor->id]);
            Ops::audit('proc.invoice.approved', 'Supplier invoice '.$inv->invoice_number.' approved', ['invoice_id' => $inv->id, 'amount' => $inv->amount]);

            return $inv;
        });
    }

    public function payInvoice(SupplierInvoice $inv, string $paidOn, string $reference): SupplierInvoice
    {
        $reference = trim($reference);
        if ($reference === '') {
            throw AcademicRuleException::because('err.proc.payReference');
        }

        return DB::transaction(function () use ($inv, $paidOn, $reference) {
            $inv = SupplierInvoice::query()->lockForUpdate()->findOrFail($inv->id);
            if ($inv->status !== 'approved') {
                throw AcademicRuleException::because('err.proc.invoiceNotApproved');
            }
            if (\Illuminate\Support\Carbon::parse($paidOn)->gt(today())) {
                throw AcademicRuleException::because('err.proc.payFuture');
            }
            $inv->update(['status' => 'paid', 'paid_on' => $paidOn, 'payment_reference' => mb_substr($reference, 0, 120)]);
            Ops::audit('proc.invoice.paid', 'Supplier invoice '.$inv->invoice_number.' paid', ['invoice_id' => $inv->id, 'amount' => $inv->amount, 'reference' => $inv->payment_reference]);

            return $inv;
        });
    }

    public function cancelInvoice(SupplierInvoice $inv): SupplierInvoice
    {
        return DB::transaction(function () use ($inv) {
            $inv = SupplierInvoice::query()->lockForUpdate()->findOrFail($inv->id);
            if (! in_array($inv->status, ['recorded', 'approved'], true)) {
                throw AcademicRuleException::because('err.proc.invoiceCannotCancel');
            }
            $inv->update(['status' => 'cancelled']);
            Ops::audit('proc.invoice.cancelled', 'Supplier invoice '.$inv->invoice_number.' cancelled', ['invoice_id' => $inv->id]);

            return $inv;
        });
    }

    /** Value of goods received so far on an order (received quantity x agreed unit cost). */
    public function receivedValue(int $orderId): int
    {
        return (int) PurchaseOrderItem::query()->where('purchase_order_id', $orderId)->get()->sum(fn ($i) => (int) $i->received_quantity * (int) $i->unit_cost);
    }

    // ------------------------------------------------------------------ helpers

    private function cleanRequestItems(array $items): array
    {
        $clean = [];
        foreach ($items as $i) {
            $desc = trim((string) ($i['description'] ?? ''));
            $qty = (int) ($i['quantity'] ?? 0);
            if ($desc === '' && $qty === 0) {
                continue;
            }
            if ($desc === '' || $qty < 1) {
                throw AcademicRuleException::because('err.proc.itemInvalid');
            }
            $stockId = ! empty($i['inventory_item_id']) ? (int) $i['inventory_item_id'] : null;
            if ($stockId) {
                InventoryItem::query()->findOrFail($stockId);
            }
            $clean[] = ['description' => mb_substr($desc, 0, 250), 'quantity' => $qty, 'estimated_unit_cost' => max(0, (int) ($i['estimated_unit_cost'] ?? 0)), 'inventory_item_id' => $stockId];
        }

        return $clean;
    }

    private function cleanOrderItems(array $items): array
    {
        $clean = [];
        foreach ($items as $i) {
            $desc = trim((string) ($i['description'] ?? ''));
            $qty = (int) ($i['quantity'] ?? 0);
            if ($desc === '' || $qty < 1) {
                throw AcademicRuleException::because('err.proc.itemInvalid');
            }
            $stockId = ! empty($i['inventory_item_id']) ? (int) $i['inventory_item_id'] : null;
            if ($stockId) {
                InventoryItem::query()->findOrFail($stockId);
            }
            $clean[] = ['description' => mb_substr($desc, 0, 250), 'quantity' => $qty, 'unit_cost' => max(0, (int) ($i['unit_cost'] ?? 0)), 'inventory_item_id' => $stockId];
        }
        if ($clean === []) {
            throw AcademicRuleException::because('err.proc.noItems');
        }

        return $clean;
    }

    private function requestTotal(array $items): int
    {
        return (int) collect($items)->sum(fn ($i) => $i['quantity'] * $i['estimated_unit_cost']);
    }

    private function orderTotal(array $items): int
    {
        return (int) collect($items)->sum(fn ($i) => $i['quantity'] * $i['unit_cost']);
    }

    private function putRequestItems(PurchaseRequest $pr, array $items): void
    {
        foreach ($items as $i) {
            PurchaseRequestItem::query()->create($i + ['purchase_request_id' => $pr->id]);
        }
    }
}
