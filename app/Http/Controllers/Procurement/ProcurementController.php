<?php

namespace App\Http\Controllers\Procurement;

use App\Exceptions\AcademicRuleException;
use App\Http\Controllers\Concerns\HandlesAcademicRules;
use App\Http\Controllers\Concerns\SharesSchoolShell;
use App\Http\Controllers\Controller;
use App\Models\GoodsReceipt;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Services\Ops\Ops;
use App\Services\Ops\OpsSettings;
use App\Services\Procurement\ProcurementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Purchase requests, orders, goods received, supplier invoices and supplier records. */
class ProcurementController extends Controller
{
    use HandlesAcademicRules, SharesSchoolShell;

    // ------------------------------------------------------------------ requests

    public function requests(Request $request): Response
    {
        $status = (string) $request->query('status', '');
        $user = $request->user();
        $ownOnly = $user->can('procurement.request') && ! $user->canAny(['procurement.manage', 'procurement.approve']);

        $rows = PurchaseRequest::query()->with('requester:id,name')
            ->when($ownOnly, fn ($q) => $q->where('requested_by', $user->id))
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->latest('id')->paginate(25)->withQueryString();

        return Inertia::render('Procurement/Requests', array_merge($this->shellProps(), [
            'requests' => $rows->through(fn (PurchaseRequest $r) => [
                'id' => $r->id, 'number' => $r->number, 'title' => $r->title, 'status' => $r->status, 'estimated_total' => (int) $r->estimated_total,
                'needed_by' => $r->needed_by?->toDateString(), 'requester' => $r->requester?->name,
            ]),
            'filter' => $status,
            'stock' => InventoryItem::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'sku']),
            'can' => ['manage' => $user->can('procurement.manage'), 'approve' => $user->can('procurement.approve'), 'request' => $user->can('procurement.request')],
        ]));
    }

    public function storeRequest(Request $request, ProcurementService $service): RedirectResponse
    {
        $v = $this->requestRules($request);

        return $this->attempt(function () use ($v, $request, $service) {
            $pr = $service->createRequest($request->user(), [
                'title' => $v['title'], 'justification' => $v['justification'] ?? null, 'needed_by' => $v['needed_by'] ?? null,
            ], $v['items']);

            return redirect()->route('procurement.requests.show', $pr)->with('success', 'ok.proc.requestSaved');
        });
    }

    public function showRequest(Request $request, PurchaseRequest $purchaseRequest): Response
    {
        $user = $request->user();
        abort_unless($user->canAny(['procurement.manage', 'procurement.approve']) || (int) $purchaseRequest->requested_by === (int) $user->id, 403);
        $purchaseRequest->load(['requester:id,name', 'decider:id,name', 'items.item:id,name,sku']);

        return Inertia::render('Procurement/RequestShow', array_merge($this->shellProps(), [
            'request' => [
                'id' => $purchaseRequest->id, 'number' => $purchaseRequest->number, 'title' => $purchaseRequest->title, 'justification' => $purchaseRequest->justification,
                'needed_by' => $purchaseRequest->needed_by?->toDateString(), 'status' => $purchaseRequest->status, 'estimated_total' => (int) $purchaseRequest->estimated_total,
                'requester' => $purchaseRequest->requester?->name, 'decider' => $purchaseRequest->decider?->name, 'decision_note' => $purchaseRequest->decision_note,
                'own' => (int) $purchaseRequest->requested_by === (int) $user->id,
                'items' => $purchaseRequest->items->map(fn (PurchaseRequestItem $i) => [
                    'id' => $i->id, 'description' => $i->description, 'quantity' => (int) $i->quantity, 'estimated_unit_cost' => (int) $i->estimated_unit_cost,
                    'inventory_item_id' => $i->inventory_item_id, 'item' => $i->item?->name,
                ]),
            ],
            'stock' => InventoryItem::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'sku']),
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'can' => ['manage' => $user->can('procurement.manage'), 'approve' => $user->can('procurement.approve')],
        ]));
    }

    public function updateRequest(Request $request, PurchaseRequest $purchaseRequest, ProcurementService $service): RedirectResponse
    {
        abort_unless($request->user()->canAny(['procurement.manage', 'procurement.request']) && ((int) $purchaseRequest->requested_by === (int) $request->user()->id || $request->user()->can('procurement.manage')), 403);
        $v = $this->requestRules($request);

        return $this->attempt(fn () => $service->updateRequest($purchaseRequest, [
            'title' => $v['title'], 'justification' => $v['justification'] ?? null, 'needed_by' => $v['needed_by'] ?? null,
        ], $v['items']), 'ok.proc.requestSaved');
    }

    public function submitRequest(Request $request, PurchaseRequest $purchaseRequest, ProcurementService $service): RedirectResponse
    {
        abort_unless((int) $purchaseRequest->requested_by === (int) $request->user()->id || $request->user()->can('procurement.manage'), 403);

        return $this->attempt(fn () => $service->submit($purchaseRequest, $request->user()), 'ok.proc.requestSubmitted');
    }

    public function decideRequest(Request $request, PurchaseRequest $purchaseRequest, ProcurementService $service): RedirectResponse
    {
        $v = $request->validate(['decision' => ['required', 'in:approved,rejected'], 'note' => ['nullable', 'string', 'max:500']]);

        return $this->attempt(fn () => $service->decide($purchaseRequest, $request->user(), $v['decision'], $v['note'] ?? null), $v['decision'] === 'approved' ? 'ok.proc.requestApproved' : 'ok.proc.requestRejected');
    }

    public function cancelRequest(PurchaseRequest $purchaseRequest, ProcurementService $service): RedirectResponse
    {
        return $this->attempt(fn () => $service->cancelRequest($purchaseRequest), 'ok.proc.requestCancelled');
    }

    // ------------------------------------------------------------------ orders

    public function orders(Request $request): Response
    {
        $status = (string) $request->query('status', '');
        $rows = PurchaseOrder::query()->with('supplier:id,name')->when($status !== '', fn ($q) => $q->where('status', $status))->latest('id')->paginate(25)->withQueryString();

        return Inertia::render('Procurement/Orders', array_merge($this->shellProps(), [
            'orders' => $rows->through(fn (PurchaseOrder $o) => [
                'id' => $o->id, 'number' => $o->number, 'supplier' => $o->supplier?->name, 'status' => $o->status,
                'total_amount' => (int) $o->total_amount, 'ordered_on' => $o->ordered_on?->toDateString(),
            ]),
            'filter' => $status,
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'stock' => InventoryItem::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'sku']),
            'approvedRequests' => PurchaseRequest::query()->where('status', 'approved')->latest('id')->limit(50)->get(['id', 'number', 'title']),
            'can' => ['manage' => $request->user()->can('procurement.manage')],
        ]));
    }

    public function storeOrder(Request $request, ProcurementService $service): RedirectResponse
    {
        $v = $request->validate([
            'supplier_id' => ['required', $this->inSchool('suppliers')],
            'purchase_request_id' => ['nullable', $this->inSchool('purchase_requests')],
            'expected_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['nullable', 'array', 'max:80'],
            'items.*.description' => ['required_with:items', 'string', 'max:250'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1', 'max:100000'],
            'items.*.unit_cost' => ['required_with:items', 'integer', 'min:0', 'max:100000000'],
            'items.*.inventory_item_id' => ['nullable', $this->inSchool('inventory_items')],
        ]);

        return $this->attempt(function () use ($v, $request, $service) {
            $po = $service->createOrder($request->user(), (int) $v['supplier_id'], [
                'expected_on' => $v['expected_on'] ?? null, 'notes' => $v['notes'] ?? null,
            ], $v['items'] ?? [], isset($v['purchase_request_id']) ? (int) $v['purchase_request_id'] : null);

            return redirect()->route('procurement.orders.show', $po)->with('success', 'ok.proc.orderSaved');
        });
    }

    public function showOrder(PurchaseOrder $order, ProcurementService $service): Response
    {
        $order->load(['supplier:id,name', 'request:id,number,title', 'items.item:id,name,sku', 'receipts']);

        return Inertia::render('Procurement/OrderShow', array_merge($this->shellProps(), [
            'order' => [
                'id' => $order->id, 'number' => $order->number, 'status' => $order->status, 'supplier' => $order->supplier?->name, 'supplier_id' => $order->supplier_id,
                'request' => $order->request?->number, 'ordered_on' => $order->ordered_on?->toDateString(), 'expected_on' => $order->expected_on?->toDateString(),
                'total_amount' => (int) $order->total_amount, 'notes' => $order->notes, 'received_value' => $service->receivedValue($order->id),
                'items' => $order->items->map(fn (PurchaseOrderItem $i) => [
                    'id' => $i->id, 'description' => $i->description, 'quantity' => (int) $i->quantity, 'unit_cost' => (int) $i->unit_cost,
                    'received_quantity' => (int) $i->received_quantity, 'inventory_item_id' => $i->inventory_item_id, 'item' => $i->item?->name,
                ]),
                'receipts' => $order->receipts->map(fn (GoodsReceipt $g) => [
                    'id' => $g->id, 'number' => $g->number, 'received_on' => $g->received_on?->toDateString(), 'note' => $g->note,
                ]),
            ],
            'locations' => InventoryLocation::query()->orderBy('name')->get(['id', 'name']),
            'can' => ['manage' => request()->user()->can('procurement.manage')],
        ]));
    }

    public function issueOrder(PurchaseOrder $order, ProcurementService $service): RedirectResponse
    {
        return $this->attempt(fn () => $service->issueOrder($order), 'ok.proc.orderIssued');
    }

    public function cancelOrder(PurchaseOrder $order, ProcurementService $service): RedirectResponse
    {
        return $this->attempt(fn () => $service->cancelOrder($order), 'ok.proc.orderCancelled');
    }

    public function receive(Request $request, PurchaseOrder $order, ProcurementService $service): RedirectResponse
    {
        $v = $request->validate([
            'received_on' => ['required', 'date'],
            'inventory_location_id' => ['nullable', $this->inSchool('inventory_locations')],
            'note' => ['nullable', 'string', 'max:300'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', 'integer'],
            'lines.*.quantity' => ['required', 'integer', 'min:0', 'max:100000'],
        ]);
        $map = [];
        foreach ($v['lines'] as $line) {
            $map[(int) $line['item_id']] = (int) $line['quantity'];
        }

        return $this->attempt(fn () => $service->receive($order, $map, isset($v['inventory_location_id']) ? (int) $v['inventory_location_id'] : null, $v['received_on'], $v['note'] ?? null, $request->user()), 'ok.proc.received');
    }

    // ------------------------------------------------------------------ invoices

    public function invoices(Request $request): Response
    {
        $status = (string) $request->query('status', '');
        $rows = SupplierInvoice::query()->with(['supplier:id,name', 'order:id,number'])->when($status !== '', fn ($q) => $q->where('status', $status))->latest('id')->paginate(25)->withQueryString();

        return Inertia::render('Procurement/Invoices', array_merge($this->shellProps(), [
            'invoices' => $rows->through(fn (SupplierInvoice $i) => [
                'id' => $i->id, 'invoice_number' => $i->invoice_number, 'supplier' => $i->supplier?->name, 'order' => $i->order?->number,
                'amount' => (int) $i->amount, 'invoice_date' => $i->invoice_date?->toDateString(), 'due_on' => $i->due_on?->toDateString(),
                'status' => $i->status, 'own' => (int) $i->recorded_by === (int) $request->user()->id,
            ]),
            'filter' => $status,
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'orders' => PurchaseOrder::query()->whereIn('status', ['issued', 'partially_received', 'received'])->latest('id')->limit(80)->get(['id', 'number', 'supplier_id', 'total_amount']),
            'can' => ['manage' => $request->user()->can('procurement.manage'), 'approve' => $request->user()->can('procurement.approve')],
        ]));
    }

    public function storeInvoice(Request $request, ProcurementService $service): RedirectResponse
    {
        $v = $request->validate([
            'supplier_id' => ['required', $this->inSchool('suppliers')],
            'purchase_order_id' => ['nullable', $this->inSchool('purchase_orders')],
            'invoice_number' => ['required', 'string', 'max:80'],
            'invoice_date' => ['required', 'date'],
            'due_on' => ['nullable', 'date'],
            'amount' => ['required', 'integer', 'min:1', 'max:100000000'],
        ]);

        return $this->attempt(fn () => $service->recordInvoice($request->user(), (int) $v['supplier_id'], isset($v['purchase_order_id']) ? (int) $v['purchase_order_id'] : null, [
            'invoice_number' => $v['invoice_number'], 'invoice_date' => $v['invoice_date'], 'due_on' => $v['due_on'] ?? null, 'amount' => (int) $v['amount'],
        ]), 'ok.proc.invoiceRecorded');
    }

    public function approveInvoice(Request $request, SupplierInvoice $invoice, ProcurementService $service): RedirectResponse
    {
        return $this->attempt(fn () => $service->approveInvoice($invoice, $request->user()), 'ok.proc.invoiceApproved');
    }

    public function payInvoice(Request $request, SupplierInvoice $invoice, ProcurementService $service): RedirectResponse
    {
        $v = $request->validate(['paid_on' => ['required', 'date'], 'payment_reference' => ['required', 'string', 'max:120']]);

        return $this->attempt(fn () => $service->payInvoice($invoice, $v['paid_on'], $v['payment_reference']), 'ok.proc.invoicePaid');
    }

    public function cancelInvoice(SupplierInvoice $invoice, ProcurementService $service): RedirectResponse
    {
        return $this->attempt(fn () => $service->cancelInvoice($invoice), 'ok.proc.invoiceCancelled');
    }

    // ------------------------------------------------------------------ suppliers, reports, setup

    public function suppliers(): Response
    {
        return Inertia::render('Procurement/Suppliers', array_merge($this->shellProps(), [
            'suppliers' => Supplier::query()->orderBy('name')->get()->map(fn (Supplier $s) => [
                'id' => $s->id, 'name' => $s->name, 'contact_person' => $s->contact_person, 'phone' => $s->phone, 'email' => $s->email,
                'address' => $s->address, 'is_active' => (bool) $s->is_active,
            ]),
        ]));
    }

    public function storeSupplier(Request $request): RedirectResponse
    {
        $v = $this->supplierRules($request);

        return $this->attempt(function () use ($v) {
            if (Supplier::query()->where('name', $v['name'])->exists()) {
                throw AcademicRuleException::because('err.proc.supplierExists');
            }
            Supplier::query()->create($v + ['is_active' => true]);
            Ops::audit('proc.supplier.created', 'Supplier added: '.$v['name'], []);
        }, 'ok.proc.supplierSaved');
    }

    public function updateSupplier(Request $request, Supplier $supplier): RedirectResponse
    {
        $v = $this->supplierRules($request) + $request->validate(['is_active' => ['required', 'boolean']]);

        return $this->attempt(function () use ($v, $supplier) {
            if (Supplier::query()->where('name', $v['name'])->where('id', '!=', $supplier->id)->exists()) {
                throw AcademicRuleException::because('err.proc.supplierExists');
            }
            $supplier->update($v);
            Ops::audit('proc.supplier.updated', 'Supplier updated: '.$supplier->name, ['supplier_id' => $supplier->id]);
        }, 'ok.proc.supplierSaved');
    }

    public function reports(): Response
    {
        $open = PurchaseRequest::query()->whereIn('status', ['submitted', 'approved'])->count();
        $issued = PurchaseOrder::query()->whereIn('status', ['issued', 'partially_received'])->count();
        $unpaid = (int) SupplierInvoice::query()->whereIn('status', ['recorded', 'approved'])->sum('amount');
        $paidYtd = (int) SupplierInvoice::query()->where('status', 'paid')->whereYear('paid_on', now()->year)->sum('amount');

        return Inertia::render('Procurement/Reports', array_merge($this->shellProps(), [
            'stats' => ['open_requests' => $open, 'open_orders' => $issued, 'unpaid' => $unpaid, 'paid_ytd' => $paidYtd],
            'bySupplier' => SupplierInvoice::query()->where('status', 'paid')->selectRaw('supplier_id, sum(amount) as total')->groupBy('supplier_id')->with('supplier:id,name')->get()
                ->map(fn ($r) => ['supplier' => $r->supplier?->name, 'total' => (int) $r->total]),
        ]));
    }

    public function setup(OpsSettings $settings): Response
    {
        return Inertia::render('Procurement/Setup', array_merge($this->shellProps(), [
            'settings' => $settings->group('procurement'),
        ]));
    }

    public function saveSetup(Request $request, OpsSettings $settings): RedirectResponse
    {
        $v = $request->validate(['self_approval' => ['required', 'boolean']]);

        return $this->attempt(function () use ($v, $settings) {
            $settings->save('procurement', ['self_approval' => $v['self_approval']]);
            Ops::audit('proc.settings.saved', 'Procurement rules saved', ['self_approval' => (bool) $v['self_approval']]);
        }, 'ok.proc.settingsSaved');
    }

    public function export()
    {
        Ops::audit('proc.exported', 'Procurement invoices exported', []);
        $rows = SupplierInvoice::query()->with(['supplier:id,name', 'order:id,number'])->latest('id')->get()
            ->map(fn ($i) => [$i->invoice_number, $i->supplier?->name, $i->order?->number, $i->amount, $i->status, $i->invoice_date?->toDateString(), $i->paid_on?->toDateString(), $i->payment_reference]);

        return Ops::csv('procurement-invoices.csv', ['Invoice', 'Supplier', 'Order', 'Amount', 'Status', 'Date', 'Paid on', 'Reference'], $rows);
    }

    private function requestRules(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:190'],
            'justification' => ['nullable', 'string', 'max:2000'],
            'needed_by' => ['nullable', 'date'],
            'items' => ['required', 'array', 'min:1', 'max:80'],
            'items.*.description' => ['required', 'string', 'max:250'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'items.*.estimated_unit_cost' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'items.*.inventory_item_id' => ['nullable', $this->inSchool('inventory_items')],
        ]);
    }

    private function supplierRules(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'contact_person' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:300'],
        ]);
    }
}
