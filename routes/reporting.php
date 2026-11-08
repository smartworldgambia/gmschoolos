<?php

use App\Http\Controllers\Reporting\ReportingController;
use App\Http\Controllers\Reporting\ReportingSettingsController;
use Illuminate\Support\Facades\Route;

Route::prefix('reporting')->name('reporting.')->middleware('permission:exams.view|exams.mark|exams.manage|students.view')->group(function () {
    Route::get('/', [ReportingController::class, 'index'])->name('index');
    Route::get('/termly', [ReportingController::class, 'termly'])->name('termly');
    Route::get('/annual', [ReportingController::class, 'annual'])->name('annual');
    Route::get('/final', [ReportingController::class, 'finalReports'])->name('final');
    Route::get('/transcripts', [ReportingController::class, 'transcripts'])->name('transcripts');
    Route::get('/generate', [ReportingController::class, 'generate'])->name('generate');
    Route::post('/', [ReportingController::class, 'store'])->middleware('permission:exams.mark|exams.manage')->name('store');
    Route::get('/approval', [ReportingController::class, 'approval'])->name('approval');
    Route::get('/register', [ReportingController::class, 'register'])->name('register');
    Route::get('/templates', [ReportingSettingsController::class, 'templates'])->name('templates');
    Route::put('/templates/{template}', [ReportingSettingsController::class, 'saveTemplate'])->whereNumber('template')->name('templates.update');
    Route::get('/grading', [ReportingSettingsController::class, 'grading'])->name('grading');
    Route::post('/grading', [ReportingSettingsController::class, 'saveGrading'])->name('grading.save');

    Route::get('/{report}', [ReportingController::class, 'show'])->whereNumber('report')->name('show');
    Route::get('/{report}/preview', [ReportingController::class, 'preview'])->whereNumber('report')->name('preview');
    Route::get('/{report}/pdf', [ReportingController::class, 'pdf'])->whereNumber('report')->middleware('throttle:30,1')->name('pdf');
    Route::put('/{report}', [ReportingController::class, 'update'])->whereNumber('report')->name('update');
    Route::post('/{report}/submit', [ReportingController::class, 'submit'])->whereNumber('report')->name('submit');
    Route::post('/{report}/review', [ReportingController::class, 'review'])->whereNumber('report')->name('review');
    Route::post('/{report}/approve', [ReportingController::class, 'approve'])->whereNumber('report')->name('approve');
    Route::post('/{report}/return', [ReportingController::class, 'returnToDraft'])->whereNumber('report')->name('return');
    Route::post('/{report}/publish', [ReportingController::class, 'publish'])->whereNumber('report')->name('publish');
    Route::post('/{report}/revise', [ReportingController::class, 'revise'])->whereNumber('report')->name('revise');
});
