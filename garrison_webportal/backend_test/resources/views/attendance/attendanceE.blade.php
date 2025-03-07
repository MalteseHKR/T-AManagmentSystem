@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Attendance for {{ $employee->first_name }} {{ $employee->last_name }}</span>
                    <a href="{{ route('employee.profile', $employee->id) }}" class="btn btn-sm btn-secondary">
                        Back to Profile
                    </a>
                </div>
                
                <div class="card-body">
                    @if($attendances->count() > 0)
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
                                @foreach($attendances as $record)
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
                                    <td>
                                        @if(!$record->clock_in)
                                            <span class="badge bg-warning text-dark">Not Started</span>
                                        @elseif(!$record->clock_out)
                                            <span class="badge bg-success">Working</span>
                                        @else
                                            <span class="badge bg-primary">Complete</span>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                        
                        <div class="mt-4">
                            {{ $attendances->links() }}
                        </div>
                    @else
                        <div class="alert alert-info">
                            No attendance records found for this employee.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection