{{-- filepath: c:\xampp\htdocs\5CS024\sprint 2\T-AManagmentSystem\garrison_webportal\backend_test\resources\views\general\general_access.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Portal - Garrison</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css">
    <style>
        .welcome-banner {
            background-color: #f5f7fa;
            border-left: 4px solid #4e73df;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        .card {
            transition: transform 0.2s;
            margin-bottom: 1.5rem;
            border-radius: 0.5rem;
            box-shadow: 0 0.15rem 1.75rem rgba(0, 0, 0, 0.05);
        }
        .card:hover {
            transform: translateY(-4px);
        }
        .card-icon {
            font-size: 2rem;
            color: #4e73df;
        }
        .attendance-status.active {
            background-color: #30c78d;
            color: white;
        }
        .navbar-brand img {
            height: 40px;
        }
        .sidebar {
            min-height: calc(100vh - 56px);
            background-color: #464f67;
            color: rgba(255, 255, 255, 0.8);
        }
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.6);
            padding: 0.7rem 1rem;
            margin: 0.2rem 0;
            border-radius: 0.25rem;
        }
        .sidebar .nav-link:hover {
            color: white;
            background-color: rgba(255, 255, 255, 0.1);
        }
        .sidebar .nav-link.active {
            color: white;
            background-color: #4e73df;
        }
        .sidebar .nav-link i {
            margin-right: 0.5rem;
        }
        .quick-stats .card {
            border-left: 4px solid;
        }
        .leave-days { border-left-color: #4e73df; }
        .announcements { border-left-color: #f6c23e; }
        .attendance { border-left-color: #36b9cc; }
        .tasks { border-left-color: #1cc88a; }
        .profile-image {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border: 5px solid #fff;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        #clockStatus {
            font-weight: bold;
            padding: 0.5rem;
            border-radius: 0.25rem;
            display: inline-block;
            margin-bottom: 1rem;
        }
        .clock-in { background-color: #e5f9f1; color: #1cc88a; }
        .clock-out { background-color: #f8f9fe; color: #858796; }
        .task-priority-high { background-color: #ffecec; border-left: 3px solid #e74a3b; }
        .task-priority-medium { background-color: #fff8ec; border-left: 3px solid #f6c23e; }
        .task-priority-low { background-color: #f1f8ff; border-left: 3px solid #4e73df; }
    </style>
</head>
<body>
    <!-- Top Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <img src="{{ asset('images/garrison-logo.png') }}" alt="Garrison Logo" onerror="this.src='https://via.placeholder.com/150x40?text=Garrison'">
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-bell"></i>
                            <span class="badge bg-danger rounded-pill">3</span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><h6 class="dropdown-header">Notifications</h6></li>
                            <li><a class="dropdown-item" href="#">New announcement: Company picnic</a></li>
                            <li><a class="dropdown-item" href="#">Your leave request was approved</a></li>
                            <li><a class="dropdown-item" href="#">New task assigned to you</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-center" href="#">Show all notifications</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <span class="me-2">{{ session('user_name') ?? 'Employee' }}</span>
                            <img src="{{ asset('images/avatar/' . (session('user_id') ?? '1') . '.jpg') }}" 
                                 alt="Profile" 
                                 class="rounded-circle" 
                                 width="32" 
                                 height="32"
                                 onerror="this.src='https://via.placeholder.com/32x32'">
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="#"><i class="bi bi-person me-2"></i>My Profile</a></li>
                            <li><a class="dropdown-item" href="#"><i class="bi bi-gear me-2"></i>Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="#" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                    <i class="bi bi-box-arrow-right me-2"></i>Logout
                                </a>
                                <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                                    @csrf
                                </form>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="#">
                                <i class="bi bi-house-door"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#">
                                <i class="bi bi-calendar-check"></i> Attendance
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#">
                                <i class="bi bi-calendar2-minus"></i> Leave Requests
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#">
                                <i class="bi bi-megaphone"></i> Announcements
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#">
                                <i class="bi bi-card-checklist"></i> Tasks
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#">
                                <i class="bi bi-people"></i> Team Directory
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#">
                                <i class="bi bi-person"></i> My Profile
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <!-- Welcome Banner -->
                <div class="welcome-banner">
                    <h4>Welcome, {{ session('user_name') ?? 'Employee' }}!</h4>
                    <p class="mb-0">Today is {{ date('l, F j, Y') }}</p>
                </div>

                <!-- Quick Stats -->
                <div class="row quick-stats mb-4">
                    <div class="col-xl-3 col-md-6">
                        <div class="card leave-days">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Available Leave Days</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">12 days</div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-calendar2-minus card-icon"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6">
                        <div class="card announcements">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                            New Announcements</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">3</div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-megaphone card-icon"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6">
                        <div class="card attendance">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                            Monthly Attendance</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">96%</div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-calendar-check card-icon"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6">
                        <div class="card tasks">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                            Pending Tasks</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">2</div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-list-task card-icon"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Content Sections -->
                <div class="row">
                    <!-- Attendance Section -->
                    <div class="col-lg-6 mb-4">
                        <div class="card shadow h-100">
                            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                <h6 class="m-0 font-weight-bold">Attendance Tracker</h6>
                            </div>
                            <div class="card-body">
                                <div class="text-center mb-4">
                                    <div id="clockStatus" class="clock-out">You are currently CLOCKED OUT</div>
                                    <div id="currentTime" class="h4 mb-3">--:--:--</div>
                                    <button id="clockButton" class="btn btn-success">
                                        <i class="bi bi-clock"></i> Clock In
                                    </button>
                                </div>
                                <h6 class="font-weight-bold">Recent Activity</h6>
                                <ul class="list-group">
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <i class="bi bi-box-arrow-in-right text-success me-2"></i>
                                            Clock In
                                            <small class="text-muted d-block">Yesterday</small>
                                        </div>
                                        <span>08:55 AM</span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <i class="bi bi-box-arrow-right text-danger me-2"></i>
                                            Clock Out
                                            <small class="text-muted d-block">Yesterday</small>
                                        </div>
                                        <span>05:02 PM</span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <i class="bi bi-box-arrow-in-right text-success me-2"></i>
                                            Clock In
                                            <small class="text-muted d-block">Apr 4, 2025</small>
                                        </div>
                                        <span>09:00 AM</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Leave Requests -->
                    <div class="col-lg-6 mb-4">
                        <div class="card shadow h-100">
                            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                <h6 class="m-0 font-weight-bold">Leave Requests</h6>
                                <a href="#" class="btn btn-sm btn-primary">
                                    <i class="bi bi-plus"></i> New Request
                                </a>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Type</th>
                                                <th>From</th>
                                                <th>To</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>Annual Leave</td>
                                                <td>Apr 15, 2025</td>
                                                <td>Apr 22, 2025</td>
                                                <td><span class="badge bg-warning">Pending</span></td>
                                            </tr>
                                            <tr>
                                                <td>Sick Leave</td>
                                                <td>Mar 10, 2025</td>
                                                <td>Mar 12, 2025</td>
                                                <td><span class="badge bg-success">Approved</span></td>
                                            </tr>
                                            <tr>
                                                <td>Personal Leave</td>
                                                <td>Feb 5, 2025</td>
                                                <td>Feb 5, 2025</td>
                                                <td><span class="badge bg-success">Approved</span></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Announcements -->
                    <div class="col-lg-6 mb-4">
                        <div class="card shadow h-100">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold">Announcements</h6>
                            </div>
                            <div class="card-body">
                                <div class="announcement-item mb-3 pb-3 border-bottom">
                                    <h6>Company Picnic - Save the Date</h6>
                                    <p class="text-muted small">Posted on April 3, 2025</p>
                                    <p>The annual company picnic will be held on June 15th at Mountain View Park. All staff and families are welcome to attend.</p>
                                    <a href="#" class="btn btn-sm btn-outline-primary">Read More</a>
                                </div>
                                <div class="announcement-item mb-3 pb-3 border-bottom">
                                    <h6>System Maintenance This Weekend</h6>
                                    <p class="text-muted small">Posted on April 2, 2025</p>
                                    <p>IT will be performing system maintenance this weekend. The portal may be unavailable during this time.</p>
                                    <a href="#" class="btn btn-sm btn-outline-primary">Read More</a>
                                </div>
                                <div class="announcement-item">
                                    <h6>New Health Insurance Benefits</h6>
                                    <p class="text-muted small">Posted on March 29, 2025</p>
                                    <p>We've updated our health insurance coverage. Please review the new benefits available to all employees.</p>
                                    <a href="#" class="btn btn-sm btn-outline-primary">Read More</a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- My Tasks (Replacing Payslips) -->
                    <div class="col-lg-6 mb-4">
                        <div class="card shadow h-100">
                            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                <h6 class="m-0 font-weight-bold">My Tasks</h6>
                                <div>
                                    <button class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-plus"></i> Add Task
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="list-group">
                                    <div class="list-group-item task-priority-high">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1">Complete quarterly report</h6>
                                            <small class="text-danger">High Priority</small>
                                        </div>
                                        <p class="mb-1">Prepare and submit the Q1 activities report</p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <small class="text-muted">Due: Apr 7, 2025</small>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" value="" id="task1">
                                                <label class="form-check-label" for="task1">
                                                    Mark as complete
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="list-group-item task-priority-medium">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1">Update department documentation</h6>
                                            <small class="text-warning">Medium Priority</small>
                                        </div>
                                        <p class="mb-1">Review and update process documentation for the department</p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <small class="text-muted">Due: Apr 15, 2025</small>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" value="" id="task2">
                                                <label class="form-check-label" for="task2">
                                                    Mark as complete
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="list-group-item task-priority-low">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1">Team meeting preparation</h6>
                                            <small class="text-primary">Low Priority</small>
                                        </div>
                                        <p class="mb-1">Prepare agenda items for next week's team meeting</p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <small class="text-muted">Due: Apr 20, 2025</small>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" value="" id="task3">
                                                <label class="form-check-label" for="task3">
                                                    Mark as complete
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Clock functionality
        document.addEventListener('DOMContentLoaded', function() {
            const clockButton = document.getElementById('clockButton');
            const clockStatus = document.getElementById('clockStatus');
            const currentTime = document.getElementById('currentTime');
            
            // Clock state (for demo purposes)
            let isClockedIn = false;
            
            // Update current time
            function updateTime() {
                const now = new Date();
                currentTime.textContent = now.toLocaleTimeString();
                setTimeout(updateTime, 1000);
            }
            
            updateTime();
            
            // Clock in/out function
            clockButton.addEventListener('click', function() {
                isClockedIn = !isClockedIn;
                
                if (isClockedIn) {
                    clockStatus.textContent = 'You are currently CLOCKED IN';
                    clockStatus.className = 'clock-in';
                    clockButton.textContent = 'Clock Out';
                    clockButton.className = 'btn btn-danger';
                    clockButton.innerHTML = '<i class="bi bi-clock"></i> Clock Out';
                    
                    // In a real application, you would send an AJAX request to the server
                    fetch('/api/attendance/clock-in', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                        }
                    }).catch(error => console.error('Error:', error));
                    
                } else {
                    clockStatus.textContent = 'You are currently CLOCKED OUT';
                    clockStatus.className = 'clock-out';
                    clockButton.textContent = 'Clock In';
                    clockButton.className = 'btn btn-success';
                    clockButton.innerHTML = '<i class="bi bi-clock"></i> Clock In';
                    
                    // In a real application, you would send an AJAX request to the server
                    fetch('/api/attendance/clock-out', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                        }
                    }).catch(error => console.error('Error:', error));
                }
            });
        });
    </script>
</body>
</html>