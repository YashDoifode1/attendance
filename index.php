<?php
session_start();

// Redirect logged-in users to their dashboard
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'] ?? 'student';
    $redirect = match($role) {
        'admin'   => 'admin/dashboard.php',
        'faculty' => 'faculty/dashboard.php',
        default   => 'student/dashboard.php',
    };
    header("Location: $redirect");
    exit();
}
?>
<?php
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['contact_submit'])) {
    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $date    = date('Y-m-d H:i:s');

    if (!empty($name) && !empty($email) && !empty($message)) {
        $data = [$date, $name, $email, $subject, $message];
        
        $file = 'contact_submissions.csv';
        
        // Add header only if file doesn't exist
        if (!file_exists($file)) {
            $header = ['Date', 'Name', 'Email', 'Subject', 'Message'];
            $fp = fopen($file, 'a');
            fputcsv($fp, $header);
            fclose($fp);
        }

        // Append the new submission
        $fp = fopen($file, 'a');
        fputcsv($fp, $data);
        fclose($fp);

        // Optional: redirect with success message
        header("Location: index.php?status=success");
        exit;
    } else {
        // Optional: handle error
        $error = "Please fill all required fields";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <meta name="description" content="Attendance Pro - Modern, secure college attendance management system with QR, biometric, real-time analytics and role-based access."/>
    <title>Attendance Pro – Smart College Attendance System</title>

    <!-- Favicons -->
     <!-- ✅ FAVICONS (assets in ROOT) -->
    <link rel="icon" type="image/x-icon" href="assets/favicon/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/favicon/favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/favicon/apple-touch-icon.png">
    <link rel="manifest" href="assets/favicon/site.webmanifest">

    <!-- Bootstrap 5 + Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --dark: #0f172a;
            --gray: #64748b;
            --light: #f8fafc;
        }
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            color: #1e293b;
            background: #ffffff;
        }
        .navbar {
            background: rgba(255,255,255,0.96);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(226,232,240,0.6);
        }
        .navbar-brand {
            font-weight: 700;
            font-size: 1.65rem;
            color: var(--primary) !important;
        }
        .nav-link {
            font-weight: 500;
            color: var(--dark) !important;
            transition: color 0.2s;
        }
        .nav-link:hover { color: var(--primary) !important; }
        .section-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 3.5rem;
            color: var(--dark);
        }
        .card-feature, .card-pricing, .card-testimonial, .card-blog {
            border: none;
            border-radius: 16px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.07);
            transition: all 0.3s ease;
        }
        .card-feature:hover, .card-pricing:hover, .card-testimonial:hover, .card-blog:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 50px rgba(0,0,0,0.12);
        }
        .hero {
            min-height: 90vh;
            background: linear-gradient(rgba(15,23,42,0.72), rgba(15,23,42,0.82)),
                        url('https://images.unsplash.com/photo-1523050854058-8df901e9cac2?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=90') center/cover no-repeat;
            color: white;
            padding: 160px 0 120px;
        }
        .hero h1 {
            font-size: clamp(2.8rem, 7vw, 4.5rem);
            font-weight: 800;
            line-height: 1.1;
        }
        .btn-hero {
            padding: 0.9rem 2.3rem;
            font-weight: 600;
            border-radius: 12px;
            transition: all 0.25s ease;
        }
        .btn-primary-custom {
            background: var(--primary);
            border: none;
        }
        .btn-primary-custom:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }
        .btn-outline-hero {
            border: 2px solid white;
            color: white;
        }
        .btn-outline-hero:hover {
            background: white;
            color: var(--dark);
        }
        footer {
            background: var(--dark);
            color: #cbd5e1;
            padding: 5rem 0 3rem;
        }
        @media (max-width: 992px) {
            .hero { padding-top: 140px; padding-bottom: 100px; }
            .section-title { font-size: 2.2rem; }
        }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg fixed-top shadow-sm">
    <div class="container">
        <a class="navbar-brand" href="#">Attendance Pro</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto align-items-center gap-3 gap-lg-4">
                <li class="nav-item"><a class="nav-link" href="#features">Features</a></li>
                <li class="nav-item"><a class="nav-link" href="#how-it-works">How It Works</a></li>
                <li class="nav-item"><a class="nav-link" href="#plans">Plans</a></li>
                <li class="nav-item"><a class="nav-link" href="#testimonials">Testimonials</a></li>
                <li class="nav-item"><a class="nav-link" href="#blog">Blog</a></li>
                <li class="nav-item"><a class="nav-link" href="#team">Team</a></li>
                <li class="nav-item"><a class="nav-link" href="#contact">Contact</a></li>
                <li class="nav-item ms-lg-3">
                    <a class="btn btn-primary-custom text-white px-4 btn-hero" href="auth/login.php">Login</a>
                </li>
                <li class="nav-item">
                    <a class="btn btn-outline-hero px-4 btn-hero" href="auth/register.php">Sign Up</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Hero -->
<section class="hero text-center">
    <div class="container">
        <h1 class="mb-4">Modern Attendance Management<br>for Educational Institutions</h1>
        <p class="lead mx-auto mb-5" style="max-width: 680px; opacity: 0.94;">
            Real-time tracking, QR & biometric support, powerful analytics, automated reports and secure role-based access — built for colleges & universities.
        </p>
        <div class="mt-5">
            <a href="auth/register.php" class="btn btn-outline-hero btn-lg btn-hero me-4 px-5">Start Free Trial</a>
            <a href="auth/login.php" class="btn btn-primary-custom btn-lg btn-hero px-5">Login</a>
        </div>
    </div>
</section>

<!-- Features -->
<section id="features" class="py-5 bg-light">
    <div class="container">
        <h2 class="section-title text-center">Powerful Features</h2>
        <div class="row g-4">
            <div class="col-md-6 col-lg-4">
                <div class="card card-feature h-100 p-4 text-center">
                    <i class="bi bi-qr-code-scan display-4 text-primary mb-4"></i>
                    <h4>QR & Biometric Attendance</h4>
                    <p class="text-muted">Fast, secure & contactless marking with anti-proxy protection</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="card card-feature h-100 p-4 text-center">
                    <i class="bi bi-graph-up-arrow display-4 text-primary mb-4"></i>
                    <h4>Advanced Analytics & Reports</h4>
                    <p class="text-muted">Defaulter lists, trend analysis, 80-20 reports & export (PDF/Excel)</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="card card-feature h-100 p-4 text-center">
                    <i class="bi bi-shield-lock display-4 text-primary mb-4"></i>
                    <h4>Role-Based Security</h4>
                    <p class="text-muted">Separate dashboards & permissions for Admin, Faculty & Students</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="card card-feature h-100 p-4 text-center">
                    <i class="bi bi-bell-fill display-4 text-primary mb-4"></i>
                    <h4>Automated Notifications</h4>
                    <p class="text-muted">Low attendance alerts via email & SMS to students & parents</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="card card-feature h-100 p-4 text-center">
                    <i class="bi bi-cloud-check display-4 text-primary mb-4"></i>
                    <h4>Cloud & Mobile Ready</h4>
                    <p class="text-muted">Access from anywhere — fully responsive web & mobile friendly</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="card card-feature h-100 p-4 text-center">
                    <i class="bi bi-file-earmark-spreadsheet display-4 text-primary mb-4"></i>
                    <h4>Easy Data Export</h4>
                    <p class="text-muted">Monthly, semester & custom reports in PDF, Excel & CSV</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- How It Works -->
<section id="how-it-works" class="py-5">
    <div class="container">
        <h2 class="section-title text-center">How It Works</h2>
        <div class="row g-5 align-items-center">
            <div class="col-lg-6">
                <img src="https://images.unsplash.com/photo-1581091226825-a6a2a5aee158?auto=format&fit=crop&w=1200&q=80" class="img-fluid rounded shadow-lg" alt="Student scanning QR">
            </div>
            <div class="col-lg-6">
                <div class="d-flex flex-column gap-5">
                    <div>
                        <h4 class="fw-bold">1. Generate Unique QR Code</h4>
                        <p class="text-muted">Each lecture or class session automatically generates a secure, time-bound QR code.</p>
                    </div>
                    <div>
                        <h4 class="fw-bold">2. Students Scan & Mark Attendance</h4>
                        <p class="text-muted">One-tap marking with location & time verification to prevent proxy attendance.</p>
                    </div>
                    <div>
                        <h4 class="fw-bold">3. Faculty Reviews & Generates Reports</h4>
                        <p class="text-muted">Real-time dashboard with edit options, analytics & instant report generation.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Pricing / Plans -->
