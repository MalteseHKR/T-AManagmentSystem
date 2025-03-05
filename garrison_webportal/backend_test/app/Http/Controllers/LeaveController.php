<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class LeaveController extends Controller
{
    public function index(Request $request)
    {
        // Static list of leave requests for demonstration purposes
        $leaveRequests = [
            ['id' => 1, 'employee_name' => 'John Doe', 'leave_type' => 'Sick Leave', 'start_date' => '2025-02-01', 'end_date' => '2025-02-05', 'status' => 'Pending'],
            ['id' => 2, 'employee_name' => 'Jane Smith', 'leave_type' => 'Annual Leave', 'start_date' => '2025-03-10', 'end_date' => '2025-03-20', 'status' => 'Approved'],
            // Add more leave requests as needed
        ];

        // Filter leave requests based on the request parameters
        if ($request->has('employee_name')) {
            $leaveRequests = array_filter($leaveRequests, function ($leaveRequest) use ($request) {
                return stripos($leaveRequest['employee_name'], $request->input('employee_name')) !== false;
            });
        }

        if ($request->has('status') && $request->input('status') !== '') {
            $leaveRequests = array_filter($leaveRequests, function ($leaveRequest) use ($request) {
                return $leaveRequest['status'] === $request->input('status');
            });
        }

        return view('leaves', ['leaveRequests' => $leaveRequests]);
    }
}
