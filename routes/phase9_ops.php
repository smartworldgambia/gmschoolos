<?php

use App\Http\Controllers\Boarding\BoardingController;
use App\Http\Controllers\Events\EventController;
use App\Http\Controllers\Inventory\AssetController;
use App\Http\Controllers\Inventory\InventoryController;
use App\Http\Controllers\Library\LibraryController;
use App\Http\Controllers\Procurement\ProcurementController;
use App\Http\Controllers\Safeguarding\SafeguardingController;
use App\Http\Controllers\Transport\TransportController;
use Illuminate\Support\Facades\Route;

// Phase 9C-9F - school operations. Required from inside the tenant-scoped auth group in web.php.

// ------------------------------------------------------------------ library (9C)
Route::middleware('permission:library.manage')->group(function () {
    Route::get('/library', [LibraryController::class, 'index'])->name('library.index');
    Route::post('/library/books', [LibraryController::class, 'storeBook'])->name('library.books.store');
    Route::get('/library/books/{book}', [LibraryController::class, 'showBook'])->whereNumber('book')->name('library.books.show');
    Route::put('/library/books/{book}', [LibraryController::class, 'updateBook'])->whereNumber('book')->name('library.books.update');
    Route::post('/library/books/{book}/copies', [LibraryController::class, 'addCopies'])->whereNumber('book')->name('library.copies.store');
    Route::put('/library/copies/{copy}', [LibraryController::class, 'setCopyStatus'])->whereNumber('copy')->name('library.copies.status');
    Route::get('/library/loans', [LibraryController::class, 'loans'])->name('library.loans.index');
    Route::post('/library/loans', [LibraryController::class, 'storeLoan'])->middleware('throttle:60,1')->name('library.loans.store');
    Route::post('/library/loans/{loan}/renew', [LibraryController::class, 'renewLoan'])->whereNumber('loan')->name('library.loans.renew');
    Route::post('/library/loans/{loan}/return', [LibraryController::class, 'returnLoan'])->whereNumber('loan')->name('library.loans.return');
    Route::get('/library/overdue.csv', [LibraryController::class, 'exportOverdue'])->middleware('throttle:20,1')->name('library.overdue.export');
    Route::get('/library/fines', [LibraryController::class, 'fines'])->name('library.fines.index');
    Route::post('/library/fines/{fine}/decide', [LibraryController::class, 'decideFine'])->whereNumber('fine')->name('library.fines.decide');
    Route::get('/library/setup', [LibraryController::class, 'setup'])->name('library.setup.index');
    Route::put('/library/setup', [LibraryController::class, 'saveSetup'])->name('library.setup.save');
});

// ------------------------------------------------------------------ inventory and assets (9D)
Route::middleware('permission:inventory.manage')->group(function () {
    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.items.index');
    Route::post('/inventory/items', [InventoryController::class, 'store'])->name('inventory.items.store');
    Route::get('/inventory/items/{item}', [InventoryController::class, 'show'])->whereNumber('item')->name('inventory.items.show');
    Route::put('/inventory/items/{item}', [InventoryController::class, 'update'])->whereNumber('item')->name('inventory.items.update');
    Route::post('/inventory/items/{item}/move', [InventoryController::class, 'move'])->whereNumber('item')->name('inventory.stock.move');
    Route::get('/inventory/stock', [InventoryController::class, 'stock'])->name('inventory.stock.index');
    Route::get('/inventory/setup', [InventoryController::class, 'setup'])->name('inventory.setup.index');
    Route::post('/inventory/locations', [InventoryController::class, 'storeLocation'])->name('inventory.locations.store');
    Route::post('/inventory/categories', [InventoryController::class, 'storeCategory'])->name('inventory.categories.store');
    Route::get('/inventory/assets', [AssetController::class, 'index'])->name('inventory.assets.index');
    Route::post('/inventory/assets', [AssetController::class, 'store'])->name('inventory.assets.store');
    Route::get('/inventory/assets/{asset}', [AssetController::class, 'show'])->whereNumber('asset')->name('inventory.assets.show');
    Route::put('/inventory/assets/{asset}', [AssetController::class, 'update'])->whereNumber('asset')->name('inventory.assets.update');
    Route::post('/inventory/assets/{asset}/assign', [AssetController::class, 'assign'])->whereNumber('asset')->name('inventory.assets.assign');
    Route::post('/inventory/assets/{asset}/return', [AssetController::class, 'returnToStore'])->whereNumber('asset')->name('inventory.assets.return');
    Route::post('/inventory/assets/{asset}/retire', [AssetController::class, 'retire'])->whereNumber('asset')->name('inventory.assets.retire');
    Route::post('/inventory/assets/{asset}/maintenance', [AssetController::class, 'scheduleMaintenance'])->whereNumber('asset')->name('inventory.assets.maintenance.store');
    Route::post('/inventory/maintenance/{maintenance}/complete', [AssetController::class, 'completeMaintenance'])->whereNumber('maintenance')->name('inventory.maintenance.complete');
    Route::post('/inventory/maintenance/{maintenance}/cancel', [AssetController::class, 'cancelMaintenance'])->whereNumber('maintenance')->name('inventory.maintenance.cancel');
});

// ------------------------------------------------------------------ procurement (9D)
Route::get('/procurement/requests', [ProcurementController::class, 'requests'])->middleware('permission:procurement.request|procurement.manage|procurement.approve')->name('procurement.requests.index');
Route::post('/procurement/requests', [ProcurementController::class, 'storeRequest'])->middleware('permission:procurement.request|procurement.manage')->name('procurement.requests.store');
Route::get('/procurement/requests/{purchaseRequest}', [ProcurementController::class, 'showRequest'])->whereNumber('purchaseRequest')->middleware('permission:procurement.request|procurement.manage|procurement.approve')->name('procurement.requests.show');
Route::put('/procurement/requests/{purchaseRequest}', [ProcurementController::class, 'updateRequest'])->whereNumber('purchaseRequest')->middleware('permission:procurement.request|procurement.manage')->name('procurement.requests.update');
Route::post('/procurement/requests/{purchaseRequest}/submit', [ProcurementController::class, 'submitRequest'])->whereNumber('purchaseRequest')->middleware('permission:procurement.request|procurement.manage')->name('procurement.requests.submit');
Route::post('/procurement/requests/{purchaseRequest}/decide', [ProcurementController::class, 'decideRequest'])->whereNumber('purchaseRequest')->middleware('permission:procurement.approve')->name('procurement.requests.decide');
Route::post('/procurement/requests/{purchaseRequest}/cancel', [ProcurementController::class, 'cancelRequest'])->whereNumber('purchaseRequest')->middleware('permission:procurement.manage')->name('procurement.requests.cancel');

Route::middleware('permission:procurement.manage|procurement.approve')->group(function () {
    Route::get('/procurement/orders', [ProcurementController::class, 'orders'])->name('procurement.orders.index');
    Route::get('/procurement/orders/{order}', [ProcurementController::class, 'showOrder'])->whereNumber('order')->name('procurement.orders.show');
    Route::get('/procurement/invoices', [ProcurementController::class, 'invoices'])->name('procurement.invoices.index');
    Route::get('/procurement/reports', [ProcurementController::class, 'reports'])->name('procurement.reports.index');
    Route::get('/procurement/export.csv', [ProcurementController::class, 'export'])->middleware('throttle:20,1')->name('procurement.export');
});
Route::middleware('permission:procurement.manage')->group(function () {
    Route::post('/procurement/orders', [ProcurementController::class, 'storeOrder'])->name('procurement.orders.store');
    Route::post('/procurement/orders/{order}/issue', [ProcurementController::class, 'issueOrder'])->whereNumber('order')->name('procurement.orders.issue');
    Route::post('/procurement/orders/{order}/cancel', [ProcurementController::class, 'cancelOrder'])->whereNumber('order')->name('procurement.orders.cancel');
    Route::post('/procurement/orders/{order}/receive', [ProcurementController::class, 'receive'])->whereNumber('order')->name('procurement.orders.receive');
    Route::post('/procurement/invoices', [ProcurementController::class, 'storeInvoice'])->name('procurement.invoices.store');
    Route::post('/procurement/invoices/{invoice}/pay', [ProcurementController::class, 'payInvoice'])->whereNumber('invoice')->name('procurement.invoices.pay');
    Route::post('/procurement/invoices/{invoice}/cancel', [ProcurementController::class, 'cancelInvoice'])->whereNumber('invoice')->name('procurement.invoices.cancel');
    Route::get('/procurement/suppliers', [ProcurementController::class, 'suppliers'])->name('procurement.suppliers.index');
    Route::post('/procurement/suppliers', [ProcurementController::class, 'storeSupplier'])->name('procurement.suppliers.store');
    Route::put('/procurement/suppliers/{supplier}', [ProcurementController::class, 'updateSupplier'])->whereNumber('supplier')->name('procurement.suppliers.update');
});
Route::post('/procurement/invoices/{invoice}/approve', [ProcurementController::class, 'approveInvoice'])->whereNumber('invoice')->middleware('permission:procurement.approve')->name('procurement.invoices.approve');
Route::get('/procurement/setup', [ProcurementController::class, 'setup'])->middleware('permission:procurement.approve')->name('procurement.setup.index');
Route::put('/procurement/setup', [ProcurementController::class, 'saveSetup'])->middleware('permission:procurement.approve')->name('procurement.setup.save');

// ------------------------------------------------------------------ transport (9E) — keep original store route names
Route::middleware('permission:transport.manage')->group(function () {
    Route::get('/transport', [TransportController::class, 'index'])->name('transport.index');
    Route::post('/transport/vehicles', [TransportController::class, 'storeVehicle'])->name('transport.vehicles.store');
    Route::post('/transport/drivers', [TransportController::class, 'storeDriver'])->name('transport.drivers.store');
    Route::post('/transport/routes', [TransportController::class, 'storeRoute'])->name('transport.routes.store');
    Route::post('/transport/stops', [TransportController::class, 'storeStop'])->name('transport.stops.store');
    Route::post('/transport/assignments', [TransportController::class, 'storeAssignment'])->name('transport.assignments.store');
    Route::post('/transport/assignments/{assignment}/end', [TransportController::class, 'endAssignment'])->whereNumber('assignment')->name('transport.assignments.end');
    Route::post('/transport/maintenance', [TransportController::class, 'storeMaintenance'])->name('transport.maintenance.store');
    Route::get('/transport/attendance', [TransportController::class, 'attendance'])->name('transport.attendance.index');
    Route::post('/transport/attendance', [TransportController::class, 'saveAttendance'])->name('transport.attendance.save');
    Route::post('/transport/bill', [TransportController::class, 'bill'])->name('transport.bill');
});

// ------------------------------------------------------------------ boarding (9E)
Route::middleware('permission:boarding.manage')->group(function () {
    Route::get('/boarding', [BoardingController::class, 'index'])->name('boarding.index');
    Route::post('/boarding/hostels', [BoardingController::class, 'storeHostel'])->name('boarding.hostels.store');
    Route::post('/boarding/rooms', [BoardingController::class, 'storeRoom'])->name('boarding.rooms.store');
    Route::post('/boarding/allocations', [BoardingController::class, 'storeAllocation'])->name('boarding.allocations.store');
    Route::post('/boarding/allocations/{allocation}/checkout', [BoardingController::class, 'checkout'])->whereNumber('allocation')->name('boarding.allocations.checkout');
    Route::get('/boarding/attendance', [BoardingController::class, 'attendance'])->name('boarding.attendance.index');
    Route::post('/boarding/attendance', [BoardingController::class, 'saveAttendance'])->name('boarding.attendance.save');
    Route::get('/boarding/welfare', [BoardingController::class, 'welfare'])->name('boarding.welfare.index');
    Route::post('/boarding/welfare', [BoardingController::class, 'storeWelfare'])->name('boarding.welfare.store');
    Route::post('/boarding/bill', [BoardingController::class, 'bill'])->name('boarding.bill');
});

// ------------------------------------------------------------------ safeguarding (9F) — report is open to reporters; the case file is manage-only
Route::get('/safeguarding/report', [SafeguardingController::class, 'reportForm'])->middleware('permission:safeguarding.report|safeguarding.manage')->name('safeguarding.report');
Route::post('/safeguarding', [SafeguardingController::class, 'store'])->middleware(['permission:safeguarding.report|safeguarding.manage', 'throttle:20,1'])->name('safeguarding.store');
Route::middleware('permission:safeguarding.manage')->group(function () {
    Route::get('/safeguarding', [SafeguardingController::class, 'index'])->name('safeguarding.index');
    Route::get('/safeguarding/{case}', [SafeguardingController::class, 'show'])->whereNumber('case')->name('safeguarding.show');
    Route::post('/safeguarding/{case}/assign', [SafeguardingController::class, 'assign'])->whereNumber('case')->name('safeguarding.assign');
    Route::post('/safeguarding/{case}/notes', [SafeguardingController::class, 'addNote'])->whereNumber('case')->name('safeguarding.notes');
    Route::post('/safeguarding/{case}/close', [SafeguardingController::class, 'close'])->whereNumber('case')->name('safeguarding.close');
});

// ------------------------------------------------------------------ events (9F)
Route::get('/events', [EventController::class, 'index'])->middleware('permission:events.view|events.manage')->name('events.index');
Route::get('/events/{event}', [EventController::class, 'show'])->whereNumber('event')->middleware('permission:events.view|events.manage')->name('events.show');
Route::middleware('permission:events.manage')->group(function () {
    Route::post('/events', [EventController::class, 'store'])->name('events.store');
    Route::put('/events/{event}', [EventController::class, 'update'])->whereNumber('event')->name('events.update');
    Route::post('/events/{event}/publish', [EventController::class, 'publish'])->whereNumber('event')->name('events.publish');
    Route::post('/events/{event}/cancel', [EventController::class, 'cancel'])->whereNumber('event')->name('events.cancel');
    Route::post('/events/{event}/complete', [EventController::class, 'complete'])->whereNumber('event')->name('events.complete');
    Route::post('/events/{event}/register', [EventController::class, 'register'])->whereNumber('event')->name('events.register');
    Route::post('/events/registrations/{registration}/drop', [EventController::class, 'drop'])->whereNumber('registration')->name('events.registrations.drop');
    Route::post('/events/registrations/{registration}/attendance', [EventController::class, 'attendance'])->whereNumber('registration')->name('events.registrations.attendance');
    Route::get('/events-venues', [EventController::class, 'venues'])->name('events.venues.index');
    Route::post('/events-venues', [EventController::class, 'storeVenue'])->name('events.venues.store');
    Route::get('/events-reports', [EventController::class, 'reports'])->name('events.reports.index');
    Route::get('/events-setup', [EventController::class, 'setup'])->name('events.setup.index');
    Route::put('/events-setup', [EventController::class, 'saveSetup'])->name('events.setup.save');
});
