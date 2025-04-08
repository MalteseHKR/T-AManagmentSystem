<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LogInformation;
use App\Models\UserInformation;
use App\Models\Device;
use App\Models\Department;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

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
     * Get attendance data grouped by department - UPDATED for normalized schema
     */
    private function getAttendanceByDepartment()
    {
        try {
            // Get attendance counts by department using the normalized schema
            $departmentCounts = DB::table('log_Information')
                ->join('user_information', 'log_Information.user_id', '=', 'user_information.user_id')
                ->join('departments', 'user_information.department_id', '=', 'departments.department_id')
                ->select('departments.department', DB::raw('count(*) as count'))
                ->whereNotNull('departments.department')
                ->groupBy('departments.department')
                ->orderBy('count', 'desc')
                ->get();
            
            $labels = [];
            $values = [];
            
            foreach ($departmentCounts as $dept) {
                $labels[] = $dept->department;
                $values[] = $dept->count;
            }
            
            return [
                'labels' => $labels,
                'values' => $values
            ];
        } catch (\Exception $e) {
            Log::error('Error in getAttendanceByDepartment: ' . $e->getMessage());
            
            // Return empty data if there's an error
            return [
                'labels' => [],
                'values' => []
            ];
        }
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
        
        return view('attendance.employee', [
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
        
        return view('attendance.dashboard', compact(
            'todayCount', 'presentToday', 'yesterdayCount', 'monthlyTrend'
        ));
    }

    /**
     * Update any method that checks for attendance table
     */
    private function getAttendanceData()
    {
        // Instead of checking for 'attendance' table
        if (!Schema::hasTable('log_Information') && !Schema::hasTable('log_information')) {
            Log::warning('Log_Information table not found in database');
            return collect();
        }
        
        // Use the correct table
        $tableName = Schema::hasTable('log_Information') ? 'log_Information' : 'log_information';
        
        // Continue with your query
        return DB::table($tableName)
            // rest of your query
            ->get();
    }
}
