<?php

use App\Http\Controllers\Lms\AssignmentController;
use App\Http\Controllers\Lms\CommunityController;
use App\Http\Controllers\Lms\CourseController;
use App\Http\Controllers\Lms\FileController;
use App\Http\Controllers\Lms\GradebookController;
use App\Http\Controllers\Lms\LessonController;
use App\Http\Controllers\Lms\QuestionBankController;
use App\Http\Controllers\Lms\QuizAttemptController;
use App\Http\Controllers\Lms\QuizController;
use App\Http\Controllers\Lms\SubmissionController;
use Illuminate\Support\Facades\Route;

/*
| Phase 7 - native LMS. Included from routes/web.php inside the authenticated, tenant-bound school group.
| Every handler also resolves the caller's role in the course (manager / course teacher / enrolled learner) and aborts 403
| otherwise, so the route permission is a first gate, not the only one.
*/

Route::prefix('lms')->name('lms.')->group(function () {
    // ------------------------------------------------------------------ everyone with LMS access (role checked in the handler)
    Route::middleware('permission:lms.view')->group(function () {
        Route::get('/courses', [CourseController::class, 'index'])->name('courses.index');
        Route::get('/courses/{course}', [CourseController::class, 'show'])->whereNumber('course')->name('courses.show');
        Route::get('/courses/{course}/gradebook', [GradebookController::class, 'show'])->whereNumber('course')->name('courses.gradebook');
        Route::get('/courses/{course}/announcements', [CommunityController::class, 'announcements'])->whereNumber('course')->name('courses.announcements');
        Route::get('/courses/{course}/forum', [CommunityController::class, 'forum'])->whereNumber('course')->name('courses.forum');
        Route::post('/courses/{course}/threads', [CommunityController::class, 'storeThread'])->whereNumber('course')->middleware('throttle:30,1')->name('threads.store');
        Route::get('/threads/{thread}', [CommunityController::class, 'thread'])->whereNumber('thread')->name('threads.show');
        Route::post('/threads/{thread}/replies', [CommunityController::class, 'reply'])->whereNumber('thread')->middleware('throttle:60,1')->name('threads.reply');
        Route::post('/threads/{thread}/answer/{post}', [CommunityController::class, 'markAnswer'])->whereNumber(['thread', 'post'])->name('threads.answer');

        Route::get('/lessons/{lesson}', [LessonController::class, 'show'])->whereNumber('lesson')->name('lessons.show');
        Route::post('/lessons/{lesson}/complete', [LessonController::class, 'complete'])->whereNumber('lesson')->name('lessons.complete');
        Route::get('/files/{file}', [FileController::class, 'show'])->whereNumber('file')->name('files.show');

        Route::get('/assignments/{assignment}', [AssignmentController::class, 'show'])->whereNumber('assignment')->name('assignments.show');
        Route::post('/assignments/{assignment}/submit', [AssignmentController::class, 'submit'])->whereNumber('assignment')->middleware('throttle:20,1')->name('assignments.submit');

        Route::get('/quizzes/{quiz}', [QuizAttemptController::class, 'show'])->whereNumber('quiz')->name('quizzes.show');
        Route::post('/quizzes/{quiz}/start', [QuizAttemptController::class, 'start'])->whereNumber('quiz')->middleware('throttle:30,1')->name('quizzes.start');
        Route::get('/attempts/{attempt}', [QuizAttemptController::class, 'review'])->whereNumber('attempt')->name('attempts.show');
        Route::post('/attempts/{attempt}/save', [QuizAttemptController::class, 'save'])->whereNumber('attempt')->middleware('throttle:240,1')->name('attempts.save');
        Route::post('/attempts/{attempt}/submit', [QuizAttemptController::class, 'submit'])->whereNumber('attempt')->middleware('throttle:30,1')->name('attempts.submit');
    });

    // ------------------------------------------------------------------ authoring and marking (teachers of the course, managers)
    Route::middleware('permission:lms.teach|lms.manage')->group(function () {
        Route::get('/courses/create', [CourseController::class, 'create'])->name('courses.create');
        Route::post('/courses', [CourseController::class, 'store'])->name('courses.store');
        Route::get('/courses/{course}/edit', [CourseController::class, 'edit'])->whereNumber('course')->name('courses.edit');
        Route::put('/courses/{course}', [CourseController::class, 'update'])->whereNumber('course')->name('courses.update');
        Route::get('/courses/{course}/people', [CourseController::class, 'people'])->whereNumber('course')->name('courses.people');
        Route::post('/courses/{course}/enrol', [CourseController::class, 'enrol'])->whereNumber('course')->middleware('throttle:30,1')->name('courses.enrol');
        Route::post('/courses/{course}/audience/sync', [CourseController::class, 'syncAudience'])->whereNumber('course')->name('courses.audience.sync');
        Route::delete('/courses/{course}/enrolments/{enrolment}', [CourseController::class, 'withdraw'])->whereNumber(['course', 'enrolment'])->name('courses.withdraw');
        Route::post('/courses/{course}/sections', [CourseController::class, 'storeSection'])->whereNumber('course')->name('sections.store');
        Route::put('/sections/{section}', [CourseController::class, 'updateSection'])->whereNumber('section')->name('sections.update');
        Route::delete('/sections/{section}', [CourseController::class, 'destroySection'])->whereNumber('section')->name('sections.destroy');

        Route::get('/courses/{course}/lessons/create', [LessonController::class, 'create'])->whereNumber('course')->name('lessons.create');
        Route::post('/courses/{course}/lessons', [LessonController::class, 'store'])->whereNumber('course')->name('lessons.store');
        Route::get('/lessons/{lesson}/edit', [LessonController::class, 'edit'])->whereNumber('lesson')->name('lessons.edit');
        Route::put('/lessons/{lesson}', [LessonController::class, 'update'])->whereNumber('lesson')->name('lessons.update');
        Route::delete('/lessons/{lesson}', [LessonController::class, 'destroy'])->whereNumber('lesson')->name('lessons.destroy');
        Route::post('/lessons/{lesson}/materials', [LessonController::class, 'storeMaterial'])->whereNumber('lesson')->middleware('throttle:30,1')->name('lessons.materials.store');
        Route::delete('/files/{file}', [FileController::class, 'destroy'])->whereNumber('file')->name('files.destroy');

        Route::get('/courses/{course}/assignments/create', [AssignmentController::class, 'create'])->whereNumber('course')->name('assignments.create');
        Route::post('/courses/{course}/assignments', [AssignmentController::class, 'store'])->whereNumber('course')->name('assignments.store');
        Route::get('/assignments/{assignment}/edit', [AssignmentController::class, 'edit'])->whereNumber('assignment')->name('assignments.edit');
        Route::put('/assignments/{assignment}', [AssignmentController::class, 'update'])->whereNumber('assignment')->name('assignments.update');
        Route::delete('/assignments/{assignment}', [AssignmentController::class, 'destroy'])->whereNumber('assignment')->name('assignments.destroy');
        Route::post('/assignments/{assignment}/attachments', [AssignmentController::class, 'attach'])->whereNumber('assignment')->middleware('throttle:30,1')->name('assignments.attach');
        Route::get('/submissions/{submission}', [SubmissionController::class, 'show'])->whereNumber('submission')->name('submissions.show');
        Route::post('/submissions/{submission}/grade', [SubmissionController::class, 'grade'])->whereNumber('submission')->name('submissions.grade');
        Route::post('/submissions/{submission}/return', [SubmissionController::class, 'returnBack'])->whereNumber('submission')->name('submissions.return');

        Route::get('/courses/{course}/quizzes/create', [QuizController::class, 'create'])->whereNumber('course')->name('quizzes.create');
        Route::post('/courses/{course}/quizzes', [QuizController::class, 'store'])->whereNumber('course')->name('quizzes.store');
        Route::get('/quizzes/{quiz}/edit', [QuizController::class, 'edit'])->whereNumber('quiz')->name('quizzes.edit');
        Route::put('/quizzes/{quiz}', [QuizController::class, 'update'])->whereNumber('quiz')->name('quizzes.update');
        Route::delete('/quizzes/{quiz}', [QuizController::class, 'destroy'])->whereNumber('quiz')->name('quizzes.destroy');
        Route::post('/quizzes/{quiz}/questions', [QuizController::class, 'storeQuestion'])->whereNumber('quiz')->name('quizzes.questions.store');
        Route::put('/questions/{question}', [QuizController::class, 'updateQuestion'])->whereNumber('question')->name('questions.update');
        Route::delete('/questions/{question}', [QuizController::class, 'destroyQuestion'])->whereNumber('question')->name('questions.destroy');
        Route::post('/quizzes/{quiz}/pulls', [QuizController::class, 'storePull'])->whereNumber('quiz')->name('quizzes.pulls.store');
        Route::delete('/pulls/{pull}', [QuizController::class, 'destroyPull'])->whereNumber('pull')->name('pulls.destroy');
        Route::post('/quizzes/{quiz}/release', [QuizController::class, 'release'])->whereNumber('quiz')->name('quizzes.release');
        Route::get('/quizzes/{quiz}/attempts', [QuizController::class, 'attempts'])->whereNumber('quiz')->name('quizzes.attempts');
        Route::get('/attempts/{attempt}/grade', [QuizController::class, 'showAttempt'])->whereNumber('attempt')->name('attempts.grade');
        Route::post('/attempts/{attempt}/grade', [QuizController::class, 'gradeAttempt'])->whereNumber('attempt')->name('attempts.grade.store');

        Route::get('/banks', [QuestionBankController::class, 'index'])->name('banks.index');
        Route::post('/banks', [QuestionBankController::class, 'store'])->name('banks.store');
        Route::get('/banks/{bank}', [QuestionBankController::class, 'show'])->whereNumber('bank')->name('banks.show');
        Route::put('/banks/{bank}', [QuestionBankController::class, 'update'])->whereNumber('bank')->name('banks.update');
        Route::delete('/banks/{bank}', [QuestionBankController::class, 'destroy'])->whereNumber('bank')->name('banks.destroy');
        Route::post('/banks/{bank}/questions', [QuestionBankController::class, 'storeQuestion'])->whereNumber('bank')->name('banks.questions.store');
        Route::put('/bank-questions/{question}', [QuestionBankController::class, 'updateQuestion'])->whereNumber('question')->name('bank-questions.update');
        Route::delete('/bank-questions/{question}', [QuestionBankController::class, 'destroyQuestion'])->whereNumber('question')->name('bank-questions.destroy');

        Route::get('/courses/{course}/gradebook/export', [GradebookController::class, 'export'])->whereNumber('course')->name('courses.gradebook.export');
        Route::post('/courses/{course}/gradebook/feedback/{student}', [GradebookController::class, 'feedback'])->whereNumber(['course', 'student'])->name('courses.gradebook.feedback');
        Route::get('/courses/{course}/progress', [GradebookController::class, 'progress'])->whereNumber('course')->name('courses.progress');

        Route::post('/courses/{course}/announcements', [CommunityController::class, 'storeAnnouncement'])->whereNumber('course')->name('announcements.store');
        Route::delete('/announcements/{announcement}', [CommunityController::class, 'destroyAnnouncement'])->whereNumber('announcement')->name('announcements.destroy');
        Route::post('/posts/{post}/hide', [CommunityController::class, 'hidePost'])->whereNumber('post')->name('posts.hide');
        Route::post('/threads/{thread}/lock', [CommunityController::class, 'lockThread'])->whereNumber('thread')->name('threads.lock');
        Route::post('/threads/{thread}/pin', [CommunityController::class, 'pinThread'])->whereNumber('thread')->name('threads.pin');
    });
});
