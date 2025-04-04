<!-- filepath: /c:/xampp/htdocs/5CS024/garrison/T-AManagmentSystem/garrison_webportal/backend_test/resources/views/employee_attendance.blade.php -->
@extends('app')

@section('title', 'Employee Attendance - Garrison Time and Attendance System')

@section('content')
<div class="container">
    <h1 class="mb-4">Attendance for {{ $employee->first_name }} {{ $employee->surname }}</h1>

    <!-- Back Link -->
    <a href="javascript:history.back()" class="btn btn-secondary mb-4">Back</a>

    <!-- Attendance Calendar -->
    <div class="card">
        <div class="card-header">
            Attendance Calendar
        </div>
        <div class="card-body">
            <div id="attendanceCalendar"></div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<link href="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/5.10.1/main.min.css" rel="stylesheet">
@endpush

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/5.10.1/main.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var calendarEl = document.getElementById('attendanceCalendar');
        var calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            events: [
                @foreach($attendanceRecords as $record)
                {
                    title: '{{ $record->punch_type }}',
                    start: '{{ $record->punch_in }}',
                    @if($record->punch_out)
                    end: '{{ $record->punch_out }}',
                    @endif
                    color: '{{ $record->punch_type == "In" ? "green" : "red" }}'
                },
                @endforeach
            ]
        });
        calendar.render();
    });
</script>
@endpush