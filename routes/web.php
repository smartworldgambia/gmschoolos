<?php

use App\Http\Controllers\Academics\AcademicController;
use App\Http\Controllers\Academics\CalendarController;
use App\Http\Controllers\Academics\CurriculumController;
use App\Http\Controllers\Academics\PromotionController;
use App\Http\Controllers\Academics\TimetableController;
use App\Http\Controllers\Attendance\AttendanceController;
use App\Http\Controllers\Examinations\AssessmentSettingsController;
use App\Http\Controllers\School\PublicApplicationController;
use App\Http\Controllers\Admissions\AdmissionController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Examinations\ExaminationController;
use App\Http\Controllers\Fees\FeeController;
use App\Http\Controllers\Fees\ReconciliationController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Exports\ExportController;
use App\Http\Controllers\Lms\CourseController;
use App\Http\Controllers\Lms\ExternalContentController;
use App\Http\Controllers\Lms\LessonController;
use App\Http\Controllers\Lms\QuizAttemptController;
use App\Http\Controllers\Marketing\LegalController;
use App\Http\Controllers\Notifications\NotificationController;
use App\Http\Controllers\Onboarding\RegisterSchoolController;
use App\Http\Controllers\Onboarding\StaffInvitationController;
use App\Http\Controllers\Platform\RegistrationReviewController as PlatformRegistrationReviewController;
use App\Http\Controllers\School\SetupController;
use App\Http\Controllers\ParentPortal\DashboardController as ParentDashboardController;
use App\Http\Controllers\MetricsController;
use App\Http\Controllers\Platform\BackupController as PlatformBackupController;
use App\Http\Controllers\Platform\CapacityController as PlatformCapacityController;
use App\Http\Controllers\Platform\DashboardController as PlatformDashboardController;
use App\Http\Controllers\Platform\MarketingController as PlatformMarketingController;
use App\Http\Controllers\Platform\MonitoringController as PlatformMonitoringController;
use App\Http\Controllers\Platform\OpsController as PlatformOpsController;
use App\Http\Controllers\Billing\BillingController as SchoolBillingController;
use App\Http\Controllers\Platform\BillingController as PlatformBillingController;
use App\Http\Controllers\Billing\PaymentController as SchoolPaymentController;
use App\Http\Controllers\Platform\PaymentController as PlatformPaymentController;
use App\Http\Controllers\Platform\IntegrationController as PlatformIntegrationController;
use App\Http\Controllers\Platform\PaymentSettingsController as PlatformPaymentSettingsController;
use App\Http\Controllers\Platform\PricingController as PlatformPricingController;
use App\Http\Controllers\Platform\ScaleController as PlatformScaleController;
use App\Http\Controllers\Platform\SchoolController as PlatformSchoolController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Reports\ReportController;
use App\Http\Controllers\Records\VerificationController;
use App\Http\Controllers\School\PublicContactController;
use App\Http\Controllers\School\PublicSeniorApplicationController;
use App\Http\Controllers\School\PublicSiteController;
use App\Http\Controllers\School\WebsiteController;
use App\Http\Controllers\Student\DashboardController as StudentDashboardController;
use App\Http\Controllers\Students\StudentController;
use App\Http\Controllers\Students\StudentImportController;
use App\Http\Controllers\Teacher\DashboardController as TeacherDashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class)->name('health');
Route::get('/ready', [HealthController::class, 'ready'])->name('ready');
Route::get('/metrics', MetricsController::class)->middleware(['throttle:60,1', 'metrics.token'])->name('metrics');

Route::middleware(['identify.tenant'])->group(function () {
    Route::get('/', HomeController::class)->name('home');
    Route::get('/privacy', [LegalController::class, 'privacy'])->name('privacy');
    Route::get('/terms', [LegalController::class, 'terms'])->name('terms');
    Route::get('/sites/{school_slug}', PublicSiteController::class)->name('school.site');
    Route::post('/sites/{school_slug}/contact', [PublicContactController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('school.contact');
    // Phase 5 - public online admission application (creates a reviewable application only)
    Route::get('/sites/{school_slug}/apply', [PublicApplicationController::class, 'create'])->name('school.apply');
    Route::post('/sites/{school_slug}/apply', [PublicApplicationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('school.apply.store');

    // Phase 6 - public Senior Secondary application (creates a reviewable application only)
    Route::get('/sites/{school_slug}/senior-secondary/{round_slug?}', [PublicSeniorApplicationController::class, 'create'])->name('school.senior.apply');
    Route::post('/sites/{school_slug}/senior-secondary/{round_slug}', [PublicSeniorApplicationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('school.senior.apply.store');

    // Phase 6 - public document verification (minimal disclosure)
    Route::get('/verify/{token}', [VerificationController::class, 'show'])
        ->where('token', '[a-z0-9]{32}')->middleware('throttle:'.config('schoolos.records.verify_throttle'))->name('records.verify');

    Route::middleware('guest')->group(function () {
        Route::get('/register-school', [RegisterSchoolController::class, 'create'])->name('register-school.create');
        Route::get('/register-school/subdomain-check', [RegisterSchoolController::class, 'checkSubdomain'])
            ->middleware('throttle:60,1')
            ->name('register-school.subdomain-check');
        Route::post('/register-school', [RegisterSchoolController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('register-school.store');
    });

    // Invitation links are opened from email, often in a browser already signed in as the school admin.
    // They must not sit behind `guest`, or the admin is bounced to User accounts and the invite stays pending.
    Route::get('/invitations/{token}', [StaffInvitationController::class, 'show'])
        ->where('token', '[A-Za-z0-9]{64}')->middleware('throttle:30,1')->name('invitations.show');
    Route::post('/invitations/{token}', [StaffInvitationController::class, 'accept'])
        ->where('token', '[A-Za-z0-9]{64}')->middleware('throttle:10,1')->name('invitations.accept');
});

Route::middleware(['auth', 'verified', 'platform.admin'])->prefix('platform')->name('platform.')->group(function () {
    Route::get('/', PlatformDashboardController::class)->name('dashboard');

    Route::get('/schools', [PlatformSchoolController::class, 'index'])->middleware('permission:platform.schools.view')->name('schools.index');
    Route::post('/schools', [PlatformSchoolController::class, 'store'])->middleware('permission:platform.schools.manage')->name('schools.store');
    Route::get('/schools/{school}', [PlatformSchoolController::class, 'show'])->middleware('permission:platform.schools.view')->name('schools.show');
    Route::patch('/schools/{school}', [PlatformSchoolController::class, 'update'])->middleware('permission:platform.schools.manage')->name('schools.update');
    Route::post('/schools/{school}/payments', [PlatformBillingController::class, 'recordPayment'])->middleware('permission:platform.payments.verify')->name('schools.payments.store');
    Route::patch('/schools/{school}/type', [PlatformBillingController::class, 'updateType'])->middleware('permission:platform.subscriptions.manage')->name('schools.type.update');
    Route::patch('/schools/{school}/quota', [PlatformSchoolController::class, 'updateQuota'])->middleware('permission:platform.schools.manage')->name('schools.quota');
    Route::post('/schools/{school}/domains', [PlatformSchoolController::class, 'storeDomain'])->middleware('permission:platform.schools.manage')->name('schools.domains.store');
    Route::post('/schools/{school}/suspend', [PlatformSchoolController::class, 'suspend'])->middleware('permission:platform.schools.manage')->name('schools.suspend');
    Route::post('/schools/{school}/unsuspend', [PlatformSchoolController::class, 'unsuspend'])->middleware('permission:platform.schools.manage')->name('schools.unsuspend');

    // Phase 4 - registrations that resembled a known institution (trial-abuse review queue)
    Route::get('/registrations', [PlatformRegistrationReviewController::class, 'index'])->middleware('permission:platform.schools.view')->name('registrations.index');
    Route::post('/registrations/{review}/clear', [PlatformRegistrationReviewController::class, 'clear'])->whereNumber('review')->middleware('permission:platform.schools.manage')->name('registrations.clear');
    Route::post('/registrations/{review}/confirm', [PlatformRegistrationReviewController::class, 'confirm'])->whereNumber('review')->middleware('permission:platform.schools.manage')->name('registrations.confirm');

    Route::get('/pricing', [PlatformPricingController::class, 'index'])->middleware('permission:platform.pricing.manage')->name('pricing.index');
    Route::post('/pricing', [PlatformPricingController::class, 'store'])->middleware('permission:platform.pricing.manage')->name('pricing.store');

    Route::get('/billing', [PlatformBillingController::class, 'index'])->middleware('permission:platform.finance.view')->name('billing.index');
    Route::get('/billing/export', [PlatformBillingController::class, 'export'])->middleware('permission:platform.finance.view')->name('billing.export');
    // Phase 3 - payments (cash / bank / Wave / Stripe): queue, confirmation, reconciliation, settings
    Route::get('/payments', [PlatformPaymentController::class, 'index'])->middleware('permission:platform.finance.view')->name('payments.index');
    Route::get('/payments/export', [PlatformPaymentController::class, 'export'])->middleware('permission:platform.finance.view')->name('payments.export');
    Route::get('/payments/reconciliation', [PlatformPaymentController::class, 'reconciliation'])->middleware('permission:platform.finance.view')->name('payments.reconciliation');
    Route::post('/payments/reconciliation/run', [PlatformPaymentController::class, 'reconcile'])->middleware('permission:platform.payments.verify')->name('payments.reconcile');
    Route::get('/payments/settings', [PlatformPaymentSettingsController::class, 'index'])->middleware('permission:platform.finance.view')->name('payments.settings');
    Route::post('/payments/settings/banks', [PlatformPaymentSettingsController::class, 'storeBank'])->middleware('permission:platform.payments.configure')->name('payments.banks.store');
    Route::put('/payments/settings/banks/{account}', [PlatformPaymentSettingsController::class, 'updateBank'])->middleware('permission:platform.payments.configure')->name('payments.banks.update');
    Route::post('/payments/settings/banks/{account}/toggle', [PlatformPaymentSettingsController::class, 'toggleBank'])->middleware('permission:platform.payments.configure')->name('payments.banks.toggle');
    Route::get('/payments/{paymentId}', [PlatformPaymentController::class, 'show'])->whereNumber('paymentId')->middleware('permission:platform.finance.view')->name('payments.show');
    Route::post('/payments/{paymentId}/cash', [PlatformPaymentController::class, 'confirmCash'])->whereNumber('paymentId')->middleware('permission:platform.payments.verify')->name('payments.cash.confirm');
    Route::post('/payments/{paymentId}/bank', [PlatformPaymentController::class, 'approveBank'])->whereNumber('paymentId')->middleware('permission:platform.payments.verify')->name('payments.bank.approve');
    Route::post('/payments/{paymentId}/reject', [PlatformPaymentController::class, 'reject'])->whereNumber('paymentId')->middleware('permission:platform.payments.verify')->name('payments.reject');
    Route::post('/payments/{paymentId}/recheck', [PlatformPaymentController::class, 'recheck'])->whereNumber('paymentId')->middleware('permission:platform.payments.verify')->name('payments.recheck');
    Route::get('/payments/{paymentId}/evidence', [PlatformPaymentController::class, 'evidence'])->whereNumber('paymentId')->middleware('permission:platform.finance.view')->name('payments.evidence');
    Route::get('/payments/{paymentId}/receipt', [PlatformPaymentController::class, 'receipt'])->whereNumber('paymentId')->middleware('permission:platform.finance.view')->name('payments.receipt');

    // Platform-wide mail (SMTP) and SMS provider credentials. Own permission; secrets are write-only; changes re-ask the password.
    Route::middleware('permission:platform.integrations.configure')->prefix('/integrations')->name('integrations.')->where(['integration' => 'mail|sms'])->group(function () {
        Route::get('/', [PlatformIntegrationController::class, 'index'])->name('index');
        Route::put('/{integration}', [PlatformIntegrationController::class, 'save'])->middleware('throttle:20,1')->name('save');
        Route::post('/{integration}/test', [PlatformIntegrationController::class, 'test'])->middleware('throttle:6,1')->name('test');
        Route::post('/{integration}/activate', [PlatformIntegrationController::class, 'activate'])->middleware('throttle:20,1')->name('activate');
        Route::post('/{integration}/deactivate', [PlatformIntegrationController::class, 'deactivate'])->middleware('throttle:20,1')->name('deactivate');
        Route::post('/{integration}/confirm', [PlatformIntegrationController::class, 'confirm'])->name('confirm');
        Route::delete('/{integration}', [PlatformIntegrationController::class, 'destroy'])->middleware('throttle:20,1')->name('destroy');
    });

    Route::get('/marketing', [PlatformMarketingController::class, 'edit'])->middleware('permission:platform.marketing.manage')->name('marketing.edit');
    Route::put('/marketing', [PlatformMarketingController::class, 'update'])->middleware('permission:platform.marketing.manage')->name('marketing.update');

    Route::get('/monitoring', [PlatformMonitoringController::class, 'index'])->middleware('permission:platform.operations.view')->name('monitoring.index');
    Route::post('/alerts', [PlatformMonitoringController::class, 'storeTestAlert'])->middleware('permission:platform.operations.manage')->name('alerts.store');
    Route::post('/alerts/{alert}/acknowledge', [PlatformMonitoringController::class, 'acknowledge'])->middleware('permission:platform.operations.manage')->name('alerts.acknowledge');
    Route::post('/alerts/{alert}/resolve', [PlatformMonitoringController::class, 'resolve'])->middleware('permission:platform.operations.manage')->name('alerts.resolve');

    Route::get('/ops', PlatformOpsController::class)->middleware('permission:platform.operations.view')->name('ops.index');

    Route::get('/backups', [PlatformBackupController::class, 'index'])->middleware('permission:platform.operations.view')->name('backups.index');
    Route::post('/backups/run', [PlatformBackupController::class, 'run'])->middleware('permission:platform.operations.manage')->name('backups.run');

    Route::get('/scale', [PlatformScaleController::class, 'index'])->middleware('permission:platform.operations.view')->name('scale.index');
    Route::post('/scale/cache-warm', [PlatformScaleController::class, 'warmCache'])->middleware('permission:platform.operations.manage')->name('scale.cache-warm');

    Route::get('/capacity', [PlatformCapacityController::class, 'index'])->middleware('permission:platform.operations.view')->name('capacity.index');
    Route::post('/capacity/remodel', [PlatformCapacityController::class, 'remodel'])->middleware('permission:platform.operations.manage')->name('capacity.remodel');
    Route::post('/capacity/record', [PlatformCapacityController::class, 'record'])->middleware('permission:platform.operations.manage')->name('capacity.record');
    Route::post('/capacity/isolation', [PlatformCapacityController::class, 'updateIsolation'])->middleware('permission:platform.operations.manage')->name('capacity.isolation');
});

Route::middleware(['auth', 'verified', 'identify.tenant', 'school.context'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/admin', AdminDashboardController::class)->middleware('permission:portal.admin')->name('admin.dashboard');
    Route::get('/website', [WebsiteController::class, 'edit'])->middleware('permission:website.manage')->name('website.edit');
    Route::put('/website', [WebsiteController::class, 'update'])->middleware('permission:website.manage')->name('website.update');
    Route::post('/website/news', [WebsiteController::class, 'storeNews'])->middleware('permission:website.manage')->name('website.news.store');
    Route::put('/website/news/{news}', [WebsiteController::class, 'updateNews'])->middleware('permission:website.manage')->name('website.news.update');
    Route::delete('/website/news/{news}', [WebsiteController::class, 'destroyNews'])->middleware('permission:website.manage')->name('website.news.destroy');

    Route::get('/settings/domain', [\App\Http\Controllers\School\DomainController::class, 'edit'])->middleware('permission:domains.manage')->name('school.domains.edit');
    Route::post('/settings/domain', [\App\Http\Controllers\School\DomainController::class, 'store'])->middleware('permission:domains.manage')->name('school.domains.store');
    Route::post('/settings/domain/{domain}/verify', [\App\Http\Controllers\School\DomainController::class, 'verify'])
        ->middleware('throttle:20,1')
        ->middleware('permission:domains.manage')->name('school.domains.verify');
    Route::post('/settings/domain/{domain}/primary', [\App\Http\Controllers\School\DomainController::class, 'makePrimary'])->middleware('permission:domains.manage')->name('school.domains.primary');
    Route::delete('/settings/domain/{domain}', [\App\Http\Controllers\School\DomainController::class, 'destroy'])->middleware('permission:domains.manage')->name('school.domains.destroy');
    // Phase 4 - school setup wizard
    Route::middleware('permission:school.settings.manage')->prefix('setup')->name('setup.')->group(function () {
        Route::get('/', [SetupController::class, 'index'])->name('index');
        Route::post('/profile', [SetupController::class, 'profile'])->name('profile');
        Route::post('/branding', [SetupController::class, 'branding'])->name('branding');
        Route::post('/school-type', [SetupController::class, 'confirmType'])->name('type');
        Route::post('/levels', [SetupController::class, 'storeLevel'])->name('levels.store');
        Route::delete('/levels/{level}', [SetupController::class, 'destroyLevel'])->whereNumber('level')->name('levels.destroy');
        Route::post('/academic-year', [SetupController::class, 'saveYear'])->name('year');
        Route::post('/departments', [SetupController::class, 'storeDepartment'])->name('departments.store');
        Route::delete('/departments/{department}', [SetupController::class, 'destroyDepartment'])->whereNumber('department')->name('departments.destroy');
        Route::post('/classes', [SetupController::class, 'storeClass'])->name('classes.store');
        Route::delete('/classes/{class}', [SetupController::class, 'destroyClass'])->whereNumber('class')->name('classes.destroy');
        Route::post('/subjects', [SetupController::class, 'storeSubject'])->name('subjects.store');
        Route::delete('/subjects/{subject}', [SetupController::class, 'destroySubject'])->whereNumber('subject')->name('subjects.destroy');
        Route::post('/invitations', [SetupController::class, 'invite'])->middleware('throttle:20,1')->name('invitations.store');
        Route::post('/invitations/{invitation}/resend', [SetupController::class, 'resendInvitation'])->whereNumber('invitation')->middleware('throttle:20,1')->name('invitations.resend');
        Route::delete('/invitations/{invitation}', [SetupController::class, 'revokeInvitation'])->whereNumber('invitation')->name('invitations.destroy');
        Route::post('/parents', [SetupController::class, 'parents'])->name('parents');
        Route::post('/website', [SetupController::class, 'website'])->name('website');
        Route::post('/steps/{step}/done', [SetupController::class, 'done'])->name('steps.done');
        Route::post('/steps/{step}/skip', [SetupController::class, 'skip'])->name('steps.skip');
        Route::post('/steps/{step}/reopen', [SetupController::class, 'reopen'])->name('steps.reopen');
        Route::post('/complete', [SetupController::class, 'complete'])->name('complete');
    });

    Route::get('/teacher', TeacherDashboardController::class)->middleware('permission:portal.teacher')->name('teacher.dashboard');
    Route::get('/student', StudentDashboardController::class)->middleware('permission:portal.student')->name('student.dashboard');
    Route::get('/parent', ParentDashboardController::class)->middleware('permission:portal.parent')->name('parent.dashboard');

    Route::get('/admissions', [AdmissionController::class, 'index'])->middleware('permission:admissions.view')->name('admissions.index');
    Route::get('/admissions/create', [AdmissionController::class, 'create'])->middleware('permission:admissions.manage')->name('admissions.create');
    Route::post('/admissions', [AdmissionController::class, 'store'])->middleware('permission:admissions.manage')->name('admissions.store');
    Route::get('/admissions/{admission}', [AdmissionController::class, 'show'])->whereNumber('admission')->middleware('permission:admissions.view')->name('admissions.show');
    Route::get('/admissions/{admission}/documents/{document}', [AdmissionController::class, 'document'])->whereNumber(['admission', 'document'])->middleware('permission:admissions.view')->name('admissions.documents.show');
    Route::patch('/admissions/{admission}/status', [AdmissionController::class, 'updateStatus'])->whereNumber('admission')->middleware('permission:admissions.manage')->name('admissions.status');

    Route::get('/students', [StudentController::class, 'index'])->middleware('permission:students.view')->name('students.index');
    Route::get('/students/create', [StudentController::class, 'create'])->middleware('permission:students.create')->name('students.create');
    Route::post('/students', [StudentController::class, 'store'])->middleware('permission:students.create')->name('students.store');
    Route::get('/students/import', [StudentImportController::class, 'index'])->middleware('permission:students.import')->name('students.import.index');
    Route::get('/students/import/template', [StudentImportController::class, 'template'])->middleware('permission:students.import')->name('students.import.template');
    Route::post('/students/import', [StudentImportController::class, 'store'])->middleware('permission:students.import')->name('students.import.store');
    Route::get('/students/import/{batch}/status', [StudentImportController::class, 'status'])->middleware('permission:students.import')->name('students.import.status');
    Route::get('/students/{student}', [StudentController::class, 'show'])->whereNumber('student')->middleware('permission:students.view')->name('students.show');
    Route::get('/students/{student}/edit', [StudentController::class, 'edit'])->whereNumber('student')->middleware('permission:students.update')->name('students.edit');
    Route::put('/students/{student}', [StudentController::class, 'update'])->whereNumber('student')->middleware('permission:students.update')->name('students.update');
    Route::get('/students/{student}/photo', [StudentController::class, 'photo'])->whereNumber('student')->middleware('permission:students.view')->name('students.photo');
    Route::post('/students/{student}/documents', [StudentController::class, 'storeDocument'])->whereNumber('student')->middleware(['permission:students.update', 'throttle:30,1'])->name('students.documents.store');
    Route::get('/students/{student}/documents/{document}', [StudentController::class, 'showDocument'])->whereNumber(['student', 'document'])->middleware('permission:students.view')->name('students.documents.show');
    Route::delete('/students/{student}/documents/{document}', [StudentController::class, 'destroyDocument'])->whereNumber(['student', 'document'])->middleware('permission:students.update')->name('students.documents.destroy');
    Route::post('/students/{student}/move', [StudentController::class, 'move'])->whereNumber('student')->middleware('permission:academics.manage')->name('students.move');
    Route::get('/students/{student}/report-card', [StudentController::class, 'reportCard'])->whereNumber('student')->middleware('permission:students.view')->name('students.report-card');

    Route::get('/exports', [ExportController::class, 'index'])->middleware('permission:reports.export')->name('exports.index');
    Route::post('/exports', [ExportController::class, 'store'])->middleware('permission:reports.export')->name('exports.store');
    Route::get('/exports/{export}/status', [ExportController::class, 'status'])->middleware('permission:reports.export')->name('exports.status');
    Route::get('/exports/{export}/download', [ExportController::class, 'download'])->middleware('permission:reports.export')->name('exports.download');

    Route::get('/billing', [SchoolBillingController::class, 'index'])->middleware('permission:subscriptions.view')->name('billing.index');
    Route::post('/billing/payments', [SchoolPaymentController::class, 'store'])->middleware(['permission:subscriptions.manage', 'throttle:20,1'])->name('billing.payments.store');
    Route::get('/billing/payments/{reference}', [SchoolPaymentController::class, 'show'])->middleware('permission:subscriptions.view')->name('billing.payments.show');
    Route::post('/billing/payments/{reference}/evidence', [SchoolPaymentController::class, 'evidence'])->middleware(['permission:subscriptions.manage', 'throttle:10,1'])->name('billing.payments.evidence');
    Route::post('/billing/payments/{reference}/cancel', [SchoolPaymentController::class, 'cancel'])->middleware('permission:subscriptions.manage')->name('billing.payments.cancel');
    Route::get('/billing/payments/{reference}/return', [SchoolPaymentController::class, 'returned'])->middleware('permission:subscriptions.view')->name('billing.payments.return');
    Route::post('/billing/payments/{reference}/refresh', [SchoolPaymentController::class, 'refresh'])->middleware(['permission:subscriptions.view', 'throttle:30,1'])->name('billing.payments.refresh');
    Route::get('/billing/payments/{reference}/receipt', [SchoolPaymentController::class, 'receipt'])->middleware('permission:subscriptions.view')->name('billing.payments.receipt');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::get('/notifications/preferences', [NotificationController::class, 'preferences'])->name('notifications.preferences');
    Route::post('/notifications/preferences', [NotificationController::class, 'savePreferences'])->name('notifications.preferences.save');
    Route::post('/notifications/phone', [NotificationController::class, 'savePhone'])->middleware('throttle:20,1')->name('notifications.phone');

    // Phase 8 - announcements (everyone reads their feed; authors are limited by permission inside the service)
    Route::get('/announcements', [\App\Http\Controllers\Communications\AnnouncementController::class, 'index'])->name('announcements.index');
    Route::get('/announcements/create', [\App\Http\Controllers\Communications\AnnouncementController::class, 'create'])->name('announcements.create');
    Route::post('/announcements', [\App\Http\Controllers\Communications\AnnouncementController::class, 'store'])->middleware('throttle:30,1')->name('announcements.store');
    Route::get('/announcements/{announcement}/edit', [\App\Http\Controllers\Communications\AnnouncementController::class, 'edit'])->whereNumber('announcement')->name('announcements.edit');
    Route::put('/announcements/{announcement}', [\App\Http\Controllers\Communications\AnnouncementController::class, 'update'])->whereNumber('announcement')->middleware('throttle:30,1')->name('announcements.update');
    Route::post('/announcements/{announcement}/cancel', [\App\Http\Controllers\Communications\AnnouncementController::class, 'cancel'])->whereNumber('announcement')->name('announcements.cancel');

    // Phase 8 - internal messaging
    Route::middleware('permission:messages.use')->prefix('messages')->name('messages.')->group(function () {
        $c = \App\Http\Controllers\Messaging\MessageController::class;
        Route::get('/', [$c, 'index'])->name('index');
        Route::get('/new', [$c, 'create'])->name('create');
        Route::post('/', [$c, 'store'])->middleware('throttle:30,1')->name('store');
        Route::post('/group', [$c, 'storeGroup'])->middleware('throttle:10,1')->name('group.store');
        Route::get('/attachments/{attachment}', [$c, 'attachment'])->whereNumber('attachment')->name('attachments.show');
        Route::get('/moderation', [$c, 'moderation'])->middleware('permission:messages.moderate')->name('moderation');
        Route::post('/moderation/messages/{message}/hide', [$c, 'hide'])->whereNumber('message')->middleware('permission:messages.moderate')->name('hide');
        Route::post('/moderation/reports/{report}/dismiss', [$c, 'dismiss'])->whereNumber('report')->middleware('permission:messages.moderate')->name('dismiss');
        Route::post('/report/{message}', [$c, 'report'])->whereNumber('message')->middleware('throttle:20,1')->name('report');
        Route::get('/{conversation}', [$c, 'show'])->whereNumber('conversation')->name('show');
        Route::post('/{conversation}/reply', [$c, 'reply'])->whereNumber('conversation')->middleware('throttle:60,1')->name('reply');
        Route::post('/{conversation}/mute', [$c, 'mute'])->whereNumber('conversation')->name('mute');
        Route::post('/{conversation}/lock', [$c, 'lock'])->whereNumber('conversation')->name('lock');
        Route::post('/{conversation}/unlock', [$c, 'unlock'])->whereNumber('conversation')->name('unlock');
    });

    // Phase 8 - account provisioning and lifecycle (administrators)
    Route::middleware('permission:users.manage')->prefix('accounts')->name('accounts.')->group(function () {
        $c = \App\Http\Controllers\School\AccountsController::class;
        Route::get('/', [$c, 'index'])->name('index');
        Route::post('/invite/staff', [$c, 'inviteStaff'])->middleware('throttle:20,1')->name('invite.staff');
        Route::post('/invite/student', [$c, 'inviteStudent'])->middleware('throttle:20,1')->name('invite.student');
        Route::post('/invite/guardian', [$c, 'inviteGuardian'])->middleware('throttle:20,1')->name('invite.guardian');
        Route::post('/invitations/{invitation}/resend', [$c, 'resend'])->whereNumber('invitation')->middleware('throttle:20,1')->name('invitations.resend');
        Route::delete('/invitations/{invitation}', [$c, 'revokeInvitation'])->whereNumber('invitation')->name('invitations.destroy');
        Route::post('/{user}/suspend', [$c, 'suspend'])->whereNumber('user')->name('suspend');
        Route::post('/{user}/reinstate', [$c, 'reinstate'])->whereNumber('user')->name('reinstate');
        Route::post('/{user}/revoke', [$c, 'revokeAccess'])->whereNumber('user')->name('revoke');
        Route::post('/{user}/restore', [$c, 'restoreAccess'])->whereNumber('user')->name('restore');
        Route::post('/{user}/reset', [$c, 'sendReset'])->whereNumber('user')->middleware('throttle:20,1')->name('reset');
    });

    // Phase 8 - communications centre (SMS, templates, delivery history)
    Route::middleware('permission:communications.manage')->prefix('communications')->name('communications.')->group(function () {
        $c = \App\Http\Controllers\Communications\CommunicationsController::class;
        Route::get('/', [$c, 'index'])->name('index');
        Route::post('/settings', [$c, 'saveSettings'])->name('settings');
        Route::post('/templates', [$c, 'saveTemplate'])->name('templates');
        Route::post('/sms/test', [$c, 'test'])->middleware('throttle:5,1')->name('sms.test');
        Route::post('/sms/{message}/retry', [$c, 'retry'])->whereNumber('message')->middleware('throttle:20,1')->name('sms.retry');
    });

    // Phase 9A - school fees and finance (parents pay the SCHOOL; the SaaS subscription lives under /billing)
    Route::prefix('fees')->name('fees.')->group(function () {
        $inv = \App\Http\Controllers\Fees\InvoiceController::class;
        $pay = \App\Http\Controllers\Fees\PaymentController::class;
        $adj = \App\Http\Controllers\Fees\FeeAdjustmentController::class;
        $set = \App\Http\Controllers\Fees\FeeSetupController::class;
        $rep = \App\Http\Controllers\Fees\FinanceReportController::class;

        Route::get('/', [FeeController::class, 'index'])->middleware('permission:fees.view')->name('index');

        // invoices
        Route::get('/invoices', [$inv, 'index'])->middleware('permission:fees.manage|payments.record|payments.verify|fees.approve')->name('invoices.index');
        Route::get('/invoices/{invoice}', [$inv, 'show'])->whereNumber('invoice')->middleware('permission:fees.view')->name('invoices.show');
        Route::post('/invoices/generate', [$inv, 'generate'])->middleware(['permission:fees.manage', 'throttle:20,1'])->name('invoices.generate');
        Route::post('/invoices', [$inv, 'store'])->middleware(['permission:fees.manage', 'throttle:60,1'])->name('invoices.store');
        Route::post('/invoices/{invoice}/cancel', [$inv, 'cancel'])->whereNumber('invoice')->middleware('permission:fees.approve')->name('invoices.cancel');
        Route::post('/invoices/{invoice}/installments', [$inv, 'installments'])->whereNumber('invoice')->middleware('permission:fees.manage')->name('invoices.installments');
        Route::delete('/invoices/{invoice}/installments', [$inv, 'clearInstallments'])->whereNumber('invoice')->middleware('permission:fees.manage')->name('invoices.installments.clear');
        Route::get('/statements/{student}', [$inv, 'statement'])->whereNumber('student')->middleware('permission:fees.view')->name('statements.show');

        // payments
        Route::get('/payments', [$pay, 'index'])->middleware('permission:payments.verify|payments.record')->name('payments.index');
        Route::post('/payments', [$pay, 'store'])->middleware(['permission:payments.record', 'throttle:60,1'])->name('payments.store');
        Route::post('/invoices/{invoice}/submissions', [$pay, 'submit'])->whereNumber('invoice')->middleware(['permission:fees.view', 'throttle:10,1'])->name('submissions.store');
        Route::post('/payments/{payment}/confirm', [$pay, 'confirm'])->whereNumber('payment')->middleware('permission:payments.verify')->name('payments.confirm');
        Route::post('/payments/{payment}/reject', [$pay, 'reject'])->whereNumber('payment')->middleware('permission:payments.verify')->name('payments.reject');
        Route::post('/payments/{payment}/void', [$pay, 'void'])->whereNumber('payment')->middleware('permission:fees.approve')->name('payments.void');
        Route::get('/payments/{payment}/receipt', [$pay, 'receipt'])->whereNumber('payment')->middleware('permission:fees.view')->name('payments.receipt');
        Route::get('/payments/{payment}/evidence', [$pay, 'evidence'])->whereNumber('payment')->middleware('permission:fees.view')->name('payments.evidence');

        // reconciliation
        Route::get('/reconciliation', [ReconciliationController::class, 'index'])->middleware('permission:payments.verify')->name('reconciliation.index');
        Route::post('/reconciliation', [ReconciliationController::class, 'store'])->middleware('permission:payments.verify')->name('reconciliation.store');

        // adjustments: discounts, scholarships, waivers
        Route::get('/adjustments', [$adj, 'index'])->middleware('permission:fees.manage|fees.approve')->name('adjustments.index');
        Route::post('/adjustments', [$adj, 'store'])->middleware(['permission:fees.manage', 'throttle:60,1'])->name('adjustments.store');
        Route::post('/adjustments/{adjustment}/approve', [$adj, 'approve'])->whereNumber('adjustment')->middleware('permission:fees.approve')->name('adjustments.approve');
        Route::post('/adjustments/{adjustment}/reject', [$adj, 'reject'])->whereNumber('adjustment')->middleware('permission:fees.approve')->name('adjustments.reject');
        Route::post('/adjustments/{adjustment}/revoke', [$adj, 'revoke'])->whereNumber('adjustment')->middleware('permission:fees.approve')->name('adjustments.revoke');

        // setup: categories, schedule, collection methods, rules
        Route::middleware('permission:fees.manage')->prefix('setup')->name('setup.')->group(function () use ($set) {
            Route::get('/', [$set, 'index'])->name('index');
            Route::post('/categories', [$set, 'storeCategory'])->name('categories.store');
            Route::put('/categories/{category}', [$set, 'updateCategory'])->whereNumber('category')->name('categories.update');
            Route::post('/structures', [$set, 'storeStructure'])->name('structures.store');
            Route::put('/structures/{structure}', [$set, 'updateStructure'])->whereNumber('structure')->name('structures.update');
            Route::delete('/structures/{structure}', [$set, 'destroyStructure'])->whereNumber('structure')->name('structures.destroy');
            Route::post('/methods', [$set, 'storeMethod'])->name('methods.store');
            Route::put('/methods/{method}', [$set, 'updateMethod'])->whereNumber('method')->name('methods.update');
            Route::put('/settings', [$set, 'saveSettings'])->name('settings');
        });

        // reports
        Route::get('/reports', [$rep, 'index'])->middleware('permission:reports.view')->name('reports.index');
        Route::get('/reports/export', [$rep, 'export'])->middleware(['permission:reports.export', 'throttle:20,1'])->name('reports.export');
    });

    // Phase 5 - academic structure, curriculum / teacher allocation, calendar, promotion
    Route::get('/academics', [AcademicController::class, 'index'])->middleware('permission:academics.view')->name('academics.index');
    Route::middleware('permission:academics.manage')->prefix('academics')->group(function () {
        Route::post('/levels', [AcademicController::class, 'storeLevel'])->name('academics.levels.store');
        Route::delete('/levels/{level}', [AcademicController::class, 'destroyLevel'])->whereNumber('level')->name('academics.levels.destroy');
        Route::post('/classes', [AcademicController::class, 'storeClass'])->name('academics.classes.store');
        Route::put('/classes/{class}', [AcademicController::class, 'updateClass'])->whereNumber('class')->name('academics.classes.update');
        Route::delete('/classes/{class}', [AcademicController::class, 'destroyClass'])->whereNumber('class')->name('academics.classes.destroy');
        Route::post('/subjects', [AcademicController::class, 'storeSubject'])->name('academics.subjects.store');
        Route::put('/subjects/{subject}', [AcademicController::class, 'updateSubject'])->whereNumber('subject')->name('academics.subjects.update');
        Route::delete('/subjects/{subject}', [AcademicController::class, 'destroySubject'])->whereNumber('subject')->name('academics.subjects.destroy');
        Route::post('/departments', [AcademicController::class, 'storeDepartment'])->name('academics.departments.store');
        Route::put('/departments/{department}', [AcademicController::class, 'updateDepartment'])->whereNumber('department')->name('academics.departments.update');
        Route::delete('/departments/{department}', [AcademicController::class, 'destroyDepartment'])->whereNumber('department')->name('academics.departments.destroy');

        Route::put('/classes/{class}/curriculum', [CurriculumController::class, 'save'])->whereNumber('class')->name('academics.curriculum.save');
        Route::post('/classes/{class}/curriculum/copy', [CurriculumController::class, 'copy'])->whereNumber('class')->name('academics.curriculum.copy');

        Route::post('/years', [CalendarController::class, 'storeYear'])->name('academics.years.store');
        Route::post('/years/{year}/current', [CalendarController::class, 'setCurrentYear'])->whereNumber('year')->name('academics.years.current');
        Route::put('/terms/{term}', [CalendarController::class, 'updateTerm'])->whereNumber('term')->name('academics.terms.update');
        Route::post('/terms/{term}/current', [CalendarController::class, 'setCurrentTerm'])->whereNumber('term')->name('academics.terms.current');
        Route::post('/events', [CalendarController::class, 'storeEvent'])->name('academics.events.store');
        Route::delete('/events/{event}', [CalendarController::class, 'destroyEvent'])->whereNumber('event')->name('academics.events.destroy');

        Route::get('/promotion', [PromotionController::class, 'index'])->name('academics.promotion');
        Route::post('/promotion', [PromotionController::class, 'store'])->name('academics.promotion.store');
    });
    Route::get('/academics/allocation', [CurriculumController::class, 'index'])->middleware('permission:academics.view')->name('academics.allocation');
    Route::get('/academics/calendar', [CalendarController::class, 'index'])->middleware('permission:academics.view')->name('academics.calendar');

    // Phase 5 - attendance
    Route::prefix('attendance')->name('attendance.')->group(function () {
        Route::get('/', [AttendanceController::class, 'index'])->middleware('permission:attendance.record|attendance.approve|attendance.staff')->name('index');
        Route::get('/take', [AttendanceController::class, 'take'])->middleware('permission:attendance.record')->name('take');
        Route::post('/take', [AttendanceController::class, 'store'])->middleware(['permission:attendance.record', 'throttle:60,1'])->name('store');
        Route::get('/corrections', [AttendanceController::class, 'corrections'])->middleware('permission:attendance.record|attendance.approve')->name('corrections');
        Route::post('/records/{record}/correction', [AttendanceController::class, 'requestCorrection'])->whereNumber('record')->middleware('permission:attendance.record')->name('corrections.request');
        Route::post('/corrections/{correction}/decide', [AttendanceController::class, 'decide'])->whereNumber('correction')->middleware('permission:attendance.approve')->name('corrections.decide');
        Route::get('/report', [AttendanceController::class, 'report'])->middleware('permission:attendance.view')->name('report');
        Route::get('/students/{student}', [AttendanceController::class, 'student'])->whereNumber('student')->middleware('permission:attendance.view')->name('student');
        Route::get('/staff', [AttendanceController::class, 'staff'])->middleware('permission:attendance.staff')->name('staff');
        Route::post('/staff', [AttendanceController::class, 'storeStaff'])->middleware('permission:attendance.staff')->name('staff.store');
    });

    Route::get('/timetable', [TimetableController::class, 'index'])->middleware('permission:timetable.manage|timetable.view')->name('timetable.index');
    Route::post('/timetable', [TimetableController::class, 'store'])->middleware('permission:timetable.manage')->name('timetable.store');
    Route::post('/timetable/check', [TimetableController::class, 'check'])->middleware('permission:timetable.manage')->name('timetable.check');
    Route::post('/timetable/publish', [TimetableController::class, 'publish'])->middleware('permission:timetable.manage')->name('timetable.publish');
    Route::delete('/timetable/{slot}', [TimetableController::class, 'destroy'])->whereNumber('slot')->middleware('permission:timetable.manage')->name('timetable.destroy');
    Route::post('/timetable/periods', [TimetableController::class, 'storePeriod'])->middleware('permission:timetable.manage')->name('timetable.periods.store');
    Route::delete('/timetable/periods/{period}', [TimetableController::class, 'destroyPeriod'])->whereNumber('period')->middleware('permission:timetable.manage')->name('timetable.periods.destroy');
    Route::post('/timetable/unavailability', [TimetableController::class, 'storeUnavailability'])->middleware('permission:timetable.manage')->name('timetable.unavailability.store');
    Route::delete('/timetable/unavailability/{unavailability}', [TimetableController::class, 'destroyUnavailability'])->whereNumber('unavailability')->middleware('permission:timetable.manage')->name('timetable.unavailability.destroy');
    Route::post('/rooms', [TimetableController::class, 'storeRoom'])->middleware('permission:timetable.manage')->name('rooms.store');

    Route::get('/examinations', [ExaminationController::class, 'index'])->middleware('permission:exams.manage|exams.mark')->name('examinations.index');
    Route::post('/examinations', [ExaminationController::class, 'store'])->middleware('permission:exams.manage')->name('examinations.store');
    Route::get('/examinations/settings', [AssessmentSettingsController::class, 'index'])->middleware('permission:exams.manage')->name('examinations.settings');
    Route::post('/examinations/settings/categories', [AssessmentSettingsController::class, 'saveCategories'])->middleware('permission:exams.manage')->name('examinations.settings.categories');
    Route::post('/examinations/settings/scale', [AssessmentSettingsController::class, 'saveScale'])->middleware('permission:exams.manage')->name('examinations.settings.scale');
    Route::get('/examinations/{examination}', [ExaminationController::class, 'show'])->whereNumber('examination')->middleware('permission:exams.manage|exams.mark')->name('examinations.show');
    Route::post('/examinations/{examination}/grades', [ExaminationController::class, 'storeGrade'])->whereNumber('examination')->middleware('permission:exams.mark')->name('examinations.grades.store');
    Route::post('/examinations/{examination}/marks', [ExaminationController::class, 'storeMarks'])->whereNumber('examination')->middleware(['permission:exams.mark|exams.manage', 'throttle:60,1'])->name('examinations.marks.store');
    Route::post('/examinations/{examination}/submit', [ExaminationController::class, 'submit'])->whereNumber('examination')->middleware('permission:exams.mark|exams.manage')->name('examinations.submit');
    Route::post('/examinations/{examination}/approve', [ExaminationController::class, 'approve'])->whereNumber('examination')->middleware('permission:exams.manage')->name('examinations.approve');
    Route::post('/examinations/{examination}/return', [ExaminationController::class, 'returnToDraft'])->whereNumber('examination')->middleware('permission:exams.manage')->name('examinations.return');
    Route::post('/examinations/{examination}/publish', [ExaminationController::class, 'publish'])->whereNumber('examination')->middleware('permission:results.publish')->name('examinations.publish');
    Route::get('/examinations/{examination}/history', [ExaminationController::class, 'history'])->whereNumber('examination')->middleware('permission:exams.manage')->name('examinations.history');

    require __DIR__.'/reporting.php';

    // Phase 6 - external exams, Senior Secondary, Records Office
    require __DIR__.'/phase6.php';

    // Phase 7 - native LMS
    require __DIR__.'/phase7.php';

    Route::get('/lms/external', [ExternalContentController::class, 'index'])->middleware('permission:lms.teach|lms.manage')->name('lms.external.index');
    Route::post('/lms/external/tools', [ExternalContentController::class, 'storeTool'])->middleware('permission:lms.manage')->name('lms.external.tools.store');
    Route::post('/lms/external/packages', [ExternalContentController::class, 'storePackage'])->middleware('permission:lms.manage')->name('lms.external.packages.store');
    Route::get('/lms/external/lessons/{lesson}/launch', [ExternalContentController::class, 'launch'])->middleware('permission:lms.view')->name('lms.external.launch');

    // Phase 9B - HR, leave, payroll
    require __DIR__.'/phase9_hr.php';

    // Phase 9C-9F - library, inventory, procurement, transport, boarding, safeguarding, events
    require __DIR__.'/phase9_ops.php';


    Route::get('/reports', [ReportController::class, 'index'])->middleware('permission:reports.view')->name('reports.index');
    Route::post('/reports/refresh', [ReportController::class, 'refresh'])->middleware('permission:reports.view')->name('reports.refresh');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
