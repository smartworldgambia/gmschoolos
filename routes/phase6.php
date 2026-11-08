<?php

use App\Http\Controllers\ExternalExams\CollectionController;
use App\Http\Controllers\ExternalExams\ExternalExamController;
use App\Http\Controllers\ExternalExams\ExternalSubjectController;
use App\Http\Controllers\Records\HistoryController;
use App\Http\Controllers\Records\RecordsController;
use App\Http\Controllers\Records\SettingsController as RecordsSettingsController;
use App\Http\Controllers\Records\TemplateController;
use App\Http\Controllers\SeniorSecondary\ApplicationController as SeniorApplicationController;
use App\Http\Controllers\SeniorSecondary\RoundController;
use Illuminate\Support\Facades\Route;

/*
| Phase 6 - external examinations, Senior Secondary admissions, Records Office.
| Included from routes/web.php inside the authenticated, tenant-bound school group.
*/

// ---------------------------------------------------------------- GABECE / WASSCE
Route::prefix('external-exams')->name('external-exams.')->group(function () {
    Route::middleware('permission:external_exams.view')->group(function () {
        Route::get('/', [ExternalExamController::class, 'index'])->name('index');
        Route::get('/subjects', [ExternalSubjectController::class, 'index'])->name('subjects.index');
        Route::get('/collections', [CollectionController::class, 'index'])->name('collections.index');
        Route::get('/collections/print', [CollectionController::class, 'print'])->name('collections.print');
        Route::get('/results-template', [ExternalExamController::class, 'template'])->name('template');
        Route::get('/sessions/{session}', [ExternalExamController::class, 'show'])->whereNumber('session')->name('sessions.show');
        Route::get('/candidates/{candidate}', [ExternalExamController::class, 'candidate'])->whereNumber('candidate')->name('candidates.show');
        Route::get('/candidates/{candidate}/documents/{document}', [ExternalExamController::class, 'document'])->whereNumber(['candidate', 'document'])->name('documents.show');
    });

    Route::middleware('permission:external_exams.manage')->group(function () {
        Route::post('/sessions', [ExternalExamController::class, 'storeSession'])->name('sessions.store');
        Route::patch('/sessions/{session}', [ExternalExamController::class, 'updateSession'])->whereNumber('session')->name('sessions.update');
        Route::post('/sessions/{session}/register', [ExternalExamController::class, 'register'])->whereNumber('session')->middleware('throttle:30,1')->name('sessions.register');
        Route::post('/sessions/{session}/import', [ExternalExamController::class, 'import'])->whereNumber('session')->middleware('throttle:10,1')->name('sessions.import');

        Route::put('/candidates/{candidate}/subjects', [ExternalExamController::class, 'updateSubjects'])->whereNumber('candidate')->name('candidates.subjects');
        Route::patch('/candidates/{candidate}/status', [ExternalExamController::class, 'updateStatus'])->whereNumber('candidate')->name('candidates.status');
        Route::post('/candidates/{candidate}/index-number', [ExternalExamController::class, 'recordIndex'])->whereNumber('candidate')->name('candidates.index-number');
        Route::post('/candidates/{candidate}/results', [ExternalExamController::class, 'enterResult'])->whereNumber('candidate')->name('candidates.results.store');
        Route::post('/candidates/{candidate}/documents', [ExternalExamController::class, 'storeDocument'])->whereNumber('candidate')->middleware('throttle:30,1')->name('documents.store');
        Route::delete('/candidates/{candidate}/documents/{document}', [ExternalExamController::class, 'destroyDocument'])->whereNumber(['candidate', 'document'])->name('documents.destroy');

        Route::post('/subjects', [ExternalSubjectController::class, 'store'])->name('subjects.store');
        Route::put('/subjects/{subject}', [ExternalSubjectController::class, 'update'])->whereNumber('subject')->name('subjects.update');
        Route::delete('/subjects/{subject}', [ExternalSubjectController::class, 'destroy'])->whereNumber('subject')->name('subjects.destroy');

        Route::post('/collections/{candidate}/received', [CollectionController::class, 'received'])->whereNumber('candidate')->name('collections.received');
        Route::post('/collections/{candidate}/collect', [CollectionController::class, 'collect'])->whereNumber('candidate')->name('collections.collect');
        Route::post('/collections/{candidate}/certificate', [CollectionController::class, 'certificate'])->whereNumber('candidate')->name('collections.certificate');
    });

    Route::middleware('permission:external_exams.verify')->group(function () {
        Route::post('/sessions/{session}/verify', [ExternalExamController::class, 'verifySession'])->whereNumber('session')->name('sessions.verify');
        Route::post('/sessions/{session}/publish', [ExternalExamController::class, 'publishSession'])->whereNumber('session')->name('sessions.publish');
        Route::post('/candidates/{candidate}/verify', [ExternalExamController::class, 'verifyCandidate'])->whereNumber('candidate')->name('candidates.verify');
        Route::post('/results/{result}/correct', [ExternalExamController::class, 'correct'])->whereNumber('result')->name('results.correct');
        Route::post('/subjects/grades', [ExternalSubjectController::class, 'saveGrades'])->name('subjects.grades');
    });
});

