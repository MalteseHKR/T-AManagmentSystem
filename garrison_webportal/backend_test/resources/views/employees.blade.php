@extends('app')

@section('title', 'Manage Employees - Garrison Time and Attendance System')

@section('content')
<div class="container">
    <h1 class="mb-4">Manage Employees</h1>

    <!-- Back Link -->
    <a href="javascript:history.back()" class="btn btn-secondary mb-4">Back</a>

    <!-- Filter Form -->
    <form method="GET" action="{{ route('employees') }}" class="mb-4">
        <div class="form-row">
            <div class="col-md-4">
                <input type="text" name="name" class="form-control" placeholder="Search by name" value="{{ request('name') }}">
            </div>
            <div class="col-md-4">
                <select name="department" class="form-control">
                    <option value="">All Departments</option>
                    <option value="HR" {{ request('department') == 'HR' ? 'selected' : '' }}>HR</option>
                    <option value="Finance" {{ request('department') == 'Finance' ? 'selected' : '' }}>Finance</option>
                    <option value="IT" {{ request('department') == 'IT' ? 'selected' : '' }}>IT</option>
                    <option value="Sales" {{ request('department') == 'Sales' ? 'selected' : '' }}>Sales</option>
                </select>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary btn-block">Filter</button>
            </div>
        </div>
    </form>

    <!-- Employee List -->
    <div class="list-group">
        @foreach ($employees as $employee)
        <a href="{{ route('employee.profile', ['id' => $employee->id]) }}" class="list-group-item list-group-item-action">
            <h5 class="mb-1">{{ $employee->first_name }} {{ $employee->surname }}</h5>
            <p class="mb-1">Department: {{ $employee->department }}</p>
        </a>
        @endforeach
    </div>
</div>
@endsection