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

    <p>This page displays analytics based on attendance records in the system. Use the tabs above to switch between different data visualizations.</p>
    
    <!-- Tabs Navigation -->
    <ul class="nav nav-tabs mb-4" id="analyticsTab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="daily-tab" data-bs-toggle="tab" data-bs-target="#daily" type="button" role="tab" aria-controls="daily" aria-selected="true">
                <i class="fas fa-chart-line me-2"></i> Daily Attendance
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="department-tab" data-bs-toggle="tab" data-bs-target="#department" type="button" role="tab" aria-controls="department" aria-selected="false">
                <i class="fas fa-chart-pie me-2"></i> Department Breakdown
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="summary-tab" data-bs-toggle="tab" data-bs-target="#summary" type="button" role="tab" aria-controls="summary" aria-selected="false">
                <i class="fas fa-info-circle me-2"></i> Summary
            </button>
        </li>
    </ul>
    
    <!-- Tabs Content -->
    <div class="tab-content" id="analyticsTabContent">
        <!-- Daily Attendance Tab -->
        <div class="tab-pane fade show active" id="daily" role="tabpanel" aria-labelledby="daily-tab">
            <div class="card shadow border-0">
                <div class="card-header bg-primary text-white py-3">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-chart-line me-2"></i> Attendance by Day
                    </h5>
                </div>
                <div class="card-body">
                    @if(count($attendanceData['values']) > 0 && array_sum($attendanceData['values']) > 0)
                        <canvas id="attendanceChart" height="400"></canvas>
                    @else
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> No attendance data available for the selected period.
                        </div>
                    @endif
                </div>
            </div>
        </div>
        
        <!-- Department Breakdown Tab -->
        <div class="tab-pane fade" id="department" role="tabpanel" aria-labelledby="department-tab">
            <div class="card shadow border-0">
                <div class="card-header bg-primary text-white py-3">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-chart-pie me-2"></i> Department Breakdown
                    </h5>
                </div>
                <div class="card-body">
                    @if(count($departmentData['values']) > 0 && array_sum($departmentData['values']) > 0)
                        <canvas id="departmentChart" height="400"></canvas>
                    @else
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> No department data available.
                        </div>
                    @endif
                </div>
            </div>
        </div>
        
        <!-- Summary Tab -->
        <div class="tab-pane fade" id="summary" role="tabpanel" aria-labelledby="summary-tab">
            <div class="card shadow border-0">
                <div class="card-header bg-primary text-white py-3">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-info-circle me-2"></i> Summary Information
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card mb-3">
                                <div class="card-body">
                                    <h6 class="card-subtitle mb-2 text-muted">Total Attendance Records</h6>
                                    <h2 class="card-title">{{ array_sum($attendanceData['values']) }}</h2>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card mb-3">
                                <div class="card-body">
                                    <h6 class="card-subtitle mb-2 text-muted">Peak Day</h6>
                                    @if(count($attendanceData['values']) > 0 && max($attendanceData['values']) > 0)
                                        <h2 class="card-title">
                                            @php
                                                $max = max($attendanceData['values']);
                                                $maxIndex = array_search($max, $attendanceData['values']);
                                                echo $attendanceData['labels'][$maxIndex] . ' (' . $max . ')';
                                            @endphp
                                        </h2>
                                    @else
                                        <h2 class="card-title text-muted">No data</h2>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card mb-3">
                                <div class="card-body">
                                    <h6 class="card-subtitle mb-2 text-muted">Departments</h6>
                                    <h2 class="card-title">{{ count($departmentData['labels']) }}</h2>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card mb-3">
                                <div class="card-body">
                                    <h6 class="card-subtitle mb-2 text-muted">Top Department</h6>
                                    @if(count($departmentData['values']) > 0)
                                        <h2 class="card-title">
                                            @php
                                                $max = max($departmentData['values']);
                                                $maxIndex = array_search($max, $departmentData['values']);
                                                echo $departmentData['labels'][$maxIndex] . ' (' . $max . ')';
                                            @endphp
                                        </h2>
                                    @else
                                        <h2 class="card-title text-muted">No data</h2>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- CDN for Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Check if we have attendance data
    const hasAttendanceData = {!! json_encode(count($attendanceData['values']) > 0 && array_sum($attendanceData['values']) > 0) !!};
    const hasDepartmentData = {!! json_encode(count($departmentData['values']) > 0 && array_sum($departmentData['values']) > 0) !!};
    
    if (hasAttendanceData) {
        // Attendance Chart - Daily attendance
        const attendanceCtx = document.getElementById('attendanceChart').getContext('2d');
        const attendanceLabels = {!! json_encode($attendanceData['labels']) !!};
        const attendanceValues = {!! json_encode($attendanceData['values']) !!};
        
        const attendanceChart = new Chart(attendanceCtx, {
            type: 'line',
            data: {
                labels: attendanceLabels,
                datasets: [{
                    label: 'Attendance Count',
                    data: attendanceValues,
                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 2,
                    tension: 0.3,
                    pointRadius: 5,
                    pointHoverRadius: 7,
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
                },
                plugins: {
                    title: {
                        display: true,
                        text: 'Attendance Over the Last 7 Days',
                        font: {
                            size: 16
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0,0,0,0.8)',
                        callbacks: {
                            label: function(context) {
                                return 'Attendance: ' + context.raw;
                            }
                        }
                    }
                }
            }
        });
    }
    
    if (hasDepartmentData) {
        // Department Chart - Breakdown by department
        const deptCtx = document.getElementById('departmentChart').getContext('2d');
        const deptLabels = {!! json_encode($departmentData['labels']) !!};
        const deptValues = {!! json_encode($departmentData['values']) !!};
        
        // Generate more colors if we have more than 5 departments
        const backgroundColors = generateColors(deptLabels.length);
        
        const departmentChart = new Chart(deptCtx, {
            type: 'doughnut',
            data: {
                labels: deptLabels,
                datasets: [{
                    data: deptValues,
                    backgroundColor: backgroundColors
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'Attendance by Department',
                        font: {
                            size: 16
                        }
                    },
                    legend: {
                        position: 'right',
                        labels: {
                            padding: 20
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0,0,0,0.8)',
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw;
                                const total = context.chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                                const percentage = Math.round((value / total) * 100);
                                return label + ': ' + value + ' (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Function to generate colors for chart
    function generateColors(count) {
        const baseColors = [
            'rgba(255, 99, 132, 0.7)',   // Red
            'rgba(54, 162, 235, 0.7)',   // Blue
            'rgba(255, 206, 86, 0.7)',   // Yellow
            'rgba(75, 192, 192, 0.7)',   // Teal
            'rgba(153, 102, 255, 0.7)',  // Purple
            'rgba(255, 159, 64, 0.7)',   // Orange
            'rgba(56, 193, 114, 0.7)',   // Green
            'rgba(201, 203, 207, 0.7)'   // Grey
        ];
        
        // If we have more departments than base colors, generate additional colors
        const colors = [...baseColors];
        if (count > baseColors.length) {
            for (let i = baseColors.length; i < count; i++) {
                const r = Math.floor(Math.random() * 255);
                const g = Math.floor(Math.random() * 255);
                const b = Math.floor(Math.random() * 255);
                colors.push(`rgba(${r}, ${g}, ${b}, 0.7)`);
            }
        }
        return colors;
    }
    
    // Tab functionality - Improved for better tab switching and canvas resizing
    document.querySelectorAll('#analyticsTab .nav-link').forEach(function(tabEl) {
        tabEl.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Get target tab ID
            const targetId = this.getAttribute('data-bs-target').substring(1);
            
            // Remove active class from all tabs and tab panes
            document.querySelectorAll('#analyticsTab .nav-link').forEach(el => {
                el.classList.remove('active');
                el.setAttribute('aria-selected', 'false');
            });
            
            // Hide all tab panes first (this is the key fix)
            document.querySelectorAll('.tab-pane').forEach(el => {
                el.classList.remove('show', 'active');
                el.style.display = 'none'; // Explicitly hide all tabs
            });
            
            // Add active class to clicked tab
            this.classList.add('active');
            this.setAttribute('aria-selected', 'true');
            
            // Show the selected tab content
            const targetPane = document.getElementById(targetId);
            targetPane.classList.add('show', 'active');
            targetPane.style.display = 'block'; // Explicitly show the selected tab
            
            // Update charts when their tab becomes visible to fix any rendering issues
            if (targetId === 'daily') {
                attendanceChart.resize();
            } else if (targetId === 'department') {
                departmentChart.resize();
            }
        });
    });

    // Also add this to ensure initial state is correct when page loads
    document.querySelectorAll('.tab-pane:not(.active)').forEach(function(pane) {
        pane.style.display = 'none';
    });
    
    // Remove the dashes from the tab labels
    document.querySelectorAll('#analyticsTab .nav-link').forEach(function(tab) {
        const text = tab.innerHTML;
        tab.innerHTML = text.replace(' - ', ' ');
    });
});
</script>
@endsection