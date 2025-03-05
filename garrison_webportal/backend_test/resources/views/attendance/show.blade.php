<!-- filepath: /c:/xampp/htdocs/5CS024/garrison/T-AManagmentSystem/garrison_webportal/backend_test/resources/views/attendance/show.blade.php -->
@extends('layouts.app')

@section('title', 'Attendance Details - Garrison Time and Attendance System')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">Attendance Details</h1>
        <a href="{{ route('attendance') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i> Back to Attendance List
        </a>
    </div>

    <div class="card shadow border-0">
        <div class="card-header bg-primary text-white py-3">
            <h5 class="card-title mb-0">
                <i class="fas fa-calendar-check me-2"></i> Attendance Record #{{ $attendance->id }}
            </h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h4 class="mb-3">Employee Information</h4>
                    <table class="table table-bordered">
                        <tr>
                            <th class="bg-light" style="width: 40%">Name</th>
                            <td>
                                @if($attendance->employee)
                                    {{ $attendance->employee->first_name }} {{ $attendance->employee->surname }}
                                @else
                                    <span class="text-muted">Employee not found</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th class="bg-light">Department</th>
                            <td>{{ $attendance->employee->department ?? 'N/A' }}</td>
                        </tr>
                        <tr>
                            <th class="bg-light">Employee ID</th>
                            <td>{{ $attendance->employee->id ?? 'N/A' }}</td>
                        </tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <h4 class="mb-3">Attendance Information</h4>
                    <table class="table table-bordered">
                        <tr>
                            <th class="bg-light" style="width: 40%">Date</th>
                            <td>{{ $attendance->punch_date }}</td>
                        </tr>
                        <tr>
                            <th class="bg-light">Punch In</th>
                            <td class="text-success">{{ $attendance->punch_time ?? 'N/A' }}</td>
                        </tr>
                        <tr>
                            <th class="bg-light">Punch Out</th>
                            <td class="text-danger">{{ $attendance->punch_out ?? 'N/A' }}</td>
                        </tr>
                        <tr>
                            <th class="bg-light">Duration</th>
                            <td><strong>{{ $attendance->duration ?? 'N/A' }}</strong></td>
                        </tr>
                    </table>
                </div>
            </div>

            @if($attendance->notes)
            <div class="row mt-4">
                <div class="col-12">
                    <h4 class="mb-3">Notes</h4>
                    <div class="p-3 bg-light rounded">
                        {{ $attendance->notes }}
                    </div>
                </div>
            </div>
            @endif
        </div>
        <div class="card-footer">
            <div class="d-flex justify-content-end">
                <a href="{{ route('attendance') }}" class="btn btn-secondary me-2">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
                @if($attendance->employee)
                <a href="{{ route('employee.profile', ['id' => $attendance->employee->id]) }}" class="btn btn-info">
                    <i class="fas fa-user"></i> View Employee Profile
                </a>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection