<?php

use App\Http\Controllers\Hr\HrController;
use App\Http\Controllers\Hr\HrSetupController;
use App\Http\Controllers\Hr\LeaveController;
use App\Http\Controllers\Hr\PayrollController;
use App\Http\Controllers\Hr\StaffController;
use Illuminate\Support\Facades\Route;

// Phase 9B - HR, leave, payroll. Required from inside the tenant-scoped auth group in web.php.

Route::get('/hr', [HrController::class, 'index'])->middleware('permission:hr.view')->name('hr.index');

// staff records, contracts, documents, performance
Route::get('/hr/staff', [StaffController::class, 'index'])->middleware('permission:hr.view')->name('hr.staff.index');
Route::post('/hr/staff', [StaffController::class, 'store'])->middleware('permission:hr.manage')->name('hr.staff.store');
Route::get('/hr/staff/{staff}', [StaffController::class, 'show'])->whereNumber('staff')->middleware('permission:hr.view')->name('hr.staff.show');
Route::put('/hr/staff/{staff}', [StaffController::class, 'update'])->whereNumber('staff')->middleware('permission:hr.manage')->name('hr.staff.update');
Route::post('/hr/staff/{staff}/contracts', [StaffController::class, 'storeContract'])->whereNumber('staff')->middleware('permission:payroll.manage')->name('hr.contracts.store');
Route::post('/hr/contracts/{contract}/end', [StaffController::class, 'endContract'])->whereNumber('contract')->middleware('permission:payroll.manage')->name('hr.contracts.end');
Route::post('/hr/staff/{staff}/documents', [StaffController::class, 'storeDocument'])->whereNumber('staff')->middleware(['permission:hr.manage', 'throttle:30,1'])->name('hr.documents.store');
Route::get('/hr/documents/{document}', [StaffController::class, 'downloadDocument'])->whereNumber('document')->middleware('permission:hr.manage')->name('hr.documents.download');
Route::delete('/hr/documents/{document}', [StaffController::class, 'destroyDocument'])->whereNumber('document')->middleware('permission:hr.manage')->name('hr.documents.destroy');
Route::post('/hr/staff/{staff}/performance', [StaffController::class, 'storePerformance'])->whereNumber('staff')->middleware('permission:hr.performance')->name('hr.performance.store');

// leave
Route::get('/hr/leave', [LeaveController::class, 'index'])->middleware('permission:hr.view')->name('hr.leave.index');
Route::post('/hr/leave', [LeaveController::class, 'store'])->middleware('permission:hr.manage')->name('hr.leave.store');
Route::post('/hr/leave/{leave}/review', [LeaveController::class, 'review'])->whereNumber('leave')->middleware('permission:hr.manage')->name('hr.leave.review');
Route::post('/hr/leave/{leave}/cancel', [LeaveController::class, 'cancel'])->whereNumber('leave')->middleware('permission:hr.manage')->name('hr.leave.cancel');

// payroll
Route::get('/hr/payroll', [PayrollController::class, 'index'])->middleware('permission:payroll.manage|payroll.approve')->name('hr.payroll.index');
Route::post('/hr/payroll', [PayrollController::class, 'store'])->middleware('permission:payroll.manage')->name('hr.payroll.store');
Route::get('/hr/payroll/{payroll}', [PayrollController::class, 'show'])->whereNumber('payroll')->middleware('permission:payroll.manage|payroll.approve')->name('hr.payroll.show');
Route::post('/hr/payroll/{payroll}/post', [PayrollController::class, 'post'])->whereNumber('payroll')->middleware('permission:payroll.approve')->name('hr.payroll.post');
Route::post('/hr/payroll/{payroll}/pay', [PayrollController::class, 'pay'])->whereNumber('payroll')->middleware('permission:payroll.manage')->name('hr.payroll.pay');
Route::delete('/hr/payroll/{payroll}', [PayrollController::class, 'destroy'])->whereNumber('payroll')->middleware('permission:payroll.manage')->name('hr.payroll.destroy');
Route::get('/hr/payroll/{payroll}/export', [PayrollController::class, 'export'])->whereNumber('payroll')->middleware(['permission:payroll.manage|payroll.approve', 'throttle:20,1'])->name('hr.payroll.export');
Route::get('/hr/payslips/{item}', [PayrollController::class, 'payslip'])->whereNumber('item')->middleware(['permission:payroll.manage|payroll.approve|staff.self', 'throttle:60,1'])->name('hr.payslips.show');
Route::post('/hr/components', [PayrollController::class, 'storeComponent'])->middleware('permission:payroll.manage')->name('hr.components.store');
Route::put('/hr/components/{component}', [PayrollController::class, 'updateComponent'])->whereNumber('component')->middleware('permission:payroll.manage')->name('hr.components.update');
Route::put('/hr/components/{component}/assignments', [PayrollController::class, 'assignComponent'])->whereNumber('component')->middleware('permission:payroll.manage')->name('hr.components.assign');

// setup
Route::get('/hr/setup', [HrSetupController::class, 'index'])->middleware('permission:hr.manage')->name('hr.setup.index');
Route::post('/hr/setup/positions', [HrSetupController::class, 'storePosition'])->middleware('permission:hr.manage')->name('hr.setup.positions.store');
Route::put('/hr/setup/positions/{position}', [HrSetupController::class, 'updatePosition'])->whereNumber('position')->middleware('permission:hr.manage')->name('hr.setup.positions.update');
Route::put('/hr/setup/settings', [HrSetupController::class, 'saveSettings'])->middleware('permission:hr.manage')->name('hr.setup.settings');

// a staff member's own leave and payslips
Route::get('/my/leave', [LeaveController::class, 'mine'])->middleware('permission:staff.self')->name('my.leave.index');
Route::post('/my/leave', [LeaveController::class, 'applyMine'])->middleware(['permission:staff.self', 'throttle:20,1'])->name('my.leave.store');
Route::post('/my/leave/{leave}/cancel', [LeaveController::class, 'cancelMine'])->whereNumber('leave')->middleware('permission:staff.self')->name('my.leave.cancel');
Route::get('/my/payslips', [PayrollController::class, 'mine'])->middleware('permission:staff.self')->name('my.payslips.index');
