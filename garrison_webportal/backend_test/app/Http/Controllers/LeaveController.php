<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\UserInformation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LeaveController extends Controller
{
    public function index(Request $request)
    {
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

        if ($request->has('employee_name') && !empty($request->input('employee_name'))) {
            $query->where('user_information.user_name', 'LIKE', '%' . $request->input('employee_name') . '%');
        }

        if ($request->has('status') && !empty($request->input('status'))) {
            $query->where('leave_requests.status', $request->input('status'));
        }

        if ($request->has('leave_type') && !empty($request->input('leave_type'))) {
            $query->where('leave_requests.leave_type_id', $request->input('leave_type'));
        }

        if ($request->has('start_date') && !empty($request->input('start_date'))) {
            $query->where('leave_requests.start_date', '>=', $request->input('start_date'));
        }

        if ($request->has('end_date') && !empty($request->input('end_date'))) {
            $query->where('leave_requests.end_date', '<=', $request->input('end_date'));
        }

        $leaveTypes = LeaveType::all();
        $leaveRequests = $query->orderBy('leave_requests.created_at', 'desc')->paginate(15);

        $pendingCount = LeaveRequest::where('status', 'PENDING')->count();
        $approvedCount = LeaveRequest::where('status', 'APPROVED')->count();
        $rejectedCount = LeaveRequest::where('status', 'REJECTED')->count();
        $totalCount = LeaveRequest::count();

        return view('leaves', compact(
            'leaveRequests',
            'leaveTypes',
            'pendingCount',
            'approvedCount',
            'rejectedCount',
            'totalCount'
        ));
    }

    public function create()
    {
        $leaveTypes = LeaveType::all();
        $employees = UserInformation::all();

        return view('leaves.create', compact('leaveTypes', 'employees'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:user_information,user_id',
            'leave_type_id' => 'required|exists:leave_types,leave_type_id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'nullable|string|max:500',
            'medical_certificate' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:5120',
            'admin_notes' => 'nullable|string|max:500',
            'is_full_day' => 'nullable|boolean',
            'start_time' => 'nullable|date_format:H:i|required_if:is_full_day,0',
            'end_time' => 'nullable|date_format:H:i|required_if:is_full_day,0|after:start_time',
        ]);

        $certificatePath = null;
        if ($request->hasFile('medical_certificate')) {
            $file = $request->file('medical_certificate');
            $userId = $validated['user_id'];
            $startDate = date('Ymd', strtotime($validated['start_date']));
            $endDate = date('Ymd', strtotime($validated['end_date']));
            $extension = $file->getClientOriginalExtension();
            $safeFilename = "{$userId}_{$startDate}_{$endDate}." . strtolower($extension);
            $destinationPath = '/home/softwaredev/garrison-app-server/uploads/certificates';
            if (!file_exists($destinationPath)) {
                mkdir($destinationPath, 0775, true);
            }
            $file->move($destinationPath, $safeFilename);
            $certificatePath = $safeFilename;
        }

        DB::table('leave_requests')->insert([
            'user_id' => $validated['user_id'],
            'leave_type_id' => $validated['leave_type_id'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'reason' => $validated['reason'],
            'status' => 'PENDING',
            'medical_certificate' => $certificatePath,
            'admin_notes' => $request->has('admin_notes') ? $validated['admin_notes'] : null,
            'is_full_day' => $request->has('is_full_day') ? $validated['is_full_day'] : 1,
            'start_time' => $request->has('start_time') ? $validated['start_time'] : null,
            'end_time' => $request->has('end_time') ? $validated['end_time'] : null,
            'request_date' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('leaves')->with('success', 'Leave request submitted successfully.');
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:APPROVED,REJECTED',
            'admin_notes' => 'nullable|string|max:500',
        ]);

        $leaveRequest = LeaveRequest::findOrFail($id);
        $currentUser = Auth::user();

        Log::info("Comparing user IDs: Auth user ID = {$currentUser->id}, Request user ID = {$leaveRequest->user_id}");

        if ((string)$currentUser->id === (string)$leaveRequest->user_id) {
            Log::info("Attempt to approve own request rejected");
            return redirect()->route('leaves')
                ->with('error', 'You cannot approve or reject your own leave request. This action requires approval from another authorized user.');
        }

        $leaveRequest->status = $request->status;
        $leaveRequest->admin_notes = $request->admin_notes;
        $leaveRequest->save();

        $statusText = $request->status == 'APPROVED' ? 'approved' : 'rejected';
        Log::info("Leave request #{$id} updated to {$request->status} by user #{$currentUser->id}");

        return redirect()->route('leaves')
            ->with('success', "Leave request has been successfully {$statusText}.");
    }

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

    public function edit($id)
    {
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

        $leaveTypes = DB::table('leave_types')->get();
        $employees = UserInformation::all();

        return view('leaves.edit', [
            'leave' => $leave,
            'leaveTypes' => $leaveTypes,
            'employees' => $employees
        ]);
    }

    public function update(Request $request, $id)
    {
        $currentUserId = Auth::id();

        $validated = $request->validate([
            'user_id' => 'required|exists:user_information,user_id',
            'leave_type_id' => 'required|exists:leave_types,leave_type_id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'nullable|string|max:500',
            'medical_certificate' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:5120',
            'admin_notes' => 'nullable|string|max:500',
            'is_full_day' => 'nullable|boolean',
            'start_time' => 'nullable|date_format:H:i|required_if:is_full_day,0',
            'end_time' => 'nullable|date_format:H:i|required_if:is_full_day,0|after:start_time',
            'status' => 'required|in:pending,approved,rejected',
        ]);

        try {
            $leave = DB::table('leave_requests')->where('request_id', $id)->first();

            if (!$leave) {
                return redirect()->route('leaves')->with('error', 'Leave request not found.');
            }

            Log::info("Leave Edit - Comparing user IDs: Current user ID = " . $currentUserId . ", Leave user ID = " . $leave->user_id);

            $status = strtoupper($validated['status']);
            if ($currentUserId == $leave->user_id) {
                Log::info("User attempted to change own request status to: " . $status . " - Forcing to PENDING");
                $status = 'PENDING';
            }

            $certificatePath = $leave->medical_certificate;
            $destinationPath = '/home/softwaredev/garrison-app-server/uploads/certificates';

            if ($request->has('remove_certificate') && $certificatePath) {
                $fullPath = $destinationPath . '/' . $certificatePath;
                if (file_exists($fullPath)) {
                    unlink($fullPath);
                }
                $certificatePath = null;
            }

            if ($request->hasFile('medical_certificate')) {
                if ($certificatePath) {
                    $oldPath = $destinationPath . '/' . $certificatePath;
                    if (file_exists($oldPath)) {
                        unlink($oldPath);
                    }
                }

                $file = $request->file('medical_certificate');
                $userId = $validated['user_id'];
                $startDate = date('Ymd', strtotime($validated['start_date']));
                $endDate = date('Ymd', strtotime($validated['end_date']));
                $extension = $file->getClientOriginalExtension();
                $safeFilename = "{$userId}_{$startDate}_{$endDate}." . strtolower($extension);

                if (!file_exists($destinationPath)) {
                    mkdir($destinationPath, 0775, true);
                }

                $file->move($destinationPath, $safeFilename);
                $certificatePath = $safeFilename;
            }

            DB::table('leave_requests')
                ->where('request_id', $id)
                ->update([
                    'user_id' => $validated['user_id'],
                    'leave_type_id' => $validated['leave_type_id'],
                    'start_date' => $validated['start_date'],
                    'end_date' => $validated['end_date'],
                    'reason' => $validated['reason'],
                    'status' => $status,
                    'medical_certificate' => $certificatePath,
                    'admin_notes' => $request->has('admin_notes') ? $validated['admin_notes'] : null,
                    'is_full_day' => $request->has('is_full_day') ? $validated['is_full_day'] : 1,
                    'start_time' => $request->has('start_time') ? $validated['start_time'] : null,
                    'end_time' => $request->has('end_time') ? $validated['end_time'] : null,
                    'updated_at' => now(),
                ]);

            return redirect()->route('leaves')->with('success', 'Leave request updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error updating leave request: ' . $e->getMessage())->withInput();
        }
    }
}
