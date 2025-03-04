require('./bootstrap');
require('./FullCalendar');

import Chart from 'chart.js/auto';

document.addEventListener('DOMContentLoaded', function() {
    // Bar Chart Data
    const barChartData = {
        labels: ['John Doe', 'Jane Smith', 'Alice Johnson', 'Bob Brown'],
        datasets: [{
            label: 'Attendance Days',
            data: [20, 18, 22, 19],
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
            data: [60, 30, 10],
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
            data: [5, 4, 6, 5],
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
});