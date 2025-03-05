<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CreateEmployeeController extends Controller
{
    public function create()
    {
        return view('employees.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'surname' => 'required|string|max:255',
            'job_role' => 'required|string|max:255',
            'phone_number' => 'required|string|max:20',
            'email' => 'required|email|unique:employees',
            'date_of_birth' => 'required|date',
            'start_date' => 'required|date',
            'department' => 'required|string',
            'active' => 'required|boolean',
            'images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        try {
            // Create employee record
            $employee = Employee::create([
                'name' => $validated['name'],
                'surname' => $validated['surname'],
                'job_role' => $validated['job_role'],
                'phone_number' => $validated['phone_number'],
                'email' => $validated['email'],
                'date_of_birth' => $validated['date_of_birth'],
                'start_date' => $validated['start_date'],
                'department' => $validated['department'],
                'active' => $validated['active'],
            ]);

            // Handle image uploads
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $image) {
                    $path = $image->store('employee-images', 'public');
                    EmployeeImage::create([
                        'employee_id' => $employee->id,
                        'path' => $path
                    ]);
                }
            }

            return redirect()->route('employees')->with('success', 'Employee created successfully');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Error creating employee: ' . $e->getMessage());
        }
    }
}