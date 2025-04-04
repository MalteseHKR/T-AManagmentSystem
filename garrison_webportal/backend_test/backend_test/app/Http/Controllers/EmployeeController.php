<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\UserInformation;  // Updated import
use App\Models\Attendance;
use App\Models\LogInformation;   // Add this import for attendance records
use Illuminate\Support\Facades\DB;


class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $query = UserInformation::query();
        
        // Apply name filter if provided
        if ($request->has('name') && $request->name) {
            $query->where('user_name', 'like', '%' . $request->name . '%');
        }
        
        // Apply department filter if provided
        if ($request->has('department') && $request->department) {
            $query->where('user_department', $request->department);
        }
        
        // Get unique departments for the filter dropdown
        $departments = UserInformation::select('user_department')
            ->whereNotNull('user_department')  
            ->where('user_department', '!=', '') 
            ->distinct()
            ->orderBy('user_department')
            ->pluck('user_department')
            ->toArray();
        
        // Get the users with pagination
        $userInformation = $query->paginate(15);
        
        return view('employees', compact('userInformation', 'departments'));
    }

public function show($id)
{
    // Find the user information by user_id instead of id
    $userInfo = UserInformation::where('user_id', $id)->firstOrFail();

    // Attach profile photo path from user_profile_photo table (if available)
    $photo = DB::table('user_profile_photo')
        ->where('user_id', $userInfo->user_id)
        ->first();

    $userInfo->portrait_url = $photo?->file_name_link ?? null;

    // Get the attendance records
    $attendanceRecords = LogInformation::where('user_id', $userInfo->user_id)
        ->orderBy('punch_date', 'desc')
        ->orderBy('punch_time', 'desc')
        ->get();

    // Pass everything to the view
    return view('employee_profile', compact('userInfo', 'attendanceRecords'));
}

    /**
     * Show the form for creating a new employee.
     */
    public function create()
    {
        return view('create'); 
    }

}