<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class CreateEmployeeController extends Controller
{
    public function create()
    {
        try {
            // Get departments from the departments table - NO defaults if empty
            $departments = DB::table('departments')
                ->orderBy('department')
                ->get();
            
            // Get roles from the roles table - NO defaults if empty
            $roles = DB::table('roles')
                ->orderBy('role')
                ->get();
            
            // No default values - if none found, the view will display "Not found" messages
            
            return view('create', compact('departments', 'roles'));
        } catch (\Exception $e) {
            Log::error('Error fetching data for create employee page: ' . $e->getMessage());
            
            // Empty arrays - no defaults
            $departments = [];
            $roles = [];
            
            return view('create', compact('departments', 'roles'))
                ->with('error', 'Could not load data from database. No departments or job roles available.');
        }
    }

    public function store(Request $request)
    {
        // Validation remains the same
        $validated = $request->validate([
            'name' => 'required|string|max:50',
            'surname' => 'required|string|max:50',
            'job_role' => 'required|exists:roles,role',
            'phone_number' => 'required|string|max:50',
            'email' => 'required|email|max:50|unique:user_information,user_email',
            'date_of_birth' => 'required|date',
            'start_date' => 'required|date',
            'department' => 'required|exists:departments,department',
            'active' => 'required|boolean',
            'images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        try {
            // Get the role_id and department_id as before
            $roleData = DB::table('roles')
                ->where('role', $validated['job_role'])
                ->first();
            
            if (!$roleData) {
                return redirect()->back()->with('error', 'Selected job role does not exist.')->withInput();
            }
            
            $departmentData = DB::table('departments')
                ->where('department', $validated['department'])
                ->first();
            
            if (!$departmentData) {
                return redirect()->back()->with('error', 'Selected department does not exist.')->withInput();
            }
            
            // Create employee record first to get the user_id
            $userId = DB::table('user_information')->insertGetId([
                'user_name' => $validated['name'],
                'user_surname' => $validated['surname'],
                'user_title' => $validated['job_role'],
                'user_phone' => $validated['phone_number'],
                'user_email' => $validated['email'],
                'user_dob' => $validated['date_of_birth'],
                'user_job_start' => $validated['start_date'],
                'user_job_end' => null,
                'user_active' => $validated['active'],
                'user_department' => $validated['department'],
                'role_id' => $roleData->role_id,
                'department_id' => $departmentData->department_id
            ]);
            
            // Now that we have the user_id, we can handle the image uploads
            $uploadSuccess = true;
            if ($request->hasFile('images')) {
                $firstName = $validated['name'];
                $lastName = $validated['surname'];
                
                // Remove spaces and special characters from names
                $sanitizedFirstName = preg_replace('/[^A-Za-z0-9]/', '', $firstName);
                $sanitizedLastName = preg_replace('/[^A-Za-z0-9]/', '', $lastName);
                
                $imageFiles = $request->file('images');
                $savedImagePaths = [];
                
                // Make sure the directory exists and is writable
                $storagePath = config('filesystems.disks.employee_photos.root');
                
                // Log the path for debugging
                Log::info("Employee photos storage path: " . $storagePath);
                
                // Ensure directory exists - use filesystem-independent approach
                try {
                    // Use Storage facade to ensure the disk exists
                    if (!Storage::disk('employee_photos')->exists('')) {
                        Storage::disk('employee_photos')->makeDirectory('');
                        Log::info("Created employee photos directory");
                    }
                    
                    foreach ($imageFiles as $index => $image) {
                        if ($image) {
                            // Calculate the image number (1-based index)
                            $imageNumber = $index + 1;
                            
                            // New format: FirstnameLastname{index} {user_id}.{extension}
                            $filename = $sanitizedFirstName . $sanitizedLastName . $imageNumber . ' ' . $userId;
                            
                            // Add original file extension
                            $extension = $image->getClientOriginalExtension();
                            $filename .= '.' . $extension;
                            
                            try {
                                // Store file using the employee_photos disk
                                $path = Storage::disk('employee_photos')->putFileAs('', $image, $filename);
                                
                                // Log success for debugging
                                Log::info("Image uploaded successfully: " . $filename);
                                
                                if ($path) {
                                    $savedImagePaths[] = $path;
                                    
                                    // Check if employee_images table exists
                                    try {
                                        // Store image reference in employee_images table
                                        DB::table('employee_images')->insert([
                                            'employee_id' => $userId,
                                            'image_path' => $filename
                                        ]);
                                    } catch (\Exception $tableException) {
                                        // Log but don't fail the upload if table doesn't exist
                                        Log::warning("Could not insert into employee_images table: " . $tableException->getMessage());
                                    }
                                } else {
                                    $uploadSuccess = false;
                                    Log::error("Failed to save image for employee: " . $validated['email']);
                                }
                            } catch (\Exception $e) {
                                $uploadSuccess = false;
                                Log::error("Exception while saving image: " . $e->getMessage());
                                Log::error("Exception details: " . $e->getTraceAsString());
                            }
                        }
                    }
                } catch (\Exception $e) {
                    $uploadSuccess = false;
                    Log::error("Error working with employee_photos disk: " . $e->getMessage());
                    Log::error("Exception details: " . $e->getTraceAsString());
                }
            }
            
            if ($uploadSuccess) {
                return redirect()->route('employees')
                    ->with('success', 'Employee created successfully');
            } else {
                return redirect()->route('employees')
                    ->with('warning', 'Employee created but some images failed to upload.');
            }
        } catch (\Exception $e) {
            Log::error('Error creating employee: ' . $e->getMessage());
            return redirect()->route('create')
                ->with('error', 'Error creating employee: ' . $e->getMessage())
                ->withInput();
        }
    }
}