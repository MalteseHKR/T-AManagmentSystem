@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Attendance for {{ $employee->first_name }} {{ $employee->surname }}</span>
                    <a href="{{ route('attendance.attendanceE', $employee->id) }}" class="btn btn-sm btn-secondary">Back to Profile</a>
                </div>
                
                <div class="card-body">
                    @if($attendanceRecords->count() > 0)
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Clock In</th>
                                    <th>Clock Out</th>
                                    <th>Hours</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($attendanceRecords as $record)
                                <tr>
                                    <td>{{ \Carbon\Carbon::parse($record->date)->format('M d, Y') }}</td>
                                    <td>{{ $record->clock_in ? \Carbon\Carbon::parse($record->clock_in)->format('H:i') : 'N/A' }}</td>
                                    <td>{{ $record->clock_out ? \Carbon\Carbon::parse($record->clock_out)->format('H:i') : 'N/A' }}</td>
                                    <td>
                                        @if($record->clock_in && $record->clock_out)
                                            {{ \Carbon\Carbon::parse($record->clock_out)->diffInHours(\Carbon\Carbon::parse($record->clock_in)) }}
                                        @else
                                            N/A
                                        @endif
                                    </td>
                                    <td>{{ $record->status }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                        
                        <div class="pagination-container">
                            {{ $attendanceRecords->links('pagination::bootstrap-4') }}
                            <div class="pagination-info">
                                Showing {{ $attendanceRecords->firstItem() ?? 0 }} to {{ $attendanceRecords->lastItem() ?? 0 }} of {{ $attendanceRecords->total() }} records
                            </div>
                        </div>
                    @else
                        <p class="text-center">No attendance records found for this employee.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection