@extends('layouts.app')

@section('title', 'Manage Leaves - Garrison Time and Attendance System')

@section('content')
<div class="container">
    <h1 class="mb-4">Manage Leaves</h1>
    <p>Manage employee leave requests, approve or reject leave applications, and view leave balances.</p>

    <!-- Back Link -->
    <a href="javascript:history.back()" class="btn btn-secondary mb-4">Back</a>

    <!-- Filter Form -->
    <form method="GET" action="{{ route('leaves') }}" class="mb-4">
        <div class="form-row">
            <div class="col-md-4">
                <input type="text" name="employee_name" class="form-control" placeholder="Search by employee name" value="{{ request('employee_name') }}">
            </div>
            <div class="col-md-4">
                <select name="status" class="form-control">
                    <option value="">All Statuses</option>
                    <option value="Pending" {{ request('status') == 'Pending' ? 'selected' : '' }}>Pending</option>
                    <option value="Approved" {{ request('status') == 'Approved' ? 'selected' : '' }}>Approved</option>
                    <option value="Rejected" {{ request('status') == 'Rejected' ? 'selected' : '' }}>Rejected</option>
                </select>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary btn-block">Filter</button>
            </div>
        </div>
    </form>

    <!-- Leave Requests Table -->
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Employee Name</th>
                <th>Leave Type</th>
                <th>Start Date</th>
                <th>End Date</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($leaveRequests as $request)
            <tr>
                <td>{{ $request['employee_name'] }}</td>
                <td>{{ $request['leave_type'] }}</td>
                <td>{{ $request['start_date'] }}</td>
                <td>{{ $request['end_date'] }}</td>
                <td>{{ $request['status'] }}</td>
                <td>
                    <button class="btn btn-success btn-sm">Approve</button>
                    <button class="btn btn-danger btn-sm">Reject</button>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection