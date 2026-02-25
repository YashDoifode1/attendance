<?php
include('includes/header.php'); // Include your professional header/sidebar if needed
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Page Not Found | QR Attendance</title>
    <link rel="icon" type="image/x-icon" href="/assets/favicon/favicon.ico">
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body, html {
            height: 100%;
            margin: 0;
            font-family: 'Segoe UI', sans-serif;
            background: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .container-404 {
            text-align: center;
            position: relative;
            animation: fadeIn 1s ease forwards;
        }

        h1 {
            font-size: 8rem;
            font-weight: bold;
            color: #dc2626;
            animation: bounce 2s infinite;
        }

        h2 {
            font-size: 2rem;
            color: #1e293b;
            margin-bottom: 1.5rem;
        }

        p {
            font-size: 1.1rem;
            color: #475569;
            margin-bottom: 2rem;
        }

        .btn-back {
            background: #4f46e5;
            color: #fff;
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .btn-back:hover {
            background: #6366f1;
            transform: scale(1.05);
        }

        /* QR Code Animation */
        .qr-container {
            margin: 2rem auto;
            width: 200px;
            height: 200px;
            perspective: 1000px;
        }

        .qr-card {
            width: 100%;
            height: 100%;
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
            border-radius: 12px;
            transform-style: preserve-3d;
            animation: rotateQR 4s infinite linear;
        }

        .qr-card img {
            width: 80%;
            height: 80%;
        }

        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
            40% { transform: translateY(-20px); }
            60% { transform: translateY(-10px); }
        }

        @keyframes rotateQR {
            0% { transform: rotateY(0deg); }
            100% { transform: rotateY(360deg); }
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px);}
            to { opacity: 1; transform: translateY(0);}
        }

        /* Responsive */
        @media(max-width:768px){
            h1 { font-size: 6rem; }
            h2 { font-size: 1.5rem; }
            .qr-container { width: 150px; height: 150px; }
        }
    </style>
</head>
<body>

<div class="container-404">
    <h1>404</h1>
    <h2>Oops! Page Not Found</h2>
    <p>The page you are looking for doesn’t exist or has been moved.<br> Maybe you need to scan a QR to check attendance instead?</p>

    <!-- Animated QR Code -->
    <div class="qr-container">
        <div class="qr-card">
            <img src="/assets/favicon/favicon.ico" alt="QR Code">
        </div>
    </div>

    <a href="/student/dashboard.php" class="btn-back">Go Back to Dashboard</a>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
