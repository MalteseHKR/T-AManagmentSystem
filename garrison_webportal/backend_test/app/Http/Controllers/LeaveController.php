<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\UserInformation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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
        $query = DB::table('leave_requests')
            ->join('user_information', 'leave_requests.user_id', '=', 'user_information.user_id')
            ->join('leave_types', 'leave_requests.leave_type_id', '=', 'leave_types.leave_type_id')
            ->select(
                'leave_requests.*', 
                'user_information.user_name', 
                'user_information.user_surname',
                'leave_types.leave_type_name',
                'leave_requests.medical_certificate'
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

        // Calculate leave statistics
        $pendingCount = LeaveRequest::where('status', 'PENDING')->count();
        $approvedCount = LeaveRequest::where('status', 'APPROVED')->count();
        $rejectedCount = LeaveRequest::where('status', 'REJECTED')->count();
        $totalCount = LeaveRequest::count();

        // Pass data to the view
        return view('leaves', compact(
            'leaveRequests', 
            'leaveTypes', 
            'pendingCount', 
            'approvedCount', 
            'rejectedCount', 
            'totalCount'
        ));
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
        $validated = $request->validate([
            'user_id' => 'required|exists:user_information,user_id',
            'leave_type_id' => 'required|exists:leave_types,leave_type_id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'nullable|string|max:500',
            'medical_certificate' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:5120', // 5MB max
        ]);

        // Handle file upload if it's a sick leave
        $certificatePath = null;
        if ($request->hasFile('medical_certificate')) {
            $certificatePath = $request->file('medical_certificate')->store('certificates', 'public');
            $certificatePath = basename($certificatePath); // Store only the filename
        }

        // Create the leave request
        DB::table('leave_requests')->insert([
            'user_id' => $validated['user_id'],
            'leave_type_id' => $validated['leave_type_id'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'reason' => $validated['reason'],
            'status' => 'PENDING',
            'medical_certificate' => $certificatePath,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('leaves')->with('success', 'Leave request submitted successfully.');
    }
    
    /**
     * Update the status of a leave request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:APPROVED,REJECTED',
        ]);
        
        // Get the leave request
        $leaveRequest = LeaveRequest::findOrFail($id);
        
        // Get the currently authenticated user
        $currentUser = Auth::user();
        
        // Check the exact user IDs being compared for debugging
        Log::info("Comparing user IDs: Auth user ID = {$currentUser->id}, Request user ID = {$leaveRequest->user_id}");
        
        // Check if the current user is trying to approve/reject their own request
        if ((string)$currentUser->id === (string)$leaveRequest->user_id) {
            Log::info("Attempt to approve own request rejected");
            return redirect()->route('leaves')
                ->with('error', 'You cannot approve or reject your own leave request. This action requires approval from another authorized user.');
        }
        
        // If we got here, the user is not the owner of the request
        $leaveRequest->status = $request->status;
        $leaveRequest->save();
        
        $statusText = $request->status == 'APPROVED' ? 'approved' : 'rejected';
        Log::info("Leave request #{$id} updated to {$request->status} by user #{$currentUser->id}");
        
        // Return with an explicit success message
        return redirect()->route('leaves')
            ->with('success', "Leave request has been successfully {$statusText}.");
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

    /**
     * Show the form for editing the specified leave request.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        // Get the leave request
        $leave = DB::table('leave_requests')
            ->join('leave_types', 'leave_requests.leave_type_id', '=', 'leave_types.leave_type_id')
            ->join('user_information', 'leave_requests.user_id', '=', 'user_information.user_id')
            ->where('leave_requests.request_id', $id)
            ->select(
                'leave_requests.*',
                'leave_types.leave_type_name as leave_type_name',
                'user_information.user_name',
                'user_information.user_surname'
            )
            ->first();
        
        if (!$leave) {
            return redirect()->route('leaves')->with('error', 'Leave request not found.');
        }
        
        // Get leave types for dropdown
        $leaveTypes = DB::table('leave_types')->get();
        
        // Get employees for dropdown
        $employees = UserInformation::all();
        
        return view('leaves.edit', [
            'leave' => $leave,
            'leaveTypes' => $leaveTypes,
            'employees' => $employees
        ]);
    }

    /**
     * Update the specified leave request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        // Get the current user ID - use one of these approaches:
        
        // Option 1: Get directly from Auth
        $currentUserId = Auth::id();
        
        // Option 2: If you're logged in with email instead
        // $userEmail = Auth::user()->email;
        // $userInfo = DB::table('user_information')
        //    ->where('user_email', $userEmail)
        //    ->first();
        // $currentUserId = $userInfo ? $userInfo->user_id : null;
        
        // Validate the request
        $validated = $request->validate([
            'user_id' => 'required|exists:user_information,user_id',
            'leave_type_id' => 'required|exists:leave_types,leave_type_id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'nullable|string|max:500',
            'medical_certificate' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:5120', // 5MB max
            'status' => 'required|in:pending,approved,rejected',
        ]);

        try {
            // Find the leave request
            $leave = DB::table('leave_requests')->where('request_id', $id)->first();
            
            if (!$leave) {
                return redirect()->route('leaves')->with('error', 'Leave request not found.');
            }
            
            // Add debugging to check IDs
            Log::info("Leave Edit - Comparing user IDs: Current user ID = " . $currentUserId . 
                      ", Leave user ID = " . $leave->user_id);
            
            // Force status to remain PENDING if user is editing their own request
            $status = strtoupper($validated['status']);
            if ($currentUserId == $leave->user_id) {
                Log::info("User attempted to change own request status to: " . $status . " - Forcing to PENDING");
                $status = 'PENDING'; // Force status to remain PENDING
            } else {
                Log::info("Admin/other user changing status to: " . $status);
            }
            
            // Handle file upload if needed
            $certificatePath = $leave->medical_certificate;
            
            // If removing certificate is checked
            if ($request->has('remove_certificate')) {
                // Delete the old file if it exists
                if ($certificatePath && Storage::disk('public')->exists('certificates/' . $certificatePath)) {
                    Storage::disk('public')->delete('certificates/' . $certificatePath);
                }
                $certificatePath = null;
            }
            
            // If a new file is uploaded
            if ($request->hasFile('medical_certificate')) {
                // Delete the old file if it exists
                if ($certificatePath && Storage::disk('public')->exists('certificates/' . $certificatePath)) {
                    Storage::disk('public')->delete('certificates/' . $certificatePath);
                }
                
                // Upload the new file
                $certificatePath = $request->file('medical_certificate')->store('certificates', 'public');
                $certificatePath = basename($certificatePath); // Store only the filename
            }
            
            // Update the leave request
            DB::table('leave_requests')
                ->where('request_id', $id)
                ->update([
                    'user_id' => $validated['user_id'],
                    'leave_type_id' => $validated['leave_type_id'],
                    'start_date' => $validated['start_date'],
                    'end_date' => $validated['end_date'],
                    'reason' => $validated['reason'],
                    'status' => $status, // Use our modified status
                    'medical_certificate' => $certificatePath,
                    'updated_at' => now(),
                ]);
            
            return redirect()->route('leaves')->with('success', 'Leave request updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error updating leave request: ' . $e->getMessage())->withInput();
        }
    }
}