// ---------------------------------------------------------------- Senior Secondary admissions
Route::prefix('senior-secondary')->name('senior-secondary.')->group(function () {
    Route::middleware('permission:senior_admissions.view')->group(function () {
        Route::get('/', [RoundController::class, 'index'])->name('rounds.index');
        Route::get('/rounds/{round}', [RoundController::class, 'show'])->whereNumber('round')->name('rounds.show');
        Route::get('/applications/{application}', [SeniorApplicationController::class, 'show'])->whereNumber('application')->name('applications.show');
        Route::get('/applications/{application}/documents/{document}', [SeniorApplicationController::class, 'document'])->whereNumber(['application', 'document'])->name('documents.show');
    });

    Route::middleware('permission:senior_admissions.manage')->group(function () {
        Route::post('/rounds', [RoundController::class, 'store'])->name('rounds.store');
        Route::put('/rounds/{round}', [RoundController::class, 'update'])->whereNumber('round')->name('rounds.update');
        Route::patch('/rounds/{round}/status', [RoundController::class, 'status'])->whereNumber('round')->name('rounds.status');
        Route::post('/rounds/{round}/recompute', [RoundController::class, 'recompute'])->whereNumber('round')->name('rounds.recompute');
        Route::post('/rounds/{round}/apply-recommendations', [RoundController::class, 'apply'])->whereNumber('round')->name('rounds.apply');
        Route::post('/applications/{application}/verify', [SeniorApplicationController::class, 'verify'])->whereNumber('application')->name('applications.verify');
        Route::post('/applications/{application}/decide', [SeniorApplicationController::class, 'decide'])->whereNumber('application')->name('applications.decide');
        Route::post('/applications/{application}/withdraw', [SeniorApplicationController::class, 'withdraw'])->whereNumber('application')->name('applications.withdraw');
        Route::post('/applications/{application}/letter', [SeniorApplicationController::class, 'letter'])->whereNumber('application')->name('applications.letter');
    });
});

// ---------------------------------------------------------------- Records Office
Route::prefix('records')->name('records.')->group(function () {
    Route::middleware('permission:records.manage|senior_admissions.view')->group(function () {
        Route::get('/', [RecordsController::class, 'index'])->name('index');
        Route::get('/documents/{document}', [RecordsController::class, 'show'])->whereNumber('document')->name('show');
        Route::get('/documents/{document}/pdf', [RecordsController::class, 'pdf'])->whereNumber('document')->middleware('throttle:30,1')->name('pdf');
    });

    Route::middleware('permission:records.manage')->group(function () {
        Route::get('/create', [RecordsController::class, 'create'])->name('create');
        Route::post('/', [RecordsController::class, 'store'])->name('store');
        Route::get('/students/{student}/history', [HistoryController::class, 'show'])->whereNumber('student')->name('history.show');
        Route::post('/students/{student}/history', [HistoryController::class, 'store'])->whereNumber('student')->name('history.store');
        Route::delete('/students/{student}/history/{record}', [HistoryController::class, 'destroy'])->whereNumber(['student', 'record'])->name('history.destroy');
    });

    // Service methods apply the exact permission per document type; these only gate who may reach the endpoints.
    Route::middleware('permission:records.manage|records.issue|senior_admissions.manage')->group(function () {
        Route::put('/documents/{document}', [RecordsController::class, 'update'])->whereNumber('document')->name('update');
        Route::post('/documents/{document}/recompile', [RecordsController::class, 'recompile'])->whereNumber('document')->name('recompile');
        Route::post('/documents/{document}/exception', [RecordsController::class, 'exception'])->whereNumber('document')->name('exception');
        Route::post('/documents/{document}/submit', [RecordsController::class, 'submit'])->whereNumber('document')->name('submit');
        Route::post('/documents/{document}/return', [RecordsController::class, 'returnToDraft'])->whereNumber('document')->name('return');
        Route::post('/documents/{document}/approve', [RecordsController::class, 'approve'])->whereNumber('document')->name('approve');
        Route::post('/documents/{document}/issue', [RecordsController::class, 'issue'])->whereNumber('document')->name('issue');
        Route::post('/documents/{document}/reprint', [RecordsController::class, 'reprint'])->whereNumber('document')->name('reprint');
        Route::post('/documents/{document}/revoke', [RecordsController::class, 'revoke'])->whereNumber('document')->name('revoke');
        Route::get('/templates', [TemplateController::class, 'index'])->name('templates.index');
        Route::post('/templates', [TemplateController::class, 'store'])->name('templates.store');
        Route::put('/templates/{template}', [TemplateController::class, 'update'])->whereNumber('template')->name('templates.update');
    });

    Route::middleware('permission:records.issue')->group(function () {
        Route::get('/settings', [RecordsSettingsController::class, 'edit'])->name('settings.edit');
        Route::put('/settings', [RecordsSettingsController::class, 'update'])->name('settings.update');
        Route::put('/settings/levels', [RecordsSettingsController::class, 'levels'])->name('settings.levels');
        Route::post('/settings/stamp', [RecordsSettingsController::class, 'stamp'])->middleware('throttle:10,1')->name('settings.stamp');
        Route::delete('/settings/stamp', [RecordsSettingsController::class, 'destroyStamp'])->name('settings.stamp.destroy');
    });
});
