<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Attendance;
use App\Models\Employee;

class AttendanceController extends Controller
{
    public function index()
    {
        $attendanceRecords = Attendance::all();
        return view('attendance', compact('attendanceRecords'));
    }

    public function show($employeeId)
    {
        $employee = Employee::findOrFail($employeeId);
        $attendanceRecords = Attendance::where('employee_id', $employeeId)->get();

        return view('attendance', compact('attendanceRecords'));
    }
}
