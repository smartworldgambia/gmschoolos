<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\User;

/** Phase 9D: requests, approval, orders, goods received, supplier invoices. */
class Phase9ProcurementTest extends Phase9TestCase
{
    private User $store;

    private User $requester;

    protected function setUp(): void
    {
        parent::setUp();
        $this->store = $this->user('storekeeper');
        $this->requester = $this->user('librarian');
    }

    private function supplier(string $name = 'Banjul Stationery'): Supplier
    {
        $this->actAs($this->store)->post(route('procurement.suppliers.store'), ['name' => $name])->assertSessionHas('success', 'ok.proc.supplierSaved');

        return Supplier::query()->withoutGlobalScopes()->where('name', $name)->firstOrFail();
    }

    /** @return array{0:PurchaseRequest,1:int} */
    private function submittedRequest(User $by, string $title = 'Pens'): array
    {
        $this->actAs($by)->post(route('procurement.requests.store'), [
            'title' => $title,
            'items' => [['description' => 'Blue pens', 'quantity' => 10, 'estimated_unit_cost' => 5]],
        ])->assertSessionHas('success', 'ok.proc.requestSaved');
        $pr = PurchaseRequest::query()->withoutGlobalScopes()->where('title', $title)->firstOrFail();
        $this->actAs($by)->post(route('procurement.requests.submit', $pr->id))->assertSessionHas('success', 'ok.proc.requestSubmitted');

        return [$pr->fresh(), 50];
    }

    public function test_permissions_split_request_manage_and_approve(): void
    {
        $this->actAs($this->teacher)->get(route('procurement.requests.index'))->assertForbidden();
        $this->actAs($this->requester)->get(route('procurement.requests.index'))->assertOk()->assertInertia(fn ($p) => $p->component('Procurement/Requests'));
        $this->actAs($this->requester)->get(route('procurement.orders.index'))->assertForbidden();
        $this->actAs($this->store)->get(route('procurement.orders.index'))->assertOk();
        $this->actAs($this->store)->get(route('procurement.setup.index'))->assertForbidden();
        $this->actAs($this->head)->get(route('procurement.setup.index'))->assertOk()->assertInertia(fn ($p) => $p->component('Procurement/Setup'));
        $this->actAs($this->head)->get(route('procurement.suppliers.index'))->assertForbidden();
    }

    public function test_a_requester_only_sees_their_own_requests_and_cannot_approve_them(): void
    {
        [$mine] = $this->submittedRequest($this->requester, 'Mine');
        [$theirs] = $this->submittedRequest($this->store, 'Theirs');

        $this->actAs($this->requester)->get(route('procurement.requests.show', $theirs->id))->assertForbidden();
        $this->actAs($this->requester)->get(route('procurement.requests.index'))
            ->assertOk()
            ->assertInertia(fn ($p) => $p->has('requests.data', 1)->where('requests.data.0.id', $mine->id));

        $this->actAs($this->store)->post(route('procurement.requests.decide', $theirs->id), ['decision' => 'approved'])->assertForbidden();
        $this->actAs($this->requester)->post(route('procurement.requests.decide', $mine->id), ['decision' => 'approved'])->assertForbidden();
        $this->actAs($this->head)->post(route('procurement.requests.decide', $mine->id), ['decision' => 'approved'])->assertSessionHas('success', 'ok.proc.requestApproved');
    }

    public function test_self_approval_is_refused_until_the_school_turns_it_on(): void
    {
        [$pr] = $this->submittedRequest($this->head, 'Head request');
        $this->actAs($this->head)->post(route('procurement.requests.decide', $pr->id), ['decision' => 'approved'])->assertSessionHas('error', 'err.proc.selfApprove');
        $this->actAs($this->head)->put(route('procurement.setup.save'), ['self_approval' => true])->assertSessionHas('success', 'ok.proc.settingsSaved');
        $this->actAs($this->head)->post(route('procurement.requests.decide', $pr->id), ['decision' => 'approved'])->assertSessionHas('success', 'ok.proc.requestApproved');
    }

    public function test_goods_must_be_received_before_an_invoice_can_be_approved_and_paid(): void
    {
        $supplier = $this->supplier();
        [$pr] = $this->submittedRequest($this->requester);
        $this->actAs($this->head)->post(route('procurement.requests.decide', $pr->id), ['decision' => 'approved']);

        $this->actAs($this->store)->post(route('procurement.orders.store'), [
            'supplier_id' => $supplier->id, 'purchase_request_id' => $pr->id,
        ])->assertSessionHas('success', 'ok.proc.orderSaved');
        $po = PurchaseOrder::query()->withoutGlobalScopes()->firstOrFail();
        $this->actAs($this->store)->post(route('procurement.orders.issue', $po->id))->assertSessionHas('success', 'ok.proc.orderIssued');

        $this->actAs($this->admin)->post(route('procurement.invoices.store'), [
            'supplier_id' => $supplier->id, 'purchase_order_id' => $po->id, 'invoice_number' => 'INV-1',
            'invoice_date' => now()->toDateString(), 'amount' => 50,
        ])->assertSessionHas('success', 'ok.proc.invoiceRecorded');
        $inv = SupplierInvoice::query()->withoutGlobalScopes()->firstOrFail();
        $this->actAs($this->admin)->post(route('procurement.invoices.approve', $inv->id))->assertSessionHas('error', 'err.proc.selfApproveInvoice');
        $this->actAs($this->head)->post(route('procurement.invoices.approve', $inv->id))->assertSessionHas('error', 'err.proc.notReceived');

        $lineId = (int) \DB::table('purchase_order_items')->where('purchase_order_id', $po->id)->value('id');
        $this->actAs($this->store)->post(route('procurement.orders.receive', $po->id), [
            'received_on' => now()->toDateString(),
            'lines' => [['item_id' => $lineId, 'quantity' => 10]],
        ])->assertSessionHas('success', 'ok.proc.received');

        $this->actAs($this->store)->post(route('procurement.invoices.approve', $inv->id))->assertForbidden();
        $this->actAs($this->head)->post(route('procurement.invoices.approve', $inv->id))->assertSessionHas('success', 'ok.proc.invoiceApproved');
        $this->actAs($this->admin)->post(route('procurement.invoices.pay', $inv->id), [
            'paid_on' => now()->toDateString(), 'payment_reference' => 'WAVE-99',
        ])->assertSessionHas('success', 'ok.proc.invoicePaid');
        $this->assertSame('paid', $inv->fresh()->status);
    }

    public function test_receiving_stock_items_needs_a_location_and_cannot_over_receive(): void
    {
        $supplier = $this->supplier();
        $this->actAs($this->store)->post(route('inventory.locations.store'), ['name' => 'Goods in']);
        $this->actAs($this->store)->post(route('inventory.items.store'), [
            'name' => 'Chalk', 'sku' => 'CHK-1', 'unit' => 'box', 'reorder_level' => 0,
        ]);
        $loc = InventoryLocation::query()->withoutGlobalScopes()->firstOrFail();
        $item = InventoryItem::query()->withoutGlobalScopes()->firstOrFail();

        $this->actAs($this->store)->post(route('procurement.orders.store'), [
            'supplier_id' => $supplier->id,
            'items' => [['description' => 'Chalk', 'quantity' => 3, 'unit_cost' => 20, 'inventory_item_id' => $item->id]],
        ]);
        $po = PurchaseOrder::query()->withoutGlobalScopes()->firstOrFail();
        $this->actAs($this->store)->post(route('procurement.orders.issue', $po->id));
        $lineId = (int) \DB::table('purchase_order_items')->where('purchase_order_id', $po->id)->value('id');

        $this->actAs($this->store)->post(route('procurement.orders.receive', $po->id), [
            'received_on' => now()->toDateString(),
            'lines' => [['item_id' => $lineId, 'quantity' => 3]],
        ])->assertSessionHas('error', 'err.proc.locationRequired');
        $this->actAs($this->store)->post(route('procurement.orders.receive', $po->id), [
            'received_on' => now()->toDateString(), 'inventory_location_id' => $loc->id,
            'lines' => [['item_id' => $lineId, 'quantity' => 4]],
        ])->assertSessionHas('error', 'err.proc.overReceive');
        $this->actAs($this->store)->post(route('procurement.orders.receive', $po->id), [
            'received_on' => now()->toDateString(), 'inventory_location_id' => $loc->id,
            'lines' => [['item_id' => $lineId, 'quantity' => 3]],
        ])->assertSessionHas('success');
        $this->assertSame(3, (int) \DB::table('inventory_stock')->where('inventory_item_id', $item->id)->sum('quantity'));
        $this->assertSame('received', $po->fresh()->status);
    }

    public function test_foreign_suppliers_and_requests_are_refused(): void
    {
        $other = $this->otherSchool();
        $foreign = $this->inSchool(fn () => Supplier::query()->create(['name' => 'Other Ltd', 'is_active' => true]), $other);
        $this->actAs($this->store)->post(route('procurement.orders.store'), [
            'supplier_id' => $foreign->id,
            'items' => [['description' => 'X', 'quantity' => 1, 'unit_cost' => 1]],
        ])->assertSessionHasErrors('supplier_id');
        $this->assertSame(0, PurchaseOrder::query()->withoutGlobalScopes()->count());
    }
}
