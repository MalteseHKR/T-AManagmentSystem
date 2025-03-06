@extends('layouts.app')

@section('title', 'Attendance - Garrison Time and Attendance System')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">Attendance Records</h1>
        <div class="d-flex justify-content-between align-items-center">
            <a href="{{ route('dashboard') }}" class="btn btn-secondary me-3">
                <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
            </a>
            <a href="{{ route('attendance.analytics') }}" class="btn-analytics">
                <i class="fas fa-chart-bar me-2"></i> Analytics Dashboard
            </a>
        </div>
    </div>

    <x-filter 
        route="{{ route('attendance') }}"
        :has-name-filter="true"
        :has-date-filter="true"
        name-label="Employee"
        name-placeholder="Search by employee name"
        :columns="3"
    />

    <div class="card shadow border-0">
        <div class="card-header bg-primary text-white py-4">
            <h4 class="card-title mb-0 fw-bold">
                <i class="fas fa-clock me-2 fa-lg"></i> - Attendance List
            </h4>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle mb-0 w-100">
                    <thead class="bg-light">
                        <tr>
                            <th class="px-4 py-3" style="width: 25%">Employee</th>
                            <th class="px-4 py-3" style="width: 15%">Date</th>
                            <th class="px-4 py-3" style="width: 15%">Punch In</th>
                            <th class="px-4 py-3" style="width: 15%">Punch Out</th>
                            <th class="px-4 py-3" style="width: 15%">Duration</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($attendances as $attendance)
                            <tr>
                                <td class="px-4 py-3 fw-semibold align-middle">
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
        <div class="pagination-container">
            <div class="pagination-info">
                Showing {{ $attendances->firstItem() ?? 0 }} to {{ $attendances->lastItem() ?? 0 }} of {{ $attendances->total() }} entries
            </div>
            
            <nav aria-label="Attendance pages">
                <ul class="pagination">
                    <!-- First Page Link -->
                    <li class="page-item {{ $attendances->onFirstPage() ? 'disabled' : '' }}">
                        <a class="page-link" href="{{ $attendances->url(1) }}" aria-label="First">
                            <i class="fas fa-angle-double-left"></i>
                        </a>
                    </li>
                    
                    <!-- Previous Page Link -->
                    <li class="page-item {{ $attendances->onFirstPage() ? 'disabled' : '' }}">
                        <a class="page-link" href="{{ $attendances->previousPageUrl() }}" aria-label="Previous">
                            <i class="fas fa-angle-left"></i>
                        </a>
                    </li>
                    
                    <!-- Current Page Info -->
                    <li class="page-item active">
                        <span class="page-link">
                            {{ $attendances->currentPage() }}
                        </span>
                    </li>
                    
                    <!-- Next Page Link -->
                    <li class="page-item {{ !$attendances->hasMorePages() ? 'disabled' : '' }}">
                        <a class="page-link" href="{{ $attendances->nextPageUrl() }}" aria-label="Next">
                            <i class="fas fa-angle-right"></i>
                        </a>
                    </li>
                    
                    <!-- Last Page Link -->
                    <li class="page-item {{ !$attendances->hasMorePages() ? 'disabled' : '' }}">
                        <a class="page-link" href="{{ $attendances->url($attendances->lastPage()) }}" aria-label="Last">
                            <i class="fas fa-angle-double-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    @endif
</div>
@endsection