<!-- filepath: /c:/xampp/htdocs/5CS024/garrison/T-AManagmentSystem/garrison_webportal/backend_test/resources/views/attendance/analytics.blade.php -->
@extends('layouts.app')

@section('title', 'Attendance Analytics - Garrison Time and Attendance System')

@section('styles')
<!-- SweetAlert2 CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
@endsection

@section('show_navbar', true)

@section('content')
<div class="container analytics-container">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <h1 class="mb-0">Attendance Analytics</h1>
        <a href="{{ route('attendance') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i> Back to Attendance
        </a>
    </div>

    <div class="intro-text mb-4">
        <p>This page displays analytics based on attendance records in the system. Use the tabs below to switch between different data visualizations.</p>
    </div>
    
    <!-- Data Status Messages (hidden) -->
    <div id="no-data-message" class="d-none" 
         data-attendance="{{ count($attendanceData['values']) == 0 || array_sum($attendanceData['values']) == 0 ? 'true' : 'false' }}"
         data-department="{{ count($departmentData['values']) == 0 || array_sum($departmentData['values']) == 0 ? 'true' : 'false' }}">
    </div>
    
    <!-- Tabs Navigation -->
    <div class="analytics-tabs-wrapper mb-4">
        <ul class="nav nav-tabs analytics-tabs" id="analyticsTab" role="tablist">
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
    </div>
    
    <!-- Tabs Content -->
    <div class="tab-content" id="analyticsTabContent">
        <!-- Daily Attendance Tab -->
        <div class="tab-pane fade show active" id="daily" role="tabpanel" aria-labelledby="daily-tab">
            <div class="card shadow border-0 analytics-card">
                <div class="card-header bg-primary text-white py-3">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-chart-line me-2"></i> Attendance by Day
                    </h5>
                </div>
                <div class="card-body p-3">
                    <div id="attendance-chart-container" class="chart-container">
                        @if(count($attendanceData['values']) > 0 && array_sum($attendanceData['values']) > 0)
                            <canvas id="attendanceChart"></canvas>
                        @else
                            <div class="alert alert-info d-flex align-items-center">
                                <i class="fas fa-info-circle me-2 fs-4"></i>
                                <div>No attendance data available for the selected period.</div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Department Breakdown Tab -->
        <div class="tab-pane fade" id="department" role="tabpanel" aria-labelledby="department-tab">
            <div class="card shadow border-0 analytics-card">
                <div class="card-header bg-primary text-white py-3">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-chart-pie me-2"></i> Department Breakdown
                    </h5>
                </div>
                <div class="card-body">
                    <div id="department-chart-container" class="chart-container">
                        @if(count($departmentData['values']) > 0 && array_sum($departmentData['values']) > 0)
                            <canvas id="departmentChart"></canvas>
                        @else
                            <div class="alert alert-info d-flex align-items-center">
                                <i class="fas fa-info-circle me-2 fs-4"></i>
                                <div>No department data available.</div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Summary Tab -->
        <div class="tab-pane fade" id="summary" role="tabpanel" aria-labelledby="summary-tab">
            <div class="card shadow border-0 analytics-card">
                <div class="card-header bg-primary text-white py-3">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-info-circle me-2"></i> Summary Information
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <div class="card mb-3 summary-card">
                                <div class="card-body p-3">
                                    <h6 class="card-subtitle mb-2 text-muted">Total Attendance Records</h6>
                                    <h2 class="card-title summary-value">{{ array_sum($attendanceData['values']) }}</h2>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="card mb-3 summary-card">
                                <div class="card-body p-3">
                                    <h6 class="card-subtitle mb-2 text-muted">Peak Day</h6>
                                    @if(count($attendanceData['values']) > 0 && max($attendanceData['values']) > 0)
                                        <h2 class="card-title summary-value">
                                            @php
                                                $max = max($attendanceData['values']);
                                                $maxIndex = array_search($max, $attendanceData['values']);
                                                echo $attendanceData['labels'][$maxIndex] . ' (' . $max . ')';
                                            @endphp
                                        </h2>
                                    @else
                                        <h2 class="card-title summary-value text-muted">No data</h2>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="card mb-3 summary-card">
                                <div class="card-body p-3">
                                    <h6 class="card-subtitle mb-2 text-muted">Departments</h6>
                                    <h2 class="card-title summary-value">{{ count($departmentData['labels']) }}</h2>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="card mb-3 summary-card">
                                <div class="card-body p-3">
                                    <h6 class="card-subtitle mb-2 text-muted">Top Department</h6>
                                    @if(count($departmentData['values']) > 0)
                                        <h2 class="card-title summary-value">
                                            @php
                                                $max = max($departmentData['values']);
                                                $maxIndex = array_search($max, $departmentData['values']);
                                                echo $departmentData['labels'][$maxIndex] . ' (' . $max . ')';
                                            @endphp
                                        </h2>
                                    @else
                                        <h2 class="card-title summary-value text-muted">No data</h2>
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

<!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Check if we have attendance data
    const hasAttendanceData = {!! json_encode(count($attendanceData['values']) > 0 && array_sum($attendanceData['values']) > 0) !!};
    const hasDepartmentData = {!! json_encode(count($departmentData['values']) > 0 && array_sum($departmentData['values']) > 0) !!};
    
    // Show loading message
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer)
            toast.addEventListener('mouseleave', Swal.resumeTimer)
        }
    });
    
    Toast.fire({
        icon: 'info',
        title: 'Loading analytics data...'
    });
    
    // Show missing data notifications as needed
    const noDataMessage = document.getElementById('no-data-message');
    const noAttendanceData = noDataMessage.getAttribute('data-attendance') === 'true';
    const noDepartmentData = noDataMessage.getAttribute('data-department') === 'true';
    
    if (noAttendanceData && noDepartmentData) {
        setTimeout(() => {
            Swal.fire({
                icon: 'info',
                title: 'No Data Available',
                text: 'There is currently no attendance data to display in the analytics.',
                confirmButtonColor: '#3085d6',
            });
        }, 1000);
    } else if (noAttendanceData || noDepartmentData) {
        setTimeout(() => {
            Toast.fire({
                icon: 'info',
                title: noAttendanceData ? 'No attendance data available' : 'No department data available'
            });
        }, 1000);
    } else {
        setTimeout(() => {
            Toast.fire({
                icon: 'success',
                title: 'Analytics data loaded successfully'
            });
        }, 1000);
    }
    
    // Chart variables
    let attendanceChart = null;
    let departmentChart = null;
    
    if (hasAttendanceData) {
        // Attendance Chart - Daily attendance
        const attendanceCtx = document.getElementById('attendanceChart').getContext('2d');
        const attendanceLabels = {!! json_encode($attendanceData['labels']) !!};
        const attendanceValues = {!! json_encode($attendanceData['values']) !!};
        
        attendanceChart = new Chart(attendanceCtx, {
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
                    },
                    x: {
                        ticks: {
                            maxRotation: 45,
                            minRotation: 45,
                            callback: function(val, index) {
                                // Abbreviate labels on small screens
                                const label = this.getLabelForValue(val);
                                if (window.innerWidth < 768) {
                                    // Return first 3 chars for mobile
                                    return label.substring(0, 3);
                                }
                                return label;
                            }
                        }
                    }
                },
                plugins: {
                    title: {
                        display: true,
                        text: 'Attendance Over the Last 7 Days',
                        font: {
                            size: 16,
                            weight: 'bold'
                        },
                        padding: {
                            top: 10,
                            bottom: 20
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0,0,0,0.8)',
                        titleFont: {
                            size: 14
                        },
                        bodyFont: {
                            size: 13
                        },
                        callbacks: {
                            label: function(context) {
                                return 'Attendance: ' + context.raw;
                            }
                        }
                    },
                    legend: {
                        labels: {
                            font: {
                                size: function() {
                                    return window.innerWidth < 768 ? 11 : 12;
                                }
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
        
        departmentChart = new Chart(deptCtx, {
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
                layout: {
                    padding: {
                        top: 10,
                        bottom: 10,
                        left: 10,
                        right: 10
                    }
                },
                plugins: {
                    title: {
                        display: true,
                        text: 'Attendance by Department',
                        font: {
                            size: 16,
                            weight: 'bold'
                        },
                        padding: {
                            top: 10,
                            bottom: 20
                        }
                    },
                    legend: {
                        position: function() {
                            return window.innerWidth < 768 ? 'bottom' : 'right';
                        },
                        labels: {
                            padding: function() {
                                return window.innerWidth < 768 ? 10 : 20;
                            },
                            font: {
                                size: function() {
                                    return window.innerWidth < 768 ? 11 : 12;
                                }
                            },
                            boxWidth: function() {
                                return window.innerWidth < 768 ? 10 : 15;
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0,0,0,0.8)',
                        titleFont: {
                            size: 14
                        },
                        bodyFont: {
                            size: 13
                        },
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
            
            // Use Bootstrap's tab API properly
            const bsTab = new bootstrap.Tab(this);
            bsTab.show();
            
            // Wait for the tab transition to complete before updating charts
            setTimeout(function() {
                try {
                    if (targetId === 'daily' && attendanceChart) {
                        // Ensure canvas has proper dimensions first
                        const canvas = document.getElementById('attendanceChart');
                        canvas.style.height = '400px';
                        canvas.height = canvas.offsetHeight;
                        canvas.width = canvas.offsetWidth;
                        
                        attendanceChart.update();
                    } else if (targetId === 'department' && departmentChart) {
                        const canvas = document.getElementById('departmentChart');
                        canvas.style.height = '400px';
                        canvas.height = canvas.offsetHeight;
                        canvas.width = canvas.offsetWidth;
                        
                        departmentChart.update();
                    }
                } catch (error) {
                    console.error('Error updating chart:', error);
                }
            }, 150); // Slightly longer delay to ensure DOM is ready
        });
    });

    // Fix window resize handler
    let resizeTimeout;
    window.addEventListener('resize', function() {
        // Debounce resize events
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(function() {
            try {
                if (attendanceChart) {
                    attendanceChart.update();
                }
                if (departmentChart) {
                    departmentChart.options.plugins.legend.position = window.innerWidth < 768 ? 'bottom' : 'right';
                    departmentChart.update();
                }
            } catch (error) {
                console.error('Error during resize:', error);
            }
        }, 250);
    });
});
</script>
@endsection