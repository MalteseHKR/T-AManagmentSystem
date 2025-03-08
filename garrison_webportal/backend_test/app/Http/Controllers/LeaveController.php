<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\UserInformation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LeaveController extends Controller
{
    /**
     * Display a listing of the leave requests.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        // Base query
        $query = LeaveRequest::query()
            ->join('user_information', 'leave_requests.user_id', '=', 'user_information.user_id')
            ->join('leave_types', 'leave_requests.leave_type_id', '=', 'leave_types.leave_type_id')
            ->select(
                'leave_requests.*', 
                'user_information.user_name', 
                'leave_types.leave_type_name'
            );
        
        // Filter by employee name if provided
        if ($request->has('employee_name') && !empty($request->input('employee_name'))) {
            $query->where('user_information.user_name', 'LIKE', '%' . $request->input('employee_name') . '%');
        }
        
        // Filter by status if provided
        if ($request->has('status') && !empty($request->input('status'))) {
            $query->where('leave_requests.status', $request->input('status'));
        }
        
        // Filter by leave type if provided
        if ($request->has('leave_type') && !empty($request->input('leave_type'))) {
            $query->where('leave_requests.leave_type_id', $request->input('leave_type'));
        }
        
        // Filter by date range if provided
        if ($request->has('start_date') && !empty($request->input('start_date'))) {
            $query->where('leave_requests.start_date', '>=', $request->input('start_date'));
        }
        
        if ($request->has('end_date') && !empty($request->input('end_date'))) {
            $query->where('leave_requests.end_date', '<=', $request->input('end_date'));
        }
        
        // Get all leave types for the filter dropdown
        $leaveTypes = LeaveType::all();
        
        // Get leave requests with pagination
        $leaveRequests = $query->orderBy('leave_requests.created_at', 'desc')->paginate(15);
        
        return view('leaves', compact('leaveRequests', 'leaveTypes'));
    }
    
    /**
     * Show the form for creating a new leave request.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $leaveTypes = LeaveType::all();
        $employees = UserInformation::all();
        
        return view('leaves.create', compact('leaveTypes', 'employees'));
    }
    
    /**
     * Store a newly created leave request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // Validate the request
        $request->validate([
            'user_id' => 'required|exists:user_information,user_id',
            'leave_type_id' => 'required|exists:leave_types,leave_type_id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'nullable|string|max:255',
        ]);
        
        // Create the leave request
        LeaveRequest::create([
            'user_id' => $request->user_id,
            'leave_type_id' => $request->leave_type_id,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'reason' => $request->reason,
            'status' => 'PENDING', // Changed to uppercase
        ]);
        
        return redirect()->route('leaves')->with('success', 'Leave request created successfully.');
    }
    
    /**
     * Update the specified leave request status.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:APPROVED,REJECTED', // Changed to uppercase
        ]);
        
        $leaveRequest = LeaveRequest::findOrFail($id);
        $leaveRequest->status = $request->status;
        $leaveRequest->save();
        
        return redirect()->route('leaves')->with('success', 'Leave request status updated to ' . ucfirst(strtolower($request->status)));
    }
    
    /**
     * Get leave statistics for dashboard.
     *
     * @return \Illuminate\Http\Response
     */
    public function getStatistics()
    {
        $stats = [
            'pending' => LeaveRequest::where('status', 'PENDING')->count(),
            'approved' => LeaveRequest::where('status', 'APPROVED')->count(),
            'rejected' => LeaveRequest::where('status', 'REJECTED')->count(),
            'total' => LeaveRequest::count(),
        ];
        
        $leaveTypeStats = DB::table('leave_requests')
            ->join('leave_types', 'leave_requests.leave_type_id', '=', 'leave_types.leave_type_id')
            ->select('leave_types.leave_type_name', DB::raw('count(*) as count'))
            ->groupBy('leave_types.leave_type_name')
            ->get();
            
        return view('leaves.statistics', compact('stats', 'leaveTypeStats'));
    }
}
