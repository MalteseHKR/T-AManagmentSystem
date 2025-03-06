<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Attendance;
use App\Models\Employee;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        $query = Attendance::query()->with('employee');

        // Name filter
        if ($request->filled('name')) {
            $query->whereHas('employee', function($q) use ($request) {
                $q->where('first_name', 'like', '%' . $request->name . '%')
                  ->orWhere('surname', 'like', '%' . $request->name . '%');
            });
        }

        // Date filters
        if ($request->filled('date_from')) {
            $query->whereDate('punch_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('punch_date', '<=', $request->date_to);
        }

        // Sorting
        $sortField = $request->get('sort', 'punch_date');
        $sortDirection = $request->get('direction', 'desc');
        
        $query->orderBy($sortField, $sortDirection);

        // Changed from the default for a custom amount
        $attendances = $query->paginate(50);
            
        return view('attendance', [
            'attendances' => $attendances,
            'sortField' => $sortField,
            'sortDirection' => $sortDirection
        ]);
    }

    /**
     * Display attendance analytics and statistics.
     *
     * @return \Illuminate\View\View
     */
    public function analytics()
    {
        // Simple data for testing
        $departmentData = [
            'labels' => ['HR', 'IT', 'Finance', 'Sales', 'Marketing'],
            'values' => [12, 19, 8, 15, 10]
        ];
        
        $attendanceData = [
            'labels' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            'values' => [65, 72, 78, 75, 68, 40, 35]
        ];
        
        return view('attendance.analytics', compact('departmentData', 'attendanceData'));
    }

    /**
     * Display attendance records for a specific employee
     *
     * @param int $employeeId
     * @return \Illuminate\View\View
     */
    public function showEmployeeAttendance($employeeId)
    {
        $employee = Employee::findOrFail($employeeId);
        $attendanceRecords = Attendance::where('employee_id', $employeeId)
            ->orderBy('date', 'desc')
            ->paginate(15);
            
        return view('attendance.attendanceE', [
            'employee' => $employee,
            'attendanceRecords' => $attendanceRecords
        ]);
    }
}
