@extends('layouts.app')

@section('title', 'Attendance - Garrison Time and Attendance System')

@section('content')
<div class="container">
    <h1 class="mb-4">Attendance Records</h1>
    
    <a href="{{ route('dashboard') }}" class="btn btn-secondary mb-4">
        <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
    </a>

    <div class="card shadow border-0">
        <div class="card-header bg-primary text-white py-3">
            <h5 class="card-title mb-0">
                <i class="fas fa-clock me-2"></i>Attendance List
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="px-4 py-3">Employee</th>
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3">Punch In</th>
                            <th class="px-4 py-3">Punch Out</th>
                            <th class="px-4 py-3">Duration</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($attendances as $attendance)
                            <tr>
                                <td class="px-4 py-3 fw-semibold">
                                    {{ optional($attendance->employee)->first_name }} 
                                    {{ optional($attendance->employee)->surname }}
                                </td>
                                <td class="px-4 py-3">{{ $attendance->punch_date }}</td>
                                <td class="px-4 py-3 text-success">{{ $attendance->punch_time }}</td>
                                <td class="px-4 py-3 text-danger">{{ $attendance->punch_out }}</td>
                                <td class="px-4 py-3">{{ $attendance->duration }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">
                                    <i class="fas fa-inbox fa-2x mb-3 d-block"></i>
                                    No attendance records found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if($attendances->hasPages())
        <div class="d-flex justify-content-center mt-4">
            {{ $attendances->appends(['sort' => $sortField, 'direction' => $sortDirection])->links() }}
        </div>
    @endif
</div>
@endsection