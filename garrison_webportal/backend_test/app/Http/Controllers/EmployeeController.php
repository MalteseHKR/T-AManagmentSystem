<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Employee;
use App\Models\Attendance;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        // Fetch employees with optional filters
        $query = Employee::query();

        if ($request->has('name') && $request->input('name') !== '') {
            $name = $request->input('name');
            $query->where(function($q) use ($name) {
                $q->where('first_name', 'like', '%' . $name . '%')
                  ->orWhere('surname', 'like', '%' . $name . '%')
                  ->orWhereRaw("CONCAT(first_name, ' ', surname) LIKE ?", ["%{$name}%"]);
            });
        }

        if ($request->has('department') && $request->input('department') !== '') {
            $query->where('department', $request->input('department'));
        }

        $employees = $query->get();

        return view('employees', ['employees' => $employees]);
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