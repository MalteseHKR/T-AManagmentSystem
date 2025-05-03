<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Models\Department;
use App\Models\Role;

class CreateEmployeeController extends Controller
{
    public function create()
    {
        try {
            // Get departments and roles for dropdown options
            $departments = Department::orderBy('department')->get();
            $roles = Role::orderBy('role')->get();
            
            // Log what we're passing to the view
            Log::info('Loading create employee form', [
                'departments_count' => $departments->count(),
                'roles_count' => $roles->count()
            ]);
            
            // Change 'create' to 'employees.create' to match the file's actual location
            return view('employees.create', compact('departments', 'roles'));
        } catch (\Exception $e) {
            Log::error('Error fetching data for create employee page: ' . $e->getMessage());
            return redirect()->route('employees')->with('error', 'Could not load the create employee form');
        }
    }

    public function store(Request $request)
    {
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
            'images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,jfif|max:2048'
        ]);

        try {
            $roleData = DB::table('roles')->where('role', $validated['job_role'])->first();
            $departmentData = DB::table('departments')->where('department', $validated['department'])->first();

            if (!$roleData || !$departmentData) {
                return redirect()->back()->with('error', 'Invalid role or department selected.')->withInput();
            }

            $userId = DB::table('user_information')->insertGetId([
                'user_name' => $validated['name'],
                'user_surname' => $validated['surname'],
                'user_phone' => $validated['phone_number'],
                'user_email' => $validated['email'],
                'user_dob' => $validated['date_of_birth'],
                'user_job_start' => $validated['start_date'],
                'user_job_end' => null,
                'user_active' => $validated['active'],
                'role_id' => $roleData->role_id,
                'department_id' => $departmentData->department_id
            ]);

            // Create login entry for the new user with properly hashed password
            try {
                // Generate a secure hash of the default password
                $hashedPassword = bcrypt('garrisonpass!2025');
                
                DB::table('login')->insert([
                    'email' => $validated['email'],
                    'user_login_pass' => $hashedPassword, // Store hashed password, not plaintext
                    'password_reset' => 1, // User must reset password on first login
                    'user_id' => $userId,
                    'login_attempts' => 0, // Initialize login attempts counter
                    'last_login_attempt' => now() // Use current timestamp instead of null
                ]);
                
                Log::info("Created login account for user: {$validated['email']} (ID: $userId)");
            } catch (\Exception $e) {
                Log::error("Failed to create login account: " . $e->getMessage());
                // Continue with the process even if login creation fails
            }

            if ($request->hasFile('images')) {
                $images = $request->file('images');
                $photoNumber = 1;

                $firstName = preg_replace('/[^A-Za-z0-9]/', '', $validated['name']);
                $lastName = preg_replace('/[^A-Za-z0-9]/', '', $validated['surname']);

                Log::info("START image upload: Found " . count($images) . " image(s)");

                foreach ($images as $index => $image) {
                    if ($image && $image->isValid()) {
                        Log::info("Processing image index: $index");

                        $extension = strtolower($image->getClientOriginalExtension());
                        if ($extension === 'jfif') $extension = 'jpg';

                        $filename = "{$firstName}_{$lastName}{$photoNumber}_{$userId}.{$extension}";
                        $filename = preg_replace('/[^A-Za-z0-9_.-]/', '_', $filename);

                        try {
                            // Save all images to employee photo storage
                            Storage::disk('local_uploads')->putFileAs('', $image, $filename);
                            Log::info("? Saved to local_uploads: $filename");

                            // Save first image as profile photo
                            if ($index === 0) {
                                $profilePhotoFilename = "{$firstName}{$lastName}_profile_{$userId}." . $extension;
                                $profilePhotoFilename = preg_replace('/[^A-Za-z0-9_.-]/', '_', $profilePhotoFilename);

                                Storage::disk('profile_photos')->putFileAs('', $image, $profilePhotoFilename);
                                Log::info("? Saved to profile_photos: $profilePhotoFilename");

                                $absolutePath = Storage::disk('profile_photos')->path($profilePhotoFilename);

                                DB::table('user_profile_photo')->insert([
                                    'file_name_link' => $absolutePath,
                                    'user_id' => $userId
                                ]);

                                Log::info("? Inserted full profile photo path for user_id: $userId");
                            }

                            $photoNumber++;
                        } catch (\Exception $e) {
                            Log::error("? Error saving image or inserting DB: " . $e->getMessage());
                        }
                    } else {
                        Log::warning("? Invalid or empty image at index: $index");
                    }
                }
            }

            return redirect()->route('employees')->with('success', 'Employee created successfully.');
        } catch (\Exception $e) {
            Log::error("? Error creating employee: " . $e->getMessage());
            return redirect()->route('employees.create') // ? Correct route name
        	->with('error', 'Something went wrong while creating the employee.')
        	->withInput();
	}
    }
}
