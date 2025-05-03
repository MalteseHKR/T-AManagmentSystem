@extends('layouts.app')

@section('title', 'Welcome to Garrison - Time and Attendance Management System')

@section('styles')
<!-- SweetAlert2 CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
@endsection

@section('content')
<div class="home-container">
    <!-- Hero Section -->
    <div class="hero-content d-flex flex-column flex-lg-row align-items-center py-4 py-md-5 px-3">
        <!-- Logo Section -->
        <div class="logo-section text-center mb-4 mb-lg-0">
            <img src="{{ asset('garrison.svg') }}" 
                alt="Garrison Logo" 
                class="img-fluid" style="max-width: 230px;">
        </div>

        <!-- Welcome Text Section -->
        <div class="welcome-section ms-lg-4 text-center text-lg-start">
            <h1 class="welcome-title fs-1 fw-bold">Welcome to Garrison</h1>
            <p class="welcome-subtitle fs-4 text-muted">Time and Attendance Management System</p>
            <div class="welcome-divider d-none d-lg-block my-3 bg-primary" style="height: 3px; width: auto;"></div>
            <p class="welcome-description my-3">
                Streamline your workforce management with our comprehensive time and attendance solution. 
                Track attendance, manage schedules, and optimize productivity all in one place.
            </p>
            
            <!-- Action Buttons -->
            <div class="action-buttons mt-3">
                <a href="{{ route('dashboard') }}" class="btn btn-primary btn-lg">
                    <span class="d-flex align-items-center">
                        <span>Login Here</span>
                        <i class="fas fa-arrow-right ms-2"></i>
                    </span>
                </a>
            </div>
        </div>
    </div>

    <!-- Features Section with Bootstrap -->
    <section class="py-2 m-0 ms-5 me-5">
        <div class="text-center mb-5">
            <h2 class="display-4 fw-bold">Key Features</h2>
            <p class="lead text-muted">Discover what makes Garrison the ideal solution for attendance management</p>
        </div>
        
        <div class="row row-cols-1 row-cols-md-2 g-4">
            <!-- Real-time Tracking Card -->
            <div class="col">
                <div class="card h-100 shadow-sm border-0 rounded-4 hover-card" id="featureCard1">
                    <div class="card-body text-center p-4">
                        <div class="mb-4">
                            <div class="feature-icon-circle d-inline-flex align-items-center justify-content-center rounded-circle">
                                <i class="fas fa-clock fa-2x text-primary"></i>
                            </div>
                        </div>
                        <h3 class="card-title fw-bold">Real-time Tracking</h3>
                        <p class="card-text fs-5">Monitor attendance and time tracking in real-time with accurate data and instant updates.</p>
                    </div>
                </div>
            </div>

            <!-- Attendance Analytics Card -->
            <div class="col">
                <div class="card h-100 shadow-sm border-0 rounded-4 hover-card" id="featureCard2">
                    <div class="card-body text-center p-4">
                        <div class="mb-4">
                            <div class="feature-icon-circle d-inline-flex align-items-center justify-content-center rounded-circle">
                                <i class="fas fa-chart-bar fa-2x text-primary"></i>
                            </div>
                        </div>
                        <h3 class="card-title fw-bold">Attendance Analytics</h3>
                        <p class="card-text fs-5">View attendance data and analyze patterns to optimize workforce management.</p>
                    </div>
                </div>
            </div>

            <!-- AI Facial Recognition Card -->
            <div class="col">
                <div class="card h-100 shadow-sm border-0 rounded-4 hover-card" id="featureCard3">
                    <div class="card-body text-center p-4">
                        <div class="mb-4">
                            <div class="feature-icon-circle d-inline-flex align-items-center justify-content-center rounded-circle">
                                <i class="fas fa-user-check fa-2x text-primary"></i>
                            </div>
                        </div>
                        <h3 class="card-title fw-bold">AI Facial Recognition</h3>
                        <p class="card-text fs-5">Secure biometric attendance system ensures accuracy and eliminates time theft and buddy punching.</p>
                    </div>
                </div>
            </div>
            
            <!-- Leave Management Card -->
            <div class="col">
                <div class="card h-100 shadow-sm border-0 rounded-4 hover-card" id="featureCard4">
                    <div class="card-body text-center p-4">
                        <div class="mb-4">
                            <div class="feature-icon-circle d-inline-flex align-items-center justify-content-center rounded-circle">
                                <i class="fas fa-calendar-alt fa-2x text-primary"></i>
                            </div>
                        </div>
                        <h3 class="card-title fw-bold">Leave Management</h3>
                        <p class="card-text fs-5">Streamlined leave request and approval process with automated workflows and tracking.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="site-footer">
        <div class="footer-content">
            <p class="copyright">&copy; {{ date('Y') }} Garrison. All rights reserved.</p>
            <div class="footer-links">
                <a href="#" id="privacyPolicyLink">Privacy Policy</a>
                <a href="#" id="termsLink">Terms of Service</a>
                <a href="#" id="contactLink">Contact Us</a>
            </div>
        </div>
    </footer>
</div>
@endsection

@push('scripts')
<!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Show welcome notification if it's the user's first visit
        // We'll use localStorage to check for the first visit
        if (!localStorage.getItem('garrison_visited')) {
            setTimeout(() => {
                Swal.fire({
                    title: 'Welcome to Garrison!',
                    text: 'Discover how our time and attendance system can help your organization.',
                    icon: 'info',
                    confirmButtonColor: '#2563eb',
                    confirmButtonText: 'Explore Now',
                    timer: 5000,
                    timerProgressBar: true
                });
                
                // Mark as visited
                localStorage.setItem('garrison_visited', 'true');
            }, 1000);
        }
        
        // Feature card click interactions
        document.querySelectorAll('.hover-card').forEach((card, index) => {
            card.addEventListener('click', function() {
                const featureTitles = [
                    'Real-time Tracking',
                    'Attendance Analytics',
                    'AI Facial Recognition',
                    'Leave Management'
                ];
                
                const featureDescriptions = [
                    'Track employee attendance in real-time with accurate timestamp data. Get instant notifications for late arrivals, absences, and overtime. Our system updates records instantly across all devices.',
                    'View attendance data and analyze patterns in an intuitive dashboard. Identify trends and attendance issues at a glance with visual analytics.',
                    'Secure attendance verification using advanced facial recognition technology. Prevents buddy punching and time theft. User-friendly interface requires minimal training for employees.',
                    'Streamline leave requests and approvals with our integrated system. Employees can request time off while managers track and approve with ease.'
                ];
                
                Swal.fire({
                    title: featureTitles[index],
                    text: featureDescriptions[index],
                    icon: 'info',
                    confirmButtonColor: '#2563eb',
                    confirmButtonText: 'Got it',
                });
            });
        });
        
        // Privacy Policy link
        document.getElementById('privacyPolicyLink').addEventListener('click', function(e) {
            e.preventDefault();
            Swal.fire({
                title: 'Privacy Policy',
                html: '<div class="text-start"><p>This is a placeholder for the Garrison privacy policy. In a real implementation, this would contain the full privacy policy text.</p></div>',
                icon: 'info',
                confirmButtonColor: '#2563eb'
            });
        });
        
        // Terms link
        document.getElementById('termsLink').addEventListener('click', function(e) {
            e.preventDefault();
            Swal.fire({
                title: 'Terms of Service',
                html: '<div class="text-start"><p>This is a placeholder for the Garrison terms of service. In a real implementation, this would contain the full terms of service text.</p></div>',
                icon: 'info',
                confirmButtonColor: '#2563eb'
            });
        });
        
        // Contact link
        document.getElementById('contactLink').addEventListener('click', function(e) {
            e.preventDefault();
            Swal.fire({
                title: 'Contact Us',
                html: `
                    <div class="text-start">
                        <p class="mb-3">We'd love to hear from you! Please reach out using any of the following methods:</p>
                        <ul class="list-none mb-3">
                            <li><i class="fas fa-envelope me-2"></i> Email: info@garrison.com</li>
                            <li><i class="fas fa-phone me-2"></i> Phone: +1 (123) 456-7890</li>
                            <li><i class="fas fa-map-marker-alt me-2"></i> Address: 123 Time Street, Attendance City</li>
                        </ul>
                    </div>
                `,
                icon: 'info',
                confirmButtonColor: '#2563eb'
            });
        });
        
        // Close announcement banner if it exists
        const announcementBanner = document.getElementById('announcementBanner');
        if (announcementBanner) {
            document.getElementById('closeAnnouncement').addEventListener('click', function() {
                announcementBanner.classList.add('closing');
                setTimeout(() => {
                    announcementBanner.style.display = 'none';
                }, 300);
            });
        }
    });
</script>
@endpush
