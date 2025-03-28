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
        // Validation
        $validated = $request->validate([
            'name' => 'required|string|max:50',
            'surname' => 'required|string|max:50',
            'job_role' => 'required|exists:roles,role', // Validate role exists in roles table
            'phone_number' => 'required|string|max:50',
            'email' => 'required|email|max:50|unique:user_information,user_email',
            'date_of_birth' => 'required|date',
            'start_date' => 'required|date',
            'department' => 'required|exists:departments,department', // Validate department exists
            'active' => 'required|boolean',
            'images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        try {
            // Get the role_id from the roles table based on the selected role name
            $roleData = DB::table('roles')
                ->where('role', $validated['job_role'])
                ->first();
            
            if (!$roleData) {
                return redirect()->back()->with('error', 'Selected job role does not exist.')->withInput();
            }
            
            // Get the department_id from the departments table based on the selected department name
            $departmentData = DB::table('departments')
                ->where('department', $validated['department'])
                ->first();
            
            if (!$departmentData) {
                return redirect()->back()->with('error', 'Selected department does not exist.')->withInput();
            }
            
            // Create employee record directly in user_information table
            // Store both the text values (for backwards compatibility) and the IDs (for the new normalized structure)
            $userId = DB::table('user_information')->insertGetId([
                'user_name' => $validated['name'],
                'user_surname' => $validated['surname'],
                'user_title' => $validated['job_role'],    // Keep storing for backwards compatibility
                'user_phone' => $validated['phone_number'],
                'user_email' => $validated['email'],
                'user_dob' => $validated['date_of_birth'],
                'user_job_start' => $validated['start_date'],
                'user_job_end' => null,
                'user_active' => $validated['active'],
                'user_department' => $validated['department'], // Keep storing for backwards compatibility
                'role_id' => $roleData->role_id,           // New FK relation
                'department_id' => $departmentData->department_id, // New FK relation
            ]);

            // Handle image uploads
            $uploadSuccess = true;
            if ($request->hasFile('images')) {
                $firstName = $validated['name'];
                $lastName = $validated['surname'];
                $firstNameInitial = strtoupper(substr($firstName, 0, 1));
                $lastNameInitial = strtoupper(substr($lastName, 0, 1));
                $currentDate = now();
                $monthDay = $currentDate->format('n/j'); // Month without leading zero / Day
                
                $imageFiles = $request->file('images');
                $savedImagePaths = [];
                
                foreach ($imageFiles as $index => $image) {
                    if ($image) {
                        $imageNumber = $index + 1;
                        
                        // Format: "FirstName LastName(Image Number) (SurnameInitial FirstnameInitial)(m/dd)"
                        $filename = $firstName . ' ' . $lastName . '(' . $imageNumber . ') ' . 
                                    '(' . $lastNameInitial . $firstNameInitial . ')(' . $monthDay . ')';
                        
                        // Add original file extension
                        $extension = $image->getClientOriginalExtension();
                        $filename .= '.' . $extension;
                        
                        try {
                            // Store file using the employee_photos disk
                            $path = Storage::disk('employee_photos')->putFileAs('', $image, $filename);
                            
                            if ($path) {
                                $savedImagePaths[] = $path;
                                
                                // Optionally store image reference in a separate table
                                // DB::table('employee_images')->insert([
                                //     'employee_id' => $userId,
                                //     'image_path' => $filename,
                                //     'created_at' => now(),
                                //     'updated_at' => now()
                                // ]);
                            } else {
                                $uploadSuccess = false;
                                Log::error("Failed to save image for employee: " . $validated['email']);
                            }
                        } catch (\Exception $e) {
                            $uploadSuccess = false;
                            Log::error("Exception while saving image: " . $e->getMessage());
                        }
                    }
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