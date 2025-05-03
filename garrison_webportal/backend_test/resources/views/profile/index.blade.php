@extends('profile.layout')

@section('profile-content')
    <h2 class="mb-4">Profile Overview</h2>

@php
    $annual = $leaveBalances->firstWhere('leaveType.leave_type_name', 'Annual');
    $sick = $leaveBalances->firstWhere('leaveType.leave_type_name', 'Sick');
    $other = $leaveBalances->firstWhere('leaveType.leave_type_name', 'Personal'); // or 'Other Leave'

    $annualRemaining = $annual ? $annual->total_days - $annual->used_days : 0;
    $sickRemaining = $sick ? $sick->total_days - $sick->used_days : 0;
    $otherRemaining = $other ? $other->total_days - $other->used_days : 0;
@endphp

    
    <div class="row">
        <div class="col-md-12">
            <div class="mb-4">
                <h5>Personal Information</h5>
                <hr>
                <div class="row mb-2">
                    <div class="col-md-3 fw-bold">Name:</div>
                    <div class="col-md-9">
                        @if(Auth::user()->userInformation && (Auth::user()->userInformation->user_name || Auth::user()->userInformation->user_surname))
                            {{ trim(Auth::user()->userInformation->user_name . ' ' . Auth::user()->userInformation->user_surname) }}
                        @else
                            {{ Auth::user()->name ?? 'Not set' }}
                        @endif
                    </div>
                </div>
                <div class="row mb-2">
                    <div class="col-md-3 fw-bold">Email:</div>
                    <div class="col-md-9">{{ Auth::user()->userInformation->user_email ?? 'Not set' }}</div>
                </div>
                @if(Auth::user()->userInformation)
                {{-- <div class="row mb-2">
                    <div class="col-md-3 fw-bold">Username:</div>
                    <div class="col-md-9">{{ Auth::user()->userInformation->user_email ?? 'Not set' }}</div>
                </div> --}}
                <div class="row mb-2">
                    <div class="col-md-3 fw-bold">Phone:</div>
                    <div class="col-md-9">{{ Auth::user()->userInformation->user_phone ?? 'Not set' }}</div>
                </div>
                @endif
            </div>
            
            <div class="mb-4">
                <h5>Account Information</h5>
                <hr>
                <div class="row mb-2">
                    <div class="col-md-3 fw-bold">Account Created:</div>
                    <div class="col-md-9">
                        @if(Auth::user()->userInformation && Auth::user()->userInformation->user_job_start)
                            {{ Auth::user()->userInformation->user_job_start->format('F d, Y') }}
                        @else
                            Not available
                        @endif
                    </div>
                </div>
                <div class="row mb-2">
                    <div class="col-md-3 fw-bold">2FA Status:</div>
                    <div class="col-md-9">
                        @if($twoFactorEnabled)
                            <span class="badge bg-success">Enabled</span>
                        @else
                            <span class="badge bg-warning">Disabled</span>
                        @endif
                    </div>
                </div>
            </div>

            <div class="mb-4">
                <h5>Leave & Attendance</h5>
                <hr>
                
                <!-- Leave Balances -->
<div class="card mb-4">
    <div class="card-body">
        <h5>Leave Balances</h5>
        <div class="row mt-3">
            <div class="col-md-4 mb-3">
                <div class="p-3 bg-light rounded text-center">
                    <h6>Annual Leave</h6>
                    <h4>{{ $annual->total_days ?? '0' }} days</h4>
                    <small class="text-muted">Remaining: {{ $annualRemaining }} days</small>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="p-3 bg-light rounded text-center">
                    <h6>Sick Leave</h6>
                    <h4>{{ $sick->total_days ?? '0' }} days</h4>
                    <small class="text-muted">Remaining: {{ $sickRemaining }} days</small>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="p-3 bg-light rounded text-center">
                    <h6>Other Leave</h6>
                    <h4>{{ $other->total_days ?? '0' }} days</h4>
                    <small class="text-muted">Remaining: {{ $otherRemaining }} days</small>
                </div>
            </div>
        </div>
    </div>
</div>
                
                <!-- Recent Attendance -->
                <div class="card">
                    <div class="card-header bg-light">
                        <div class="d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-clock me-2"></i> Recent Attendance</span>
                            {{-- <!-- <a href="{{ route('attendance.history') }}" class="btn btn-sm btn-outline-primary">View All</a> --> --}}
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Clock In</th>
                                        <th>Clock Out</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($recentAttendance ?? [] as $record)
                                    <tr>
                                        <td>{{ \Carbon\Carbon::parse($record->punch_date)->format('d M, Y') }}</td>
                                        <td>{{ $record->punch_type == 'IN' ? \Carbon\Carbon::parse($record->date_time_event)->format('H:i A') : '-' }}</td>
                                        <td>{{ $record->punch_type == 'OUT' ? \Carbon\Carbon::parse($record->date_time_event)->format('H:i A') : 'Active' }}</td>
                                        <td>
                                            <span class="badge bg-{{ $record->punch_type == 'IN' ? 'success' : 'primary' }}">
                                                {{ $record->punch_type }}
                                            </span>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="4" class="text-center py-3">No recent attendance records found.</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection