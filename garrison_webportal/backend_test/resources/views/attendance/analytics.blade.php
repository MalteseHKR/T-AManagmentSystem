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
                        <i class="fas fa-chart-line me-2"></i> - Attendance by Day
                    </h5>
                </div>
                <div class="card-body">
                    <canvas id="attendanceChart" height="400"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Department Breakdown Tab -->
        <div class="tab-pane fade" id="department" role="tabpanel" aria-labelledby="department-tab">
            <div class="card shadow border-0">
                <div class="card-header bg-primary text-white py-3">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-chart-pie me-2"></i> - Department Breakdown
                    </h5>
                </div>
                <div class="card-body">
                    <canvas id="departmentChart" height="400"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Summary Tab -->
        <div class="tab-pane fade" id="summary" role="tabpanel" aria-labelledby="summary-tab">
            <div class="card shadow border-0">
                <div class="card-header bg-primary text-white py-3">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-info-circle me-2"></i> - Summary Information
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
                                    <h2 class="card-title">
                                        @php
                                            $max = max($attendanceData['values']);
                                            $maxIndex = array_search($max, $attendanceData['values']);
                                            echo $attendanceData['labels'][$maxIndex] . ' (' . $max . ')';
                                        @endphp
                                    </h2>
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
    // Attendance Chart - Create charts right away so they're available when tabs are clicked
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
                tension: 0.3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });
    
    // Department Chart
    const deptCtx = document.getElementById('departmentChart').getContext('2d');
    const deptLabels = {!! json_encode($departmentData['labels']) !!};
    const deptValues = {!! json_encode($departmentData['values']) !!};
    
    const departmentChart = new Chart(deptCtx, {
        type: 'doughnut',
        data: {
            labels: deptLabels,
            datasets: [{
                data: deptValues,
                backgroundColor: [
                    'rgba(255, 99, 132, 0.7)',
                    'rgba(54, 162, 235, 0.7)',
                    'rgba(255, 206, 86, 0.7)',
                    'rgba(75, 192, 192, 0.7)',
                    'rgba(153, 102, 255, 0.7)'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });
    
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