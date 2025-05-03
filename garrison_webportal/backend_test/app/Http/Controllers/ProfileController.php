<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserInformation;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\LeaveRequest;
use App\Models\Attendance;
use App\Http\Controllers\MfaController;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\View;
use Illuminate\Validation\Rule;
use App\Models\LogInformation;


class ProfileController extends Controller
{
    protected $mfaController;
    
    public function __construct(MfaController $mfaController)
    {
        $this->mfaController = $mfaController;
        
        // Share user information with all profile views
        View::composer('*', function ($view) {
            if (Auth::check()) {
                $user = Auth::user();
                $userInfo = $user->userInformation;
                
                // Share user info with all views
                $view->with('userInfo', $userInfo);
            }
        });
    }
    
    /**
     * Display the user's profile page.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $user = Auth::user();
        // Ensure timestamps are properly loaded
        $user->load('userInformation');
        
        // Get 2FA status
        $twoFactorEnabled = $this->mfaController->isEnabled(Auth::id());
        
        // Get leave balances - Replace with your actual implementation
       $leaveBalances = LeaveBalance::with('leaveType')
            ->where('user_id', $user->userInformation->user_id)
            ->where('year', now()->year)
            ->get();	
	// Get Profile Picture
	$userInfo = $user->userInformation;

        
    $recentAttendance = \App\Models\LogInformation::where('user_id', $user->userInformation->user_id)
        ->orderBy('date_time_event', 'desc')
        ->take(5)
        ->get();
        
        return view('profile.index', compact(
            'user', 
            'userInfo',
	    'twoFactorEnabled', 
            'leaveBalances', 
            'recentAttendance'
        ));
    }

    /**
     * Show the form for editing the user's profile.
     *
     * @return \Illuminate\View\View
     */
    public function edit()
    {
        $user = Auth::user();
        return view('profile.edit', compact('user'));
    }

    /**
     * Update the user's profile information.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request)
    {
        $user = Auth::user();
        
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 
                'string', 
                'email', 
                'max:255', 
                Rule::unique('users')->ignore($user->id)
            ],
            'user_name' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('profile.edit')
                ->withErrors($validator)
                ->withInput();
        }

        // Update user basic info
        $user->name = $request->name;
        $user->email = $request->email;
        $user->save();

        // Update or create user information
        $userInfo = $user->userInformation ?? new UserInformation(['user_id' => $user->id]);
        $userInfo->user_name = $request->user_name;
        $userInfo->phone = $request->phone;
        $userInfo->save();

        return redirect()
            ->route('profile.index')
            ->with('success', 'Profile updated successfully!');
    }

    /**
     * Show the form for changing the user's password.
     *
     * @return \Illuminate\View\View
     */
    public function editPassword()
    {
        return view('profile.password');
    }

    /**
     * Update the user's password.
     * Note: This may be handled by your existing password change functionality,
     * if you're using the change-password blade from auth.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'string', function ($attribute, $value, $fail) {
                if (!Hash::check($value, Auth::user()->password)) {
                    $fail('The current password is incorrect.');
                }
            }],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = Auth::user();
        $user->password = Hash::make($request->password);
        $user->save();

        return redirect()
            ->route('profile.password')
            ->with('success', 'Password changed successfully!');
    }

    /**
     * Display the user's attendance records.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\View\View
     */
    public function attendance(Request $request)
    {
        $user = Auth::user();
        $query = LogInformation::where('user_id', $user->id);
        
        // Date filters
        if ($request->filled('date_from')) {
            $query->whereDate('punch_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('punch_date', '<=', $request->date_to);
        }
        
        // Load relationships
        $query->with('device');
        
        // Order by date and time
        $query->orderBy('date_time_event', 'desc');
        
        // Paginate results
        $attendanceRecords = $query->paginate(15);
        
        return view('profile.attendance', compact('attendanceRecords'));
    }

    /**
     * Display the user's leave management page.
     *
     * @return \Illuminate\View\View
     */

public function leave()
{
    $user = Auth::user();

    // Fetch leave balances
    $leaveBalances = LeaveBalance::with('leaveType')
        ->where('user_id', $user->userInformation->user_id)
        ->where('year', now()->year)
        ->get();

    // Fetch leave requests for this user
    $leaveRequests = LeaveRequest::with('leaveType')
        ->where('user_id', $user->userInformation->user_id)
        ->orderBy('start_date', 'desc')
        ->get();

    return view('profile.leave', compact('leaveBalances', 'leaveRequests'));
}
}