<section id="plans" class="py-5 bg-light">
    <div class="container">
        <h2 class="section-title text-center">Choose Your Plan</h2>
        <div class="row g-4 justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="card card-pricing h-100 p-4 text-center">
                    <h4 class="fw-bold mb-3">Basic</h4>
                    <div class="fs-1 fw-bold mb-3">Free</div>
                    <p class="text-muted mb-4">Perfect for small colleges & testing</p>
                    <ul class="list-unstyled mb-4">
                        <li>Up to 500 students</li>
                        <li>QR Attendance</li>
                        <li>Basic Reports</li>
                        <li>Email Support</li>
                    </ul>
                    <a href="auth/register.php" class="btn btn-outline-primary btn-lg w-100">Get Started</a>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="card card-pricing h-100 p-4 text-center border-primary position-relative">
                    <span class="badge bg-primary position-absolute top-0 start-50 translate-middle-x">Most Popular</span>
                    <h4 class="fw-bold mb-3">Pro</h4>
                    <div class="fs-1 fw-bold mb-3">₹999<span class="fs-5">/month</span></div>
                    <p class="text-muted mb-4">Best for medium & large colleges</p>
                    <ul class="list-unstyled mb-4">
                        <li>Unlimited students</li>
                        <li>Biometric + QR</li>
                        <li>Advanced Analytics</li>
                        <li>Automated Alerts (SMS + Email)</li>
                        <li>Priority Support</li>
                    </ul>
                    <a href="auth/register.php?plan=pro" class="btn btn-primary btn-lg w-100">Choose Pro</a>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="card card-pricing h-100 p-4 text-center">
                    <h4 class="fw-bold mb-3">Enterprise</h4>
                    <div class="fs-1 fw-bold mb-3">Custom</div>
                    <p class="text-muted mb-4">Tailored for universities & institutions</p>
                    <ul class="list-unstyled mb-4">
                        <li>Custom integrations</li>
                        <li>Dedicated server option</li>
                        <li>API Access</li>
                        <li>White-label solution</li>
                        <li>24×7 Premium Support</li>
                    </ul>
                    <a href="#contact" class="btn btn-outline-primary btn-lg w-100">Contact Sales</a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Testimonials -->
<section id="testimonials" class="py-5">
    <div class="container">
        <h2 class="section-title text-center">What Our Users Say</h2>
        <div class="row g-4">
            <div class="col-md-6 col-lg-4">
                <div class="card card-testimonial h-100 p-4">
                    <div class="mb-3">
                        <i class="bi bi-star-fill text-warning"></i><i class="bi bi-star-fill text-warning"></i>
                        <i class="bi bi-star-fill text-warning"></i><i class="bi bi-star-fill text-warning"></i>
                        <i class="bi bi-star-fill text-warning"></i>
                    </div>
                    <p class="mb-4">"Reduced proxy attendance by 95% and saved faculty 15+ hours every month on manual entry."</p>
                    <div>
                        <strong>Dr. Anjali Sharma</strong><br>
                        <small class="text-muted">HOD Computer Science, ABC Institute of Technology</small>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="card card-testimonial h-100 p-4">
                    <div class="mb-3">
                        <i class="bi bi-star-fill text-warning"></i><i class="bi bi-star-fill text-warning"></i>
                        <i class="bi bi-star-fill text-warning"></i><i class="bi bi-star-fill text-warning"></i>
                        <i class="bi bi-star-fill text-warning"></i>
                    </div>
                    <p class="mb-4">"The analytics dashboard is excellent. We can now easily track and improve student attendance patterns."</p>
                    <div>
                        <strong>Prof. Rajesh Kumar</strong><br>
                        <small class="text-muted">Dean Academics, XYZ University</small>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="card card-testimonial h-100 p-4">
                    <div class="mb-3">
                        <i class="bi bi-star-fill text-warning"></i><i class="bi bi-star-fill text-warning"></i>
                        <i class="bi bi-star-fill text-warning"></i><i class="bi bi-star-fill text-warning"></i>
                        <i class="bi bi-star-fill text-warning"></i>
                    </div>
                    <p class="mb-4">"Super easy to implement and students love the QR-based attendance — no more long queues!"</p>
                    <div>
                        <strong>Sneha Patel</strong><br>
                        <small class="text-muted">Student Coordinator, Modern College</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Blog -->
