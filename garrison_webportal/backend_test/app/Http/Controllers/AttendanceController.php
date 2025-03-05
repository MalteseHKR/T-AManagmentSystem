<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Attendance;
use App\Models\Employee;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        $query = Attendance::with('employee');

        // Handle sorting
        $sortField = $request->get('sort', 'punch_date');
        $sortDirection = $request->get('direction', 'desc');

        $query->orderBy($sortField, $sortDirection);
        $query->orderBy('punch_time', 'desc');

        $attendances = $query->paginate(10);
            
        return view('attendance', [
            'attendances' => $attendances,
            'sortField' => $sortField,
            'sortDirection' => $sortDirection
        ]);
    }
}
