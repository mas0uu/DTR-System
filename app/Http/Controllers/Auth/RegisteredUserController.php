<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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
            'student_no' => 'required|string|unique:'.User::class,
            'email' => 'required|string|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            
            // Academic information
            'school' => 'required|string|max:255',
            'required_hours' => 'required|integer|min:1',
            
            // Internship information
            'company' => 'required|string|max:255',
            'department' => 'required|string|max:255',
            'supervisor_name' => 'required|string|max:255',
            'supervisor_position' => 'required|string|max:255',
        ]);

        $user = User::create([
            'name' => $request->student_name,
            'email' => $request->email,
            'student_name' => $request->student_name,
            'student_no' => $request->student_no,
            'school' => $request->school,
            'required_hours' => $request->required_hours,
            'company' => $request->company,
            'department' => $request->department,
            'supervisor_name' => $request->supervisor_name,
            'supervisor_position' => $request->supervisor_position,
            'password' => Hash::make($request->password),
        ]);

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('verification.notice'));
    }
}
