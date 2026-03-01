<?php
// Make sure APP_URL is defined somewhere in your app
?>
<style>/* ================= FOOTER ================= */

.app-footer {
    background: #0f172a;
    color: #cbd5e1;
    margin-left: 240px;
    padding: 0;
    border-top: 1px solid #1e293b;
    font-size: 0.95rem;
}

/* Container */
.footer-container {
    max-width: 1400px;
    margin: auto;
    padding: 40px 28px 18px;
}

/* Top */
.footer-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #1e293b;
    padding-bottom: 20px;
    margin-bottom: 28px;
}

/* Brand */
.footer-brand {
    display: flex;
    align-items: center;
    gap: 14px;
    font-weight: 700;
    color: #f1f5f9;
}

.footer-brand i {
    font-size: 1.8rem;
    color: #6366f1;
}

.brand-text span {
    display: block;
    font-size: 1.2rem;
}

.brand-text small {
    color: #94a3b8;
}

/* Scroll top button */
.scroll-top {
    background: #6366f1;
    border: none;
    color: white;
    width: 42px;
    height: 42px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: .25s;
}

.scroll-top:hover {
    transform: translateY(-3px);
    background: #4f46e5;
}

/* Grid */
.footer-main {
    display: grid;
    grid-template-columns: repeat(auto-fit,minmax(220px,1fr));
    gap: 34px;
    margin-bottom: 32px;
}

.footer-section h5 {
    color: #f8fafc;
    margin-bottom: 14px;
    font-size: 1.05rem;
}

/* Links */
.footer-links,
.footer-contact {
    list-style: none;
    padding: 0;
    margin: 0;
}

.footer-links li,
.footer-contact li {
    margin-bottom: 10px;
}

.footer-links a {
    color: #94a3b8;
    text-decoration: none;
    display: flex;
    gap: 8px;
    align-items: center;
    transition: .2s;
}

.footer-links a:hover {
    color: #6366f1;
    transform: translateX(4px);
}

/* Contact */
.footer-contact i {
    margin-right: 8px;
    color: #6366f1;
}

.footer-contact a {
    color: #cbd5e1;
    text-decoration: none;
}

/* Social */
.footer-social {
    display: flex;
    gap: 10px;
    margin-top: 12px;
}

.social-link {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    background: #1e293b;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #cbd5e1;
    transition: .25s;
}

.social-link:hover {
    background: #6366f1;
    color: white;
    transform: translateY(-3px);
}

/* Bottom */
.footer-bottom {
    border-top: 1px solid #1e293b;
    padding-top: 16px;
    display: flex;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
}

.copyright {
    color: #94a3b8;
}

.version {
    margin-left: 10px;
    background: #1e293b;
    padding: 2px 8px;
    border-radius: 6px;
    font-size: 0.8rem;
}

.footer-bottom-links a {
    color: #94a3b8;
    text-decoration: none;
    margin: 0 6px;
    transition: .2s;
}

.footer-bottom-links a:hover {
    color: #6366f1;
}

.separator {
    color: #475569;
}

/* MOBILE */
@media (max-width: 991.98px) {
    .app-footer {
        margin-left: 0;
    }

    .footer-top {
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
    }

    .footer-bottom {
        flex-direction: column;
        align-items: flex-start;
    }
}</style>
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