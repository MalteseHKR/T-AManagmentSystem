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
            $departments = DB::table('departments')->orderBy('department')->get();
            $roles = DB::table('roles')->orderBy('role')->get();

            return view('create', compact('departments', 'roles'));
        } catch (\Exception $e) {
            Log::error('Error fetching data for create employee page: ' . $e->getMessage());
            return view('create', ['departments' => [], 'roles' => []])
                ->with('error', 'Could not load data from database. No departments or job roles available.');
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
            // Begin a database transaction
            DB::beginTransaction();
            
            $roleData = DB::table('roles')->where('role', $validated['job_role'])->first();
            $departmentData = DB::table('departments')->where('department', $validated['department'])->first();

            if (!$roleData || !$departmentData) {
                return redirect()->back()->with('error', 'Invalid role or department selected.')->withInput();
            }

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

            // Create login entry for the new user
            $hashedPassword = bcrypt('garrisonpass!2025');
            
            // Check if the login table structure matches what we're trying to insert
            $loginInsertData = [
                'email' => $validated['email'],
                'user_login_pass' => $hashedPassword,
                'password_reset' => 1,
                'user_id' => $userId
            ];
            
            // Only add these fields if they exist in your table structure
            if (DB::getSchemaBuilder()->hasColumn('login', 'login_attempts')) {
                $loginInsertData['login_attempts'] = 0;
            }
            
            if (DB::getSchemaBuilder()->hasColumn('login', 'last_login')) {
                $loginInsertData['last_login'] = now();
            }
            
            // Insert the login record
            $loginId = DB::table('login')->insertGetId($loginInsertData);
            
            Log::info("Created login account for user: {$validated['email']} (ID: $userId, Login ID: $loginId)");

            // Process image uploads
            if ($request->hasFile('images')) {
                $images = $request->file('images');
                $photoNumber = 1;

                $firstName = preg_replace('/[^A-Za-z0-9]/', '', $validated['name']);
                $lastName = preg_replace('/[^A-Za-z0-9]/', '', $validated['surname']);

                Log::info("START image upload: Found " . count($images) . " image(s)");

                foreach ($images as $index => $image) {
                    if ($image && $image->isValid()) {
                        $extension = strtolower($image->getClientOriginalExtension());
                        if ($extension === 'jfif') $extension = 'jpg';

                        $filename = "{$firstName}{$lastName}Photo{$photoNumber}_{$userId}.{$extension}";
                        $filename = preg_replace('/[^A-Za-z0-9_.-]/', '_', $filename);

                        // Save all images to employee photo storage
                        Storage::disk('local_uploads')->putFileAs('', $image, $filename);

                        // Save first image as profile photo
                        if ($index === 0) {
                            $profilePhotoFilename = "{$firstName}{$lastName}_profile_{$userId}." . $extension;
                            $profilePhotoFilename = preg_replace('/[^A-Za-z0-9_.-]/', '_', $profilePhotoFilename);

                            Storage::disk('profile_photos')->putFileAs('', $image, $profilePhotoFilename);
                            $absolutePath = Storage::disk('profile_photos')->path($profilePhotoFilename);

                            DB::table('user_profile_photo')->insert([
                                'file_name_link' => $absolutePath,
                                'user_id' => $userId
                            ]);
                        }

                        $photoNumber++;
                    }
                }
            }

            // Commit the transaction
            DB::commit();
            
            return redirect()->route('employees')->with('success', 'Employee created successfully with login credentials.');
        } catch (\Exception $e) {
            // Rollback the transaction
            DB::rollBack();
            
            Log::error("Error creating employee: " . $e->getMessage());
            return redirect()->route('create')
                ->with('error', 'Error creating employee: ' . $e->getMessage())
                ->withInput();
        }
    }
}
