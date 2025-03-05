<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Employee;
use App\Models\Attendance;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $query = Employee::query();

        // Name filter
        if ($request->filled('name')) {
            $query->where(function($q) use ($request) {
                $q->where('first_name', 'like', '%' . $request->name . '%')
                  ->orWhere('surname', 'like', '%' . $request->name . '%');
            });
        }

        // Department filter
        if ($request->filled('department') && $request->department !== '') {
            $query->where('department', $request->department);
        }

        $employees = $query->orderBy('surname')->paginate(10);

        return view('employees', compact('employees'));
    }

    public function show($id)
    {
        $employee = Employee::findOrFail($id);
        $attendanceRecords = Attendance::where('employee_id', $id)->get();

        return view('employee_profile', compact('employee', 'attendanceRecords'));
    }

    /**
     * Show the form for creating a new employee.
     */
    public function create()
    {
        return view('create'); 
    }

}