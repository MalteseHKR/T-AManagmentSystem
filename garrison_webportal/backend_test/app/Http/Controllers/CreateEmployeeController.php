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
            // Get unique departments from the user_information table (note the underscore)
            $departments = DB::table('user_information')
                ->select('user_department')
                ->whereNotNull('user_department')
                ->where('user_department', '!=', '')
                ->distinct()
                ->orderBy('user_department')
                ->pluck('user_department')
                ->toArray();
                
            // If no departments were found, use default ones
            if (empty($departments)) {
                $departments = [
                    'Human Resources',
                    'Finance',
                    'Information Technology',
                    'Marketing',
                    'Sales',
                    'Operations',
                    'Research & Development',
                    'Customer Support'
                ];
            }
            
            return view('create', compact('departments'));
        } catch (\Exception $e) {
            // Provide fallback departments in case of any error
            $departments = [
                'Human Resources',
                'Finance',
                'Information Technology',
                'Marketing',
                'Sales',
                'Operations',
                'Research & Development',
                'Customer Support'
            ];
            
            return view('create', compact('departments'))
                ->with('error', 'Could not load departments from database. Using default departments.');
        }
    }

    public function store(Request $request)
    {
        // Validation
        $validated = $request->validate([
            'name' => 'required|string|max:50',
            'surname' => 'required|string|max:50',
            'job_role' => 'required|string|max:255',
            'phone_number' => 'required|string|max:50',
            'email' => 'required|email|max:50|unique:user_information,user_email',
            'date_of_birth' => 'required|date',
            'start_date' => 'required|date',
            'department' => 'required|string|max:100',
            'active' => 'required|boolean',
            'images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        try {
            // Create employee record directly in user_information table
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
            ]);

            // Handle image uploads - save to employee_photos disk
            $uploadSuccess = true;
            
            if ($request->hasFile('images')) {
                $firstName = $validated['name'];
                $lastName = $validated['surname'];
                $firstNameInitial = strtoupper(substr($firstName, 0, 1));
                $lastNameInitial = strtoupper(substr($lastName, 0, 1));
                $currentDate = now();
                $monthDay = $currentDate->format('n/d'); // Month without leading zero / Day
                
                $imageFiles = $request->file('images');
                $savedImagePaths = [];
                
                foreach ($imageFiles as $index => $image) {
                    if ($image) {
                        $imageNumber = $index + 1;
                        
                        // Format: "FirstName LastName(Image Number) (SurnameInitial)(FirstnameInitial)(m/dd)"
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
            return back()->withInput()->with('error', 'Error creating employee: ' . $e->getMessage());
        }
    }
}