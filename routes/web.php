<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DtrMonthController;
use App\Http\Controllers\DtrRowController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dtr.index');
    }
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return redirect()->route('dtr.index');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // DTR Routes
    Route::get('/dtr', [DtrMonthController::class, 'index'])->name('dtr.index');
    Route::get('dtr/months', [DtrMonthController::class, 'index'])->name('dtr.months.index');
    Route::post('dtr/months', [DtrMonthController::class, 'store'])->name('dtr.months.store');
    Route::get('dtr/months/{month}', [DtrMonthController::class, 'show'])->name('dtr.months.show');
    Route::patch('dtr/months/{month}/finish', [DtrMonthController::class, 'finish'])->name('dtr.months.finish');
    Route::delete('dtr/months/{month}', [DtrMonthController::class, 'destroy'])->name('dtr.months.destroy');
    Route::post('dtr/rows', [DtrRowController::class, 'store'])->name('dtr.rows.store');
    Route::patch('dtr/rows/{row}', [DtrRowController::class, 'update'])->name('dtr.rows.update');
    Route::patch('dtr/rows/{row}/clock-in', [DtrRowController::class, 'clockIn'])->name('dtr.rows.clock_in');
    Route::patch('dtr/rows/{row}/clock-out', [DtrRowController::class, 'clockOut'])->name('dtr.rows.clock_out');
    Route::patch('dtr/rows/{row}/break/start', [DtrRowController::class, 'startBreak'])->name('dtr.rows.break_start');
    Route::patch('dtr/rows/{row}/break/finish', [DtrRowController::class, 'finishBreak'])->name('dtr.rows.break_finish');
    Route::patch('dtr/rows/{row}/leave', [DtrRowController::class, 'markLeave'])->name('dtr.rows.leave');
    Route::delete('dtr/rows/{row}', [DtrRowController::class, 'destroy'])->name('dtr.rows.destroy');
});

require __DIR__.'/auth.php';
