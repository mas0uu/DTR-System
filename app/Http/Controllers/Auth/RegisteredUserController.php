<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\DtrMonth;
use App\Models\DtrRow;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            // User identification
            'student_name' => 'required|string|max:255',
            'student_no' => [
                'nullable',
                'string',
                Rule::requiredIf(fn () => $request->input('employee_type') === 'intern'),
                Rule::unique(User::class),
            ],
            'email' => 'required|string|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            
            // Academic information
            'school' => [
                'nullable',
                'string',
                'max:255',
                Rule::requiredIf(fn () => $request->input('employee_type') === 'intern'),
            ],
            'required_hours' => [
                'nullable',
                'integer',
                'min:0',
                Rule::requiredIf(fn () => $request->input('employee_type') === 'intern'),
            ],
            
            // Internship information
            'company' => 'required|string|max:255',
            'department' => 'required|string|max:255',
            'supervisor_name' => 'required|string|max:255',
            'supervisor_position' => 'required|string|max:255',

            // Employment setup
            'employee_type' => 'required|in:intern,regular',
            'starting_date' => 'required|date|before_or_equal:today',
            'working_days' => 'required|array|min:1',
            'working_days.*' => 'integer|between:0,6',
            'work_time_in' => 'required|date_format:H:i',
            'work_time_out' => 'required|date_format:H:i|after:work_time_in',
            'default_break_minutes' => 'nullable|integer|in:5,10,15,30,45,60',
            'salary_type' => [
                'nullable',
                'in:monthly,daily,hourly',
                Rule::requiredIf(fn () => $request->input('employee_type') === 'regular'),
            ],
            'salary_amount' => [
                'nullable',
                'numeric',
                'min:0.01',
                Rule::requiredIf(fn () => $request->input('employee_type') === 'regular'),
            ],
        ]);

        $user = User::create([
            'name' => $request->student_name,
            'email' => $request->email,
            'student_name' => $request->student_name,
            'student_no' => $request->employee_type === 'intern' ? $request->student_no : null,
            'school' => $request->employee_type === 'intern' ? $request->school : null,
            'required_hours' => $request->employee_type === 'intern' ? $request->required_hours : 0,
            'company' => $request->company,
            'department' => $request->department,
            'supervisor_name' => $request->supervisor_name,
            'supervisor_position' => $request->supervisor_position,
            'employee_type' => $request->employee_type,
            'intern_compensation_enabled' => $request->employee_type === 'regular',
            'starting_date' => $request->starting_date,
            'working_days' => $request->working_days,
            'work_time_in' => $request->work_time_in,
            'work_time_out' => $request->work_time_out,
            'default_break_minutes' => (int) $request->input('default_break_minutes', 60),
            'salary_type' => $request->employee_type === 'regular' ? $request->salary_type : null,
            'salary_amount' => $request->employee_type === 'regular' ? $request->salary_amount : null,
            'initial_paid_leave_days' => 0,
            'current_paid_leave_balance' => 0,
            'leave_reset_month' => 1,
            'leave_reset_day' => 1,
            'employment_status' => 'active',
            'password' => Hash::make($request->password),
        ]);

        $this->generateDtrRowsFromStartDate($user);

        Auth::login($user);

        return redirect(route('dashboard'));
    }

    private function generateDtrRowsFromStartDate(User $user): void
    {
        $timezone = 'Asia/Manila';
        $startDate = Carbon::parse($user->starting_date, $timezone)->startOfDay();
        $today = Carbon::now($timezone)->startOfDay();

        $workingDays = collect($user->working_days ?? [])->map(fn ($day) => (int) $day)->all();

        for ($date = $startDate->copy(); $date->lte($today); $date->addDay()) {
            if (! in_array($date->dayOfWeek, $workingDays, true)) {
                continue;
            }

            $month = DtrMonth::firstOrCreate([
                'user_id' => $user->id,
                'month' => $date->month,
                'year' => $date->year,
            ]);

            DtrRow::firstOrCreate(
                [
                    'dtr_month_id' => $month->id,
                    'date' => $date->format('Y-m-d'),
                ],
                [
                    'day' => $date->format('l'),
                    'time_in' => null,
                    'time_out' => null,
                    'total_minutes' => 0,
                    'break_minutes' => 0,
                    'late_minutes' => 0,
                    'on_break' => false,
                    'break_started_at' => null,
                    'break_target_minutes' => null,
                    'status' => 'draft',
                ]
            );
        }
    }
}
