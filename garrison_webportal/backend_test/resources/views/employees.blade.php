@extends('layouts.app')

@section('title', 'Manage Employees - Garrison Time and Attendance System')

@section('content')
<div class="container">
    <h1 class="mb-4">Manage Employees</h1>

    <!-- Nav  Link -->
    <div class="d-flex justify-content-between mb-4">
        <a href="dashboard" class="btn btn-secondary">Back</a>
        <a href="{{ route('create') }}" class="btn btn-primary">Create New Employee</a>
    </div>

    <!-- Filter Form -->
    <x-filter 
        route="{{ route('employees') }}"
        :has-name-filter="true"
        :has-department-filter="true"
        :departments="['HR', 'Finance', 'IT', 'Sales', 'Marketing']"
        name-label="Employee Name"
        name-placeholder="Search by employee name"
        :columns="4"
    />

    <!-- Employee List -->
    <div class="list-group">
        @foreach ($employees as $employee)
        <a href="{{ route('employee.profile', ['id' => $employee->id]) }}" class="list-group-item list-group-item-action">
            <h5 class="mb-1">{{ $employee->first_name }} {{ $employee->surname }}</h5>
            <div>
                <span class="mb-1">Department:</span>
                <span class="department-badge">{{ $employee->department }}</span>
            </div>
        </a>
        @endforeach
    </div>
</div>
@endsection