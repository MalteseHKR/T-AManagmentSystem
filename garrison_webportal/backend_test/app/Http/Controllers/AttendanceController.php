<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LogInformation;
use App\Models\UserInformation;
use App\Models\Device;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        // Update the model reference to use LogInformation instead of Attendance
        $query = LogInformation::query()->with('userInformation', 'device');

        // Name filter - updated to use userInformation relationship
        if ($request->filled('name')) {
            $query->whereHas('userInformation', function($q) use ($request) {
                $q->where('user_name', 'like', '%' . $request->name . '%')
                  ->orWhere('user_surname', 'like', '%' . $request->name . '%');
            });
        }

        // Date filters - updated to use punch_date field
        if ($request->filled('date_from')) {
            $query->whereDate('punch_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('punch_date', '<=', $request->date_to);
        }

        // Sorting - default to most recent events first
        $sortField = $request->get('sort', 'date_time_event');
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
        // Get department attendance data
        $departmentData = [
            'labels' => [],
            'values' => []
        ];

        $departmentStats = DB::table('log_information AS li')
            ->join('user_information AS ui', 'li.user_id', '=', 'ui.user_id')
            ->select('ui.user_department', DB::raw('COUNT(*) as count'))
            ->whereNotNull('ui.user_department')
            ->groupBy('ui.user_department')
            ->get();

        foreach ($departmentStats as $stat) {
            $departmentData['labels'][] = $stat->user_department;
            $departmentData['values'][] = $stat->count;
        }
        
        // Get weekly attendance data
        $weeklyData = [
            'labels' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            'values' => []
        ];

        // Get counts for each day of the week
        for ($i = 0; $i < 7; $i++) {
            $dayCount = DB::table('log_information')
                ->where(DB::raw('DAYOFWEEK(punch_date)'), '=', $i + 1) // 1 = Sunday in MySQL
                ->count();
            
            // Adjust array to make Monday first day of week
            $weeklyData['values'][($i + 6) % 7] = $dayCount;
        }
        
        return view('attendance.analytics', compact('departmentData', 'weeklyData'));
    }

    /**
     * Display attendance records for a specific employee
     *
     * @param int $employeeId
     * @return \Illuminate\View\View
     */
    public function showEmployeeAttendance($userId)
    {
        $employee = UserInformation::findOrFail($userId);
        
        $attendances = LogInformation::where('user_id', $userId)
            ->orderBy('date_time_event', 'desc')
            ->paginate(15);
            
        return view('attendance.attendanceE', [
            'employee' => $employee,
            'attendances' => $attendances
        ]);
    }

    /**
     * Display the attendance dashboard
     * 
     * @return \Illuminate\View\View
     */
    public function dashboard()
    {
        // Today's attendance count
        $todayCount = LogInformation::whereDate('punch_date', today())->count();
        
        // Present employees today
        $presentToday = LogInformation::select('user_id')
            ->whereDate('punch_date', today())
            ->where('punch_type', 'IN')
            ->distinct()
            ->count();
        
        // Yesterday's attendance
        $yesterdayCount = LogInformation::whereDate('punch_date', today()->subDay())->count();
            
        // Monthly attendance trends
        $monthlyTrend = [];
        $daysInMonth = now()->daysInMonth;
        
        for ($i = 1; $i <= $daysInMonth; $i++) {
            $date = now()->startOfMonth()->addDays($i - 1);
            $count = LogInformation::whereDate('punch_date', $date)->count();
            
            $monthlyTrend[] = [
                'day' => $i,
                'count' => $count
            ];
        }
        
        return view('attendance.analytics', compact(
            'todayCount', 'presentToday', 'yesterdayCount', 'monthlyTrend'
        ));
    }
}
