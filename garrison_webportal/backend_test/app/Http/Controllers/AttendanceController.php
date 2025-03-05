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
        // Get the last 30 days of attendance data
        $startDate = now()->subDays(30)->startOfDay();
        $endDate = now()->endOfDay();
        
        // Daily attendance count
        $dailyAttendance = Attendance::selectRaw('DATE(punch_date) as date, COUNT(*) as count')
            ->whereBetween('punch_date', [$startDate, $endDate])
            ->groupBy('date')
            ->orderBy('date')
            ->get();
        
        // Department attendance breakdown
        $departmentAttendance = Attendance::selectRaw('employees.department, COUNT(*) as count')
            ->join('employees', 'attendances.employee_id', '=', 'employees.id')
            ->whereBetween('punch_date', [$startDate, $endDate])
            ->groupBy('employees.department')
            ->orderBy('count', 'desc')
            ->get();
        
        // Average attendance duration by department
        $avgDurationByDept = Attendance::selectRaw('employees.department, AVG(TIME_TO_SEC(duration)) as avg_duration')
            ->join('employees', 'attendances.employee_id', '=', 'employees.id')
            ->whereBetween('punch_date', [$startDate, $endDate])
            ->whereNotNull('duration')
            ->groupBy('employees.department')
            ->get()
            ->map(function($item) {
                // Convert seconds to hours and minutes
                $hours = floor($item->avg_duration / 3600);
                $minutes = floor(($item->avg_duration % 3600) / 60);
                $item->formatted_duration = sprintf("%02d:%02d", $hours, $minutes);
                return $item;
            });
        
        // Prepare chart data
        $dates = $dailyAttendance->pluck('date')->toJson();
        $counts = $dailyAttendance->pluck('count')->toJson();
        
        $deptLabels = $departmentAttendance->pluck('department')->toJson();
        $deptCounts = $departmentAttendance->pluck('count')->toJson();
        
        $durationLabels = $avgDurationByDept->pluck('department')->toJson();
        $durationValues = $avgDurationByDept->pluck('avg_duration')->map(function($seconds) {
            return round($seconds / 3600, 2); // Convert to hours
        })->toJson();
        
        return view('attendance.analytics', compact(
            'dailyAttendance', 
            'departmentAttendance', 
            'avgDurationByDept',
            'dates',
            'counts',
            'deptLabels',
            'deptCounts',
            'durationLabels',
            'durationValues'
        ));
    }

    /**
     * Display the specified attendance record.
     *
     * @param  int  $id
     * @return \Illuminate\View\View
     */
    public function show($id)
    {
        $attendance = Attendance::with('employee')->findOrFail($id);
        
        return view('attendance.show', compact('attendance'));
    }
}
