@extends('app')

@section('title', 'Track Attendance - Garrison Time and Attendance System')

@section('content')
<div class="container">
    <h1 class="mb-4">Track Attendance</h1>
    <p>Track employee attendance, view attendance reports, and manage attendance records.</p>

    <!-- Back Link -->
    <a href="javascript:history.back()" class="btn btn-secondary mb-4">Back</a>

    <!-- Chart Switch Buttons -->
    <div class="mb-4">
        <button id="showBarChart" class="btn btn-primary">Show Bar Chart</button>
        <button id="showPieChart" class="btn btn-secondary">Show Pie Chart</button>
        <button id="showLineChart" class="btn btn-info">Show Line Chart</button>
    </div>

    <!-- Bar Chart -->
    <div class="chart-container mb-4" id="barChartContainer">
        <canvas id="attendanceBarChart"></canvas>
    </div>

    <!-- Pie Chart -->
    <div class="chart-container mb-4" id="pieChartContainer" style="display: none;">
        <canvas id="attendancePieChart"></canvas>
    </div>

    <!-- Line Chart -->
    <div class="chart-container mb-4" id="lineChartContainer" style="display: none;">
        <canvas id="attendanceLineChart"></canvas>
    </div>
</div>
@endsection

@push('styles')
<style>
    .chart-container {
        width: 100%;
        max-width: 600px;
        margin: 0 auto;
    }
</style>
@endpush

@push('scripts')
<script>
    // Bar Chart Data
    const barChartData = {
        labels: @json($attendanceRecords->pluck('employee_id')->unique()->map(function($id) {
            return \App\Models\Employee::find($id)->first_name . ' ' . \App\Models\Employee::find($id)->surname;
        })),
        datasets: [{
            label: 'Attendance Days',
            data: @json($attendanceRecords->groupBy('employee_id')->map(function($records) {
                return $records->count();
            })),
            backgroundColor: 'rgba(54, 162, 235, 0.2)',
            borderColor: 'rgba(54, 162, 235, 1)',
            borderWidth: 1
        }]
    };

    // Bar Chart Configuration
    const barChartConfig = {
        type: 'bar',
        data: barChartData,
        options: {
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    };

    // Render Bar Chart
    const attendanceBarChart = new Chart(
        document.getElementById('attendanceBarChart'),
        barChartConfig
    );

    // Pie Chart Data
    const pieChartData = {
        labels: ['Present', 'Absent', 'Late'],
        datasets: [{
            label: 'Attendance Status',
            data: [
                @json($attendanceRecords->where('punch_type', 'In')->count()),
                @json($attendanceRecords->where('punch_type', 'Out')->count()),
                @json($attendanceRecords->where('punch_type', 'Late')->count())
            ],
            backgroundColor: [
                'rgba(75, 192, 192, 0.2)',
                'rgba(255, 99, 132, 0.2)',
                'rgba(255, 206, 86, 0.2)'
            ],
            borderColor: [
                'rgba(75, 192, 192, 1)',
                'rgba(255, 99, 132, 1)',
                'rgba(255, 206, 86, 1)'
            ],
            borderWidth: 1
        }]
    };

    // Pie Chart Configuration
    const pieChartConfig = {
        type: 'pie',
        data: pieChartData,
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top',
                },
                tooltip: {
                    callbacks: {
                        label: function(tooltipItem) {
                            return tooltipItem.label + ': ' + tooltipItem.raw + '%';
                        }
                    }
                }
            }
        }
    };

    // Render Pie Chart
    const attendancePieChart = new Chart(
        document.getElementById('attendancePieChart'),
        pieChartConfig
    );

    // Line Chart Data
    const lineChartData = {
        labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
        datasets: [{
            label: 'Attendance Days',
            data: @json($attendanceRecords->groupBy(function($record) {
                return \Carbon\Carbon::parse($record->punch_in)->weekOfMonth;
            })->map(function($records) {
                return $records->count();
            })),
            backgroundColor: 'rgba(153, 102, 255, 0.2)',
            borderColor: 'rgba(153, 102, 255, 1)',
            borderWidth: 1,
            fill: false
        }]
    };

    // Line Chart Configuration
    const lineChartConfig = {
        type: 'line',
        data: lineChartData,
        options: {
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    };

    // Render Line Chart
    const attendanceLineChart = new Chart(
        document.getElementById('attendanceLineChart'),
        lineChartConfig
    );

    // Toggle Chart Visibility
    document.getElementById('showBarChart').addEventListener('click', function() {
        document.getElementById('barChartContainer').style.display = 'block';
        document.getElementById('pieChartContainer').style.display = 'none';
        document.getElementById('lineChartContainer').style.display = 'none';
    });

    document.getElementById('showPieChart').addEventListener('click', function() {
        document.getElementById('barChartContainer').style.display = 'none';
        document.getElementById('pieChartContainer').style.display = 'block';
        document.getElementById('lineChartContainer').style.display = 'none';
    });

    document.getElementById('showLineChart').addEventListener('click', function() {
        document.getElementById('barChartContainer').style.display = 'none';
        document.getElementById('pieChartContainer').style.display = 'none';
        document.getElementById('lineChartContainer').style.display = 'block';
    });
</script>
@endpush