<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
})->name('welcome');

Route::get('/debug-diag', function () {
    try {
        $pdo = \Illuminate\Support\Facades\DB::connection()->getPdo();
        $dbName = \Illuminate\Support\Facades\DB::connection()->getDatabaseName();
        $userCount = \App\Models\User::count();
        $auth = auth()->user();
        $session = session()->all();

        return response()->json([
            'database_connection' => config('database.default'),
            'database_name' => $dbName,
            'user_count' => $userCount,
            'authenticated_user' => $auth,
            'session_id' => session()->getId(),
            'env_db_database' => env('DB_DATABASE'),
            'php_version' => PHP_VERSION,
        ]);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()], 500);
    }
});

Route::post('/test-tasks', function () {
    return 'Test Route Works';
});

Route::fallback(function () {
    return 'Fallback: Route not found for ' . request()->method() . ' ' . request()->path();
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', [\App\Http\Controllers\TaskController::class, 'index'])->name('dashboard');
    Route::post('/tasks', [\App\Http\Controllers\TaskController::class, 'store'])->name('tasks.store');
    Route::put('/tasks/{task}', [\App\Http\Controllers\TaskController::class, 'update'])->name('tasks.update');
    Route::delete('/tasks/{task}', [\App\Http\Controllers\TaskController::class, 'destroy'])->name('tasks.destroy');
    Route::patch('/checklist-items/{checklistItem}', [\App\Http\Controllers\TaskController::class, 'toggleChecklistItem'])->name('checklist-items.toggle');
    Route::post('/moods', [\App\Http\Controllers\MoodController::class, 'store'])->name('moods.store');
    Route::post('/tasks/bulk-schedule', [\App\Http\Controllers\TaskController::class, 'bulkSchedule'])->name('tasks.bulk-schedule');
    Route::post('/tasks/bulk-store', [\App\Http\Controllers\TaskController::class, 'bulkStore'])->name('tasks.bulk-store');
    Route::post('/ai-plan', [\App\Http\Controllers\TaskController::class, 'generatePlan'])->name('ai.plan');

    // Google Calendar
    Route::get('/auth/google', [\App\Http\Controllers\GoogleCalendarController::class, 'redirectToGoogle'])->name('google.auth');
    Route::get('/auth/google/callback', [\App\Http\Controllers\GoogleCalendarController::class, 'handleGoogleCallback'])->name('google.callback');
    Route::get('/calendar/events', [\App\Http\Controllers\GoogleCalendarController::class, 'getEvents'])->name('calendar.events');

    // iCloud Calendar
    Route::post('/auth/icloud', [\App\Http\Controllers\ICloudCalendarController::class, 'connect'])->name('icloud.auth');

    // Settings
    Route::get('/settings/account', function () {
        return Inertia::render('Settings/Account');
    })->name('settings.account');
});