<section id="blog" class="py-5 bg-light">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-5 flex-wrap gap-3">
            <h2 class="section-title m-0">Latest Articles</h2>
            <a href="#" class="btn btn-outline-primary">View All Posts →</a>
        </div>
        <div class="row g-4">
            <div class="col-md-6 col-lg-3">
                <div class="card card-blog h-100">
                    <img src="https://images.unsplash.com/photo-1581091226825-a6a2a5aee158?auto=format&fit=crop&w=800&q=80" class="card-img-top" alt="">
                    <div class="card-body p-4">
                        <span class="badge bg-primary mb-2">Technology</span>
                        <h5>Why QR Attendance is Replacing Traditional Methods</h5>
                        <p class="text-muted small">Discover how QR codes eliminate proxy attendance and save valuable time...</p>
                        <small class="text-muted">Jan 10, 2026 • 6 min read</small>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="card card-blog h-100">
                    <img src="https://images.unsplash.com/photo-1554224155-6726b3ff335f?auto=format&fit=crop&w=800&q=80" class="card-img-top" alt="">
                    <div class="card-body p-4">
                        <span class="badge bg-success mb-2">Analytics</span>
                        <h5>80-20 Rule: Helping Chronic Attendance Defaulters</h5>
                        <p class="text-muted small">Practical strategies colleges are using to improve overall attendance rates...</p>
                        <small class="text-muted">Dec 28, 2025 • 8 min read</small>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="card card-blog h-100">
                    <img src="https://images.unsplash.com/photo-1555066931-4365d14bab8c?auto=format&fit=crop&w=800&q=80" class="card-img-top" alt="">
                    <div class="card-body p-4">
                        <span class="badge bg-danger mb-2">Security</span>
                        <h5>Role-Based Access: Securing Attendance Data</h5>
                        <p class="text-muted small">Best practices to protect sensitive student attendance information...</p>
                        <small class="text-muted">Dec 15, 2025 • 5 min read</small>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="card card-blog h-100">
                    <img src="https://images.unsplash.com/photo-1517248135467-2c7ed3ab7229?auto=format&fit=crop&w=800&q=80" class="card-img-top" alt="">
                    <div class="card-body p-4">
                        <span class="badge bg-info mb-2">Mobile</span>
                        <h5>Why Colleges Are Moving to Mobile-First Attendance Systems</h5>
                        <p class="text-muted small">Benefits of cloud + mobile for faculty and students in 2026...</p>
                        <small class="text-muted">Nov 30, 2025 • 7 min read</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Team -->
<section id="team" class="py-5">
    <div class="container">
        <h2 class="section-title text-center">Meet Our Team</h2>
        <div class="row g-5 justify-content-center">
            <div class="col-6 col-sm-4 col-md-3 col-lg-2 text-center">
                <img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=400&h=400&fit=crop&crop=faces" class="rounded-circle mb-3 shadow" width="140" height="140" alt="">
                <h6 class="mb-1">Yash Doifode</h6>
                <p class="small text-muted mb-0">Founder & Lead Developer</p>
            </div>
            <div class="col-6 col-sm-4 col-md-3 col-lg-2 text-center">
                <img src="https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=400&h=400&fit=crop&crop=faces" class="rounded-circle mb-3 shadow" width="140" height="140" alt="">
                <h6 class="mb-1">Priya Mehta</h6>
                <p class="small text-muted mb-0">UI/UX Designer</p>
            </div>
            <div class="col-6 col-sm-4 col-md-3 col-lg-2 text-center">
                <img src="https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=400&h=400&fit=crop&crop=faces" class="rounded-circle mb-3 shadow" width="140" height="140" alt="">
                <h6 class="mb-1">Rahul Verma</h6>
                <p class="small text-muted mb-0">Backend Engineer</p>
            </div>
            <div class="col-6 col-sm-4 col-md-3 col-lg-2 text-center">
                <img src="https://images.unsplash.com/photo-1552058544-f2b08422138a?w=400&h=400&fit=crop&crop=faces" class="rounded-circle mb-3 shadow" width="140" height="140" alt="">
                <h6 class="mb-1">Neha Kapoor</h6>
                <p class="small text-muted mb-0">Frontend Developer</p>
            </div>
            <div class="col-6 col-sm-4 col-md-3 col-lg-2 text-center">
                <img src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=400&h=400&fit=crop&crop=faces" class="rounded-circle mb-3 shadow" width="140" height="140" alt="">
                <h6 class="mb-1">Aryan Singh</h6>
                <p class="small text-muted mb-0">Database Architect</p>
            </div>
            <div class="col-6 col-sm-4 col-md-3 col-lg-2 text-center">
                <img src="https://images.unsplash.com/photo-1519085360753-af0119f7cbe7?w=400&h=400&fit=crop&crop=faces" class="rounded-circle mb-3 shadow" width="140" height="140" alt="">
                <h6 class="mb-1">Kavya Reddy</h6>
                <p class="small text-muted mb-0">Quality Assurance Lead</p>
            </div>
        </div>
    </div>
</section>

<!-- Contact -->
<section id="contact" class="py-5 bg-light">
    <div class="container">
        <h2 class="section-title text-center">Get in Touch</h2>
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card border-0 shadow-lg p-4 p-lg-5">
                    <div class="row g-5">
                        <div class="col-lg-5">
                            <h4 class="mb-4">Contact Information</h4>
                            <div class="d-flex align-items-start gap-3 mb-4">
                                <i class="bi bi-geo-alt-fill fs-4 text-primary mt-1"></i>
                                <div>
                                    <strong>New Delhi, India</strong><br>
                                    <span class="text-muted">Office visits by appointment only</span>
                                </div>
                            </div>
                            <div class="d-flex align-items-start gap-3 mb-4">
                                <i class="bi bi-envelope-at-fill fs-4 text-primary mt-1"></i>
                                <div>
                                    <strong>support@attendancepro.in</strong><br>
                                    <span class="text-muted">Replies within 24 hours</span>
                                </div>
                            </div>
                            <div class="d-flex align-items-start gap-3 mb-4">
                                <i class="bi bi-telephone-fill fs-4 text-primary mt-1"></i>
                                <div>
                                    <strong>+91 98765 43210</strong><br>
                                    <span class="text-muted">Mon–Fri 10:00 AM – 6:00 PM IST</span>
                                </div>
                            </div>
                            <div class="mt-4">
                                <small class="text-muted">
                                    For urgent matters, please use email.<br>
                                    We usually respond within one business day.
                                </small>
                            </div>
                        </div>

                        <div class="col-lg-7">
                            <form method="POST" action="">
    <input type="hidden" name="contact_submit" value="1">
    
    <div class="row g-3">
        <div class="col-md-6">
            <input type="text" name="name" class="form-control form-control-lg" placeholder="Full Name" required>
        </div>
        <div class="col-md-6">
            <input type="email" name="email" class="form-control form-control-lg" placeholder="Email Address" required>
        </div>
    </div>
    
    <div class="mt-3">
        <input type="text" name="subject" class="form-control form-control-lg" placeholder="Subject" required>
    </div>
    
    <div class="mt-3">
        <textarea name="message" class="form-control form-control-lg" rows="6" placeholder="How can we help you?" required></textarea>
    </div>
    
    <div class="mt-4">
        <button type="submit" class="btn btn-primary-custom btn-lg w-100">
            Send Message →
        </button>
    </div>
</form>

<?php if (isset($_GET['status']) && $_GET['status'] === 'success'): ?>
    <div class="alert alert-success mt-4 text-center">
        Thank you! Your message has been received.
    </div>
<?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Footer -->
<footer class="text-center">
    <div class="container">
        <p class="lead mb-3">Ready to modernize your attendance system?</p>
        <a href="auth/register.php" class="btn btn-outline-light btn-lg px-5 mb-4">Start Free Today</a>
        <p class="mb-1">© <?= date("Y") ?> Attendance Pro. All rights reserved.</p>
        <small class="text-muted">Made with focus on security, simplicity & performance</small>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>