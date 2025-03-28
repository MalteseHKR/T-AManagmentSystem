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
     * Display the analytics dashboard.
     *
     * @return \Illuminate\Http\Response
     */
    public function analytics()
    {
        // Get attendance data for the last 7 days
        $attendanceData = $this->getAttendanceByDay();
        
        // Get department data
        $departmentData = $this->getAttendanceByDepartment();
        
        // Pass the data to the view
        return view('attendance.analytics', compact('attendanceData', 'departmentData'));
    }
    
    /**
     * Get attendance data grouped by day for the last 7 days
     */
    private function getAttendanceByDay()
    {
        // Get the last 7 days
        $days = [];
        $values = [];
        
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $days[] = now()->subDays($i)->format('D, M j'); // Format: Mon, Jan 1
            
            // Count attendance records for this day
            $count = LogInformation::whereDate('punch_date', $date)->count();
            $values[] = $count;
        }
        
        return [
            'labels' => $days,
            'values' => $values
        ];
    }
    
    /**
     * Get attendance data grouped by department
     */
    private function getAttendanceByDepartment()
    {
        // Get attendance counts by department
        $departmentCounts = DB::table('user_information')
            ->join('log_Information', 'user_information.user_id', '=', 'log_Information.user_id')
            ->select('user_information.user_department', DB::raw('count(*) as count'))
            ->whereNotNull('user_information.user_department')
            ->where('user_information.user_department', '!=', '')
            ->groupBy('user_information.user_department')
            ->orderBy('count', 'desc')
            ->get();
        
        $labels = [];
        $values = [];
        
        foreach ($departmentCounts as $dept) {
            $labels[] = $dept->user_department;
            $values[] = $dept->count;
        }
        
        return [
            'labels' => $labels,
            'values' => $values
        ];
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
        
        // Get attendance records with photos for this employee
        $attendances = LogInformation::where('user_id', $userId)
            ->orderBy('date_time_event', 'desc')
            ->paginate(15);
        
        // Get a list of all attendance photos for this employee
        $attendancePhotos = LogInformation::where('user_id', $userId)
            ->whereNotNull('photo_url')
            ->where('photo_url', '!=', '')
            ->select('photo_url', 'date_time_event')
            ->orderBy('date_time_event', 'desc')
            ->get();
        
        // Format the attendance photos
        $formattedPhotos = $attendancePhotos->map(function($item) {
            return [
                'url' => route('images.serve', ['filename' => basename($item->photo_url)]),
                'original_path' => $item->photo_url,
                'date' => \Carbon\Carbon::parse($item->date_time_event)->format('Y-m-d H:i:s')
            ];
        });
        
        return view('attendance', [
            'employee' => $employee,
            'attendances' => $attendances,
            'attendancePhotos' => $formattedPhotos
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
