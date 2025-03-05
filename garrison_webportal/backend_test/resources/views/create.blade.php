@extends('layouts.app')

@section('title', 'Create Employee - Garrison Time and Attendance System')

@section('content')
<div class="container">
    <h1 class="mb-4">Create New Employee</h1>

    <form action="{{ route('employees') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="name" class="form-label">Name</label>
                <input type="text" class="form-control" id="name" name="name" required>
            </div>
            
            <div class="col-md-6 mb-3">
                <label for="surname" class="form-label">Surname</label>
                <input type="text" class="form-control" id="surname" name="surname" required>
            </div>

            <div class="col-md-6 mb-3">
                <label for="job_role" class="form-label">Job Role</label>
                <input type="text" class="form-control" id="job_role" name="job_role" required>
            </div>

            <div class="col-md-6 mb-3">
                <label for="phone_number" class="form-label">Phone Number</label>
                <input type="tel" class="form-control" id="phone_number" name="phone_number" required>
            </div>

            <div class="col-md-6 mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control" id="email" name="email" required>
            </div>

            <div class="col-md-6 mb-3">
                <label for="date_of_birth" class="form-label">Date of Birth</label>
                <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" required>
            </div>

            <div class="col-md-6 mb-3">
                <label for="start_date" class="form-label">Start Date</label>
                <input type="date" class="form-control" id="start_date" name="start_date" required>
            </div>

            <div class="col-md-6 mb-3">
                <label for="department" class="form-label">Department</label>
                <select class="form-control" id="department" name="department" required>
                    <option value="">Select Department</option>
                    <option value="HR">HR</option>
                    <option value="Finance">Finance</option>
                    <option value="IT">IT</option>
                    <option value="Sales">Sales</option>
                </select>
            </div>

            <div class="col-md-6 mb-3">
                <label for="active" class="form-label">Active Status</label>
                <select class="form-control" id="active" name="active" required>
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>

            <div class="col-12 mb-3">
                <label class="form-label">Employee Images (Max 5)</label>
                <div class="row">
                    @for ($i = 1; $i <= 5; $i++)
                        <div class="col-md-4 mb-2">
                            <input type="file" class="form-control" name="images[]" accept="image/*">
                        </div>
                    @endfor
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between mt-4">
            <a href="{{ route('employees') }}" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Create Employee</button>
        </div>
    </form>
</div>
@endsection