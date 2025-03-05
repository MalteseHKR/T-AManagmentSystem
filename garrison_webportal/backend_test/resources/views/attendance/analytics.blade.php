<!-- filepath: /c:/xampp/htdocs/5CS024/garrison/T-AManagmentSystem/garrison_webportal/backend_test/resources/views/attendance/analytics.blade.php -->
@extends('layouts.app')

@section('title', 'Attendance Analytics - Garrison Time and Attendance System')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">Attendance Analytics</h1>
        <a href="{{ route('attendance') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i> Back to Attendance
        </a>
    </div>

    <div class="row">
        <!-- Daily Attendance Chart -->
        <div class="col-lg-8 mb-4">
            <div class="card shadow border-0 h-100">
                <div class="card-header bg-primary text-white py-3">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-chart-line me-2"></i> Daily Attendance (Last 30 Days)
                    </h5>
                </div>
                <div class="card-body">
                    <canvas id="dailyAttendanceChart" height="300"></canvas>
                </div>
            </div>
        </div>

        <!-- Department Breakdown -->
        <div class="col-lg-4 mb-4">
            <div class="card shadow border-0 h-100">
                <div class="card-header bg-primary text-white py-3">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-chart-pie me-2"></i> Department Breakdown
                    </h5>
                </div>
                <div class="card-body">
                    <canvas id="deptAttendanceChart" height="300"></canvas>
                </div>
            </div>
        </div>

        <!-- Average Duration -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow border-0">
                <div class="card-header bg-primary text-white py-3">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-clock me-2"></i> Average Duration by Department
                    </h5>
                </div>
                <div class="card-body">
                    <canvas id="durationChart" height="250"></canvas>
                </div>
            </div>
        </div>

        <!-- Summary Stats -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow border-0">
                <div class="card-header bg-primary text-white py-3">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-list-alt me-2"></i> Summary Statistics
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Department</th>
                                    <th>Attendance Count</th>
                                    <th>Avg. Duration</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($departmentAttendance as $dept)
                                    <tr>
                                        <td>{{ $dept->department }}</td>
                                        <td>{{ $dept->count }}</td>
                                        <td>
                                            @php
                                                $avgDuration = $avgDurationByDept->where('department', $dept->department)->first();
                                            @endphp
                                            {{ $avgDuration ? $avgDuration->formatted_duration : 'N/A' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Set global Chart.js options
    Chart.defaults.color = '#333';
    Chart.defaults.font.family = "'Open Sans', 'Helvetica Neue', 'Helvetica', 'Arial', sans-serif";
    
    // Daily attendance line chart
    const dailyCtx = document.getElementById('dailyAttendanceChart').getContext('2d');
    new Chart(dailyCtx, {
        type: 'line',
        data: {
            labels: JSON.parse('{!! $dates !!}'),
            datasets: [{
                label: 'Daily Attendance',
                data: JSON.parse('{!! $counts !!}'),
                backgroundColor: 'rgba(0, 123, 255, 0.2)',
                borderColor: 'rgba(0, 123, 255, 1)',
                borderWidth: 2,
                tension: 0.3,
                pointBackgroundColor: '#fff',
                pointBorderColor: 'rgba(0, 123, 255, 1)',
                pointRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });

    // Department pie chart
    const deptCtx = document.getElementById('deptAttendanceChart').getContext('2d');
    new Chart(deptCtx, {
        type: 'doughnut',
        data: {
            labels: JSON.parse('{!! $deptLabels !!}'),
            datasets: [{
                data: JSON.parse('{!! $deptCounts !!}'),
                backgroundColor: [
                    'rgba(255, 99, 132, 0.7)',
                    'rgba(54, 162, 235, 0.7)',
                    'rgba(255, 206, 86, 0.7)',
                    'rgba(75, 192, 192, 0.7)',
                    'rgba(153, 102, 255, 0.7)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });

    // Duration bar chart
    const durationCtx = document.getElementById('durationChart').getContext('2d');
    new Chart(durationCtx, {
        type: 'bar',
        data: {
            labels: JSON.parse('{!! $durationLabels !!}'),
            datasets: [{
                label: 'Average Hours',
                data: JSON.parse('{!! $durationValues !!}'),
                backgroundColor: 'rgba(75, 192, 192, 0.7)',
                borderColor: 'rgba(75, 192, 192, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Hours'
                    }
                }
            }
        }
    });
});
</script>
@endsection