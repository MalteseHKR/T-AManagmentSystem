@extends('profile.layout')

@section('profile-content')
    <h2 class="mb-4">My Attendance Records</h2>
    
    <!-- Filters and Search -->
    <div class="card mb-4">
        <div class="card-body">
            <form action="{{ route('profile.attendance') }}" method="GET" class="row g-3">
                <div class="col-md-4">
                    <label for="date_from" class="form-label">From Date</label>
                    <input type="date" class="form-control" id="date_from" name="date_from" value="{{ request('date_from') }}">
                </div>
                <div class="col-md-4">
                    <label for="date_to" class="form-label">To Date</label>
                    <input type="date" class="form-control" id="date_to" name="date_to" value="{{ request('date_to') }}">
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">Filter</button>
                    <a href="{{ route('profile.attendance') }}" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Attendance Records Table -->
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Status</th>
                            <th>Location</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($attendanceRecords as $record)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($record->punch_date)->format('d M, Y') }}</td>
                            <td>{{ \Carbon\Carbon::parse($record->date_time_event)->format('H:i A') }}</td>
                            <td>
                                <span class="badge bg-{{ $record->punch_type == 'IN' ? 'success' : 'primary' }}">
                                    {{ $record->punch_type }}
                                </span>
                            </td>
                            <td>{{ $record->device ? $record->device->device_name : 'Unknown' }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="text-center py-3">No attendance records found.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Pagination -->
    <div class="mt-3">
        {{ $attendanceRecords->links() }}
    </div>
@endsection