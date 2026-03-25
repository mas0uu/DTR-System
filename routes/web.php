<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DtrMonthController;
use App\Http\Controllers\DtrRowController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\AdminEmployeeController;
use App\Http\Controllers\AdminPayrollController;
use App\Http\Controllers\AdminAttendanceController;
use App\Http\Controllers\AdminAuditController;
use App\Http\Controllers\AdminAnomalyController;
use App\Http\Controllers\AdminPasswordResetRequestController;
use App\Http\Controllers\AdminHolidayController;
use App\Http\Controllers\AdminInternProgressController;
use App\Http\Controllers\AdminLeaveController;
use App\Http\Controllers\EmployeeHolidayController;
use App\Http\Controllers\EmployeeLeaveController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check()) {
        return auth()->user()?->isAdmin()
            ? redirect()->route('admin.employees.index')
            : redirect()->route('dtr.index');
    }
    return redirect()->route('login');
});

Route::get('/dashboard', function () {
    return auth()->user()?->isAdmin()
        ? redirect()->route('admin.employees.index')
        : redirect()->route('dtr.index');
})->middleware(['auth'])->name('dashboard');

Route::middleware(['auth', 'force_password_change', 'active_employee'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/photo', [ProfileController::class, 'updatePhoto'])->name('profile.photo.update');
    Route::delete('/profile/photo', [ProfileController::class, 'destroyPhoto'])->name('profile.photo.destroy');
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
    Route::get('leaves', [EmployeeLeaveController::class, 'index'])->name('leaves.index');
    Route::get('holidays', [EmployeeHolidayController::class, 'index'])->name('holidays.index');
    Route::get('payroll', [PayrollController::class, 'index'])->name('payroll.index');
    Route::get('payroll/{payrollRecord}/payslip/view', [PayrollController::class, 'showPayslip'])->name('payroll.payslip.view');
    Route::get('payroll/{payrollRecord}/payslip', [PayrollController::class, 'downloadPayslip'])->name('payroll.payslip');
    Route::post('payroll/generate', [PayrollController::class, 'generate'])->name('payroll.generate');
});

Route::middleware(['auth', 'force_password_change', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('employees', [AdminEmployeeController::class, 'index'])->name('employees.index');
    Route::get('employees/create', [AdminEmployeeController::class, 'create'])->name('employees.create');
    Route::post('employees', [AdminEmployeeController::class, 'store'])->name('employees.store');
    Route::get('employees/{employee}/edit', [AdminEmployeeController::class, 'edit'])->name('employees.edit');
    Route::patch('employees/{employee}', [AdminEmployeeController::class, 'update'])->name('employees.update');
    Route::patch('employees/{employee}/deactivate', [AdminEmployeeController::class, 'deactivate'])->name('employees.deactivate');
    Route::patch('employees/{employee}/archive', [AdminEmployeeController::class, 'archive'])->name('employees.archive');
    Route::patch('employees/{employee}/reactivate', [AdminEmployeeController::class, 'reactivate'])->name('employees.reactivate');
    Route::delete('employees/{employee}', [AdminEmployeeController::class, 'destroy'])->name('employees.destroy');
    Route::get('payroll', [AdminPayrollController::class, 'index'])->name('payroll.index');
    Route::post('payroll/generate', [AdminPayrollController::class, 'generate'])->name('payroll.generate');
    Route::post('payroll/generate-all', [AdminPayrollController::class, 'generateAll'])->name('payroll.generate_all');
    Route::patch('payroll/{payrollRecord}/review', [AdminPayrollController::class, 'review'])->name('payroll.review');
    Route::patch('payroll/{payrollRecord}/finalize', [AdminPayrollController::class, 'finalize'])->name('payroll.finalize');
    Route::delete('payroll/{payrollRecord}', [AdminPayrollController::class, 'destroy'])->name('payroll.destroy');
    Route::get('payroll/{payrollRecord}/payslip/view', [AdminPayrollController::class, 'showPayslip'])->name('payroll.payslip.view');
    Route::get('payroll/{payrollRecord}/payslip/download', [AdminPayrollController::class, 'downloadPayslip'])->name('payroll.payslip.download');
    Route::get('attendance', [AdminAttendanceController::class, 'index'])->name('attendance.index');
    Route::get('attendance/logs', [AdminAttendanceController::class, 'logs'])->name('attendance.logs');
    Route::get('attendance/{employee}', [AdminAttendanceController::class, 'show'])->name('attendance.show');
    Route::patch('attendance/rows/{row}', [AdminAttendanceController::class, 'updateRow'])->name('attendance.rows.update');
    Route::patch('attendance/rows/{row}/correct', [AdminAttendanceController::class, 'correctRow'])->name('attendance.rows.correct');
    Route::get('leaves', [AdminLeaveController::class, 'index'])->name('leaves.index');
    Route::patch('leaves/{leaveRequest}/decision', [AdminLeaveController::class, 'decide'])->name('leaves.decision');
    Route::patch('leaves/balance/{employee}', [AdminLeaveController::class, 'adjustBalance'])->name('leaves.balance.adjust');
    Route::get('holidays', [AdminHolidayController::class, 'index'])->name('holidays.index');
    Route::post('holidays', [AdminHolidayController::class, 'store'])->name('holidays.store');
    Route::patch('holidays/{holiday}', [AdminHolidayController::class, 'update'])->name('holidays.update');
    Route::delete('holidays/{holiday}', [AdminHolidayController::class, 'destroy'])->name('holidays.destroy');
    Route::get('anomalies', [AdminAnomalyController::class, 'index'])->name('anomalies.index');
    Route::get('security/password-resets', [AdminPasswordResetRequestController::class, 'index'])->name('password_reset_requests.index');
    Route::patch('security/password-resets/{passwordResetRequest}/approve', [AdminPasswordResetRequestController::class, 'approve'])->name('password_reset_requests.approve');
    Route::patch('security/password-resets/{passwordResetRequest}/reject', [AdminPasswordResetRequestController::class, 'reject'])->name('password_reset_requests.reject');
    Route::get('intern-progress', [AdminInternProgressController::class, 'index'])->name('intern_progress.index');
    Route::get('audit', [AdminAuditController::class, 'index'])->name('audit.index');
});

require __DIR__.'/auth.php';
