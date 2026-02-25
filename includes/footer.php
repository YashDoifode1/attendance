<?php
// Make sure APP_URL is defined somewhere in your app
?>

</main> <!-- Close main-content from header -->

<footer class="app-footer">
    <div class="footer-container">
        <!-- Footer Top Section -->
        <div class="footer-top">
            <div class="footer-brand">
                <i class="bi bi-journal-check"></i>
                <div class="brand-text">
                    <span>Attendance Pro</span>
                    <small>Enterprise Attendance Management</small>
                </div>
            </div>

            <div class="footer-actions">
                <button class="scroll-top" onclick="scrollToTop()" aria-label="Scroll to top">
                    <i class="bi bi-arrow-up"></i>
                </button>
            </div>
        </div>

        <!-- Footer Main Content -->
        <div class="footer-main">
            <!-- Quick Links -->
            <div class="footer-section">
                <h5>Quick Links</h5>
                <ul class="footer-links">
                    <li><a href="<?= APP_URL ?>/index.php"><i class="bi bi-chevron-right"></i> Home</a></li>
                    <li><a href="<?= APP_URL ?>/about.php"><i class="bi bi-chevron-right"></i> About Us</a></li>
                    <li><a href="<?= APP_URL ?>/contact.php"><i class="bi bi-chevron-right"></i> Contact</a></li>
                    <li><a href="<?= APP_URL ?>/help.php"><i class="bi bi-chevron-right"></i> Help Center</a></li>
                </ul>
            </div>

            <!-- Student Resources -->
            <div class="footer-section">
                <h5>Student Resources</h5>
                <ul class="footer-links">
                    <li><a href="<?= APP_URL ?>/student/view_attendance.php"><i class="bi bi-chevron-right"></i> Attendance</a></li>
                    <li><a href="<?= APP_URL ?>/student/timetable.php"><i class="bi bi-chevron-right"></i> Timetable</a></li>
                    <li><a href="<?= APP_URL ?>/student/subjects.php"><i class="bi bi-chevron-right"></i> Subjects</a></li>
                    <li><a href="<?= APP_URL ?>/student/notices.php"><i class="bi bi-chevron-right"></i> Notices</a></li>
                </ul>
            </div>

            <!-- Support -->
            <div class="footer-section">
                <h5>Support</h5>
                <ul class="footer-links">
                    <li><a href="<?= APP_URL ?>/faq.php"><i class="bi bi-chevron-right"></i> FAQ</a></li>
                    <li><a href="<?= APP_URL ?>/privacy.php"><i class="bi bi-chevron-right"></i> Privacy Policy</a></li>
                    <li><a href="<?= APP_URL ?>/terms.php"><i class="bi bi-chevron-right"></i> Terms of Use</a></li>
                    <li><a href="<?= APP_URL ?>/sitemap.php"><i class="bi bi-chevron-right"></i> Sitemap</a></li>
                </ul>
            </div>

            <!-- Contact Info -->
            <div class="footer-section contact-info">
                <h5>Contact Us</h5>
                <ul class="footer-contact">
                    <li>
                        <i class="bi bi-envelope"></i>
                        <a href="mailto:support@attendancepro.com">support@attendancepro.com</a>
                    </li>
                    <li>
                        <i class="bi bi-telephone"></i>
                        <a href="tel:+1234567890">+1 (234) 567-890</a>
                    </li>
                    <li>
                        <i class="bi bi-geo-alt"></i>
                        <span>123 Education St, Learning City</span>
                    </li>
                </ul>
                
                <!-- Social Links -->
                <div class="footer-social">
                    <a href="#" class="social-link" title="Facebook" aria-label="Facebook">
                        <i class="bi bi-facebook"></i>
                    </a>
                    <a href="#" class="social-link" title="Twitter" aria-label="Twitter">
                        <i class="bi bi-twitter-x"></i>
                    </a>
                    <a href="#" class="social-link" title="LinkedIn" aria-label="LinkedIn">
                        <i class="bi bi-linkedin"></i>
                    </a>
                    <a href="#" class="social-link" title="Instagram" aria-label="Instagram">
                        <i class="bi bi-instagram"></i>
                    </a>
                    <a href="#" class="social-link" title="GitHub" aria-label="GitHub">
                        <i class="bi bi-github"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Footer Bottom Section -->
        <div class="footer-bottom">
            <div class="copyright">
                <i class="bi bi-c-circle"></i> <?= date('Y') ?> Attendance Pro. All rights reserved.
                <span class="version">v2.0.0</span>
            </div>
            
            <div class="footer-bottom-links">
                <a href="<?= APP_URL ?>/privacy.php">Privacy</a>
                <span class="separator">•</span>
                <a href="<?= APP_URL ?>/terms.php">Terms</a>
                <span class="separator">•</span>
                <a href="<?= APP_URL ?>/cookies.php">Cookies</a>
                <span class="separator">•</span>
                <a href="<?= APP_URL ?>/accessibility.php">Accessibility</a>
            </div>
        </div>
    </div>
</footer>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>

<script>
    // Initialize AOS
    AOS.init({
        duration: 800,
        once: true,
        offset: 100
    });

    // Hide page loader
    window.addEventListener('load', function() {
        const loader = document.getElementById('pageLoader');
        if (loader) {
            loader.style.opacity = '0';
            setTimeout(() => {
                loader.style.display = 'none';
            }, 300);
        }
    });

    // Toggle Sidebar
    function toggleSidebar() {
        const sidebar = document.querySelector('nav.sidebar');
        const overlay = document.querySelector('.sidebar-overlay');
        sidebar.classList.toggle('active');
        overlay.classList.toggle('active');
        
        // Prevent body scroll when sidebar is open on mobile
        if (window.innerWidth <= 991.98) {
            document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : '';
        }
    }

    // Close sidebar when clicking on overlay
    document.querySelector('.sidebar-overlay')?.addEventListener('click', function() {
        document.querySelector('nav.sidebar').classList.remove('active');
        this.classList.remove('active');
        document.body.style.overflow = '';
    });

    // Scroll to top function
    function scrollToTop() {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    }

    // Show/hide scroll to top button based on scroll position
    window.addEventListener('scroll', function() {
        const scrollTop = document.querySelector('.scroll-top');
        if (scrollTop) {
            if (window.pageYOffset > 300) {
                scrollTop.style.opacity = '1';
                scrollTop.style.visibility = 'visible';
            } else {
                scrollTop.style.opacity = '0';
                scrollTop.style.visibility = 'hidden';
            }
        }
    });

    // Close sidebar on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const sidebar = document.querySelector('nav.sidebar');
            const overlay = document.querySelector('.sidebar-overlay');
            if (sidebar.classList.contains('active')) {
                sidebar.classList.remove('active');
                overlay.classList.remove('active');
                document.body.style.overflow = '';
            }
        }
    });

    // Handle window resize
    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            if (window.innerWidth > 991.98) {
                const sidebar = document.querySelector('nav.sidebar');
                const overlay = document.querySelector('.sidebar-overlay');
                sidebar.classList.remove('active');
                overlay?.classList.remove('active');
                document.body.style.overflow = '';
            }
        }, 250);
    });
</script>

</body>
</html>