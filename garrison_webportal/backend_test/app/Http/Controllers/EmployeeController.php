<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use App\Models\UserInformation;
use App\Models\Department;
use App\Models\Role;
use App\Models\Attendance;
use App\Models\LogInformation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class EmployeeController extends Controller
{
    /**
     * Check for the existence of tables - UPDATED to stop attendance warnings
     */
    protected function tableExists($tableName)
    {
        // If someone is checking for 'attendance', silently redirect to log_Information
        if ($tableName === 'attendance') {
            // No need to log anything - just check if log_Information exists
            return Schema::hasTable('log_Information') || Schema::hasTable('log_information');
        }
        
        // For all other tables, normal check
        $exists = Schema::hasTable($tableName);
        
        // Only log for non-attendance tables
        if (!$exists && $tableName !== 'attendance') {
            Log::info("Table check: {$tableName} does not exist");
        }
        
        return $exists;
    }

    public function index(Request $request)
    {
        // Start with a base query that includes department relationship
        $query = UserInformation::with(['department', 'role']);
        
        // Apply name filter if provided
        if ($request->has('name') && $request->name) {
            $query->where('user_name', 'like', '%' . $request->name . '%');
        }
        
        // Apply department filter if provided - using the relationship
        if ($request->has('department') && $request->department) {
            // Join with departments table and filter by department name
            $query->whereHas('department', function($q) use ($request) {
                $q->where('department', $request->department);
            });
        }

        // Clone the query for counting
        $countQuery = clone $query;

        // Get active/inactive counts respecting applied filters
        $activeEmployees = (clone $countQuery)->where('user_active', 1)->count();
        $inactiveEmployees = (clone $countQuery)->where(function($q) {
            $q->where('user_active', 0)
              ->orWhereNull('user_active');
        })->count();
        
        // Get unique departments for the filter dropdown - from departments table
        $departments = Department::orderBy('department')
            ->pluck('department')
            ->toArray();
        
        // Get the users with pagination
        $userInformation = $query->paginate(15);
        
        // Fix the photo loading - be more explicit about the structure
        try {
            $userIds = [];
            foreach ($userInformation as $user) {
                $userIds[] = $user->user_id;
            }
            
            // Get photos for all users on this page
            $photos = DB::table('user_profile_photo')
                ->whereIn('user_id', $userIds)
                ->get();
                
            // Create a lookup array for quick access
            $photosByUserId = [];
            foreach ($photos as $photo) {
                $photosByUserId[$photo->user_id] = $photo;
            }
            
            // Attach photos to each user
            foreach ($userInformation as $user) {
                $user->photo = $photosByUserId[$user->user_id] ?? null;
            }
            
            // Log photo debug info
            Log::info('Photos loaded for employees', [
                'total_photos_found' => count($photos),
                'user_ids' => $userIds,
                'first_photo_path' => $photos->isNotEmpty() ? $photos->first()->file_name_link : 'none'
            ]);
        } catch (\Exception $e) {
            Log::error('Error loading employee photos: ' . $e->getMessage());
        }
        
        // Log what we're passing to the view
        Log::info('Employees data for view', [
            'employee_count' => $userInformation->total(),
            'department_count' => count($departments),
            'active_employees' => $activeEmployees,
            'inactive_employees' => $inactiveEmployees,
            'photos_loaded' => count($photos)
        ]);
        
        return view('employees', compact(
            'userInformation', 
            'departments',
            'activeEmployees',
            'inactiveEmployees'
        ));
    }

    public function show($id)
    {
        // Find the user information by user_id with related data
        $userInfo = UserInformation::with(['department', 'role'])
            ->where('user_id', $id)
            ->firstOrFail();

        // Attach profile photo path from user_profile_photo table (if available)
        $photo = DB::table('user_profile_photo')
            ->where('user_id', $userInfo->user_id)
            ->first();

        $userInfo->portrait_url = $photo?->file_name_link ?? null;

        // FIX: Only check for log_Information table
        try {
            // Only check for the correct table we know exists
            $tableFound = $this->tableExists('log_Information') || $this->tableExists('log_information');
            
            if (!$tableFound) {
                Log::info("Log_Information table not found - checking for alternative tables");
                $attendanceRecords = collect();
            } else {
                // Get the actual table name with correct case
                $tableName = $this->tableExists('log_Information') ? 'log_Information' : 'log_information';
                
                Log::info("Using table: {$tableName} for attendance records");
                
                // Query table for this employee's records
                $attendanceRecords = DB::table($tableName)
                    ->where('user_id', $userInfo->user_id)
                    ->orderBy('date_time_event', 'desc')
                    ->limit(10)
                    ->get();
                    
                Log::info('Found attendance records in show()', [
                    'count' => $attendanceRecords->count(),
                    'first_record' => $attendanceRecords->first()
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error fetching attendance records in show(): ' . $e->getMessage());
            $attendanceRecords = collect();
        }

        // Pass everything to the view
        return view('employees.employee_profile', compact('userInfo', 'attendanceRecords'));
    }

    /**
     * Display the profile for a specific employee.
     *
     * @param int $id
     * @return \Illuminate\View\View
     */
    public function profile($id)
    {
        // Get user information
        $userInfo = UserInformation::with(['department', 'role'])
            ->where('user_id', $id)
            ->first();
        
        if (!$userInfo) {
            return redirect()->route('employees')->with('error', 'Employee not found.');
        }
        
        // Attach profile photo path from user_profile_photo table (if available)
        $photo = DB::table('user_profile_photo')
            ->where('user_id', $userInfo->user_id)
            ->first();

        $userInfo->portrait_url = $photo?->file_name_link ?? null;
        
        // Add debugging
        Log::info('Fetching attendance records for employee', ['user_id' => $id]);
        
        try {
            // DIRECTLY query log_Information table instead of checking if it exists
            $attendanceRecords = DB::table('log_Information')
                ->where('user_id', $id)
                ->orderBy('date_time_event', 'desc')
                ->limit(10)
                ->get();
                
            Log::info('Retrieved attendance records', [
                'count' => $attendanceRecords->count(),
                'first_record' => $attendanceRecords->first()
            ]);
        } catch (\Exception $e) {
            // Only log the actual error, don't mention "attendance table"
            Log::error('Error fetching log_Information records: ' . $e->getMessage());
            $attendanceRecords = collect([]);
        }
        
        // Pass everything to the view
        return view('employees.employee_profile', compact('userInfo', 'attendanceRecords'));
    }

    /**
     * Show the form for creating a new employee.
     */
    public function create()
    {
        // Get departments and roles for dropdown options
        $departments = Department::orderBy('department')->get();
        $roles = Role::orderBy('role')->get();
        
        // Change the view path from 'create' to 'employees.create'
        return view('employees.create', compact('departments', 'roles')); 
    }

    /**
     * Show the form for editing an employee
     */
    public function edit($id)
    {
        // Find the user information
        $userInfo = UserInformation::with(['department', 'role'])
            ->where('user_id', $id)
            ->firstOrFail();
        
        // Get all departments and roles for dropdowns
        $departments = Department::orderBy('department')->get();
        $roles = Role::orderBy('role')->get();
        
        return view('employees.edit', compact('userInfo', 'departments', 'roles'));
    }

    /**
     * Update the employee information
     */



public function update(Request $request, $id)
{
    $validated = $request->validate([
        'user_name' => 'required|string|max:255',
        'user_surname' => 'required|string|max:255',
        'user_email' => 'required|email|max:255',
        'user_phone' => 'nullable|string|max:20',
        'department_id' => 'required|exists:departments,department_id',
        'role_id' => 'required|exists:roles,role_id',
        'user_active' => 'boolean',
        'ai_image_1' => 'nullable|image|mimes:jpeg,jpg,png,gif,jfif|max:2048',
        'ai_image_2' => 'nullable|image|mimes:jpeg,jpg,png,gif,jfif|max:2048',
        'ai_image_3' => 'nullable|image|mimes:jpeg,jpg,png,gif,jfif|max:2048',
    ]);

    $userInfo = UserInformation::where('user_id', $id)->firstOrFail();
    $userInfo->update($validated);

    $firstName = preg_replace('/[^A-Za-z0-9]/', '', $validated['user_name']);
    $lastName = preg_replace('/[^A-Za-z0-9]/', '', $validated['user_surname']);
    $photoNumber = 1;

    foreach ([1, 2, 3] as $index) {
        $field = "ai_image_{$index}";
        if ($request->hasFile($field) && $request->file($field)->isValid()) {
            $image = $request->file($field);
            $extension = strtolower($image->getClientOriginalExtension());
            if ($extension === 'jfif') $extension = 'jpg';

            $filename = "{$firstName}_{$lastName}{$photoNumber}_{$id}.{$extension}";
            $filename = preg_replace('/[^A-Za-z0-9_.-]/', '_', $filename);

            try {
                Storage::disk('local_uploads')->putFileAs('', $image, $filename);
                Log::info("? Uploaded image to local_uploads: $filename");

                // Save first valid image as profile photo
                if ($photoNumber === 1) {
                    $profileName = "{$firstName}{$lastName}_profile_{$id}.{$extension}";
                    $profileName = preg_replace('/[^A-Za-z0-9_.-]/', '_', $profileName);

                    Storage::disk('profile_photos')->putFileAs('', $image, $profileName);
                    Log::info("? Uploaded profile image to profile_photos: $profileName");

                    $absolutePath = Storage::disk('profile_photos')->path($profileName);

                    DB::table('user_profile_photo')->updateOrInsert(
                        ['user_id' => $id],
                        ['file_name_link' => $absolutePath]
                    );
                }

                $photoNumber++;
            } catch (\Exception $e) {
                Log::error("? Error uploading or saving image: " . $e->getMessage());
            }
        }
    }

    return redirect()
        ->route('employee.profile', $id)
        ->with('success', 'Employee information updated successfully.');
}

}