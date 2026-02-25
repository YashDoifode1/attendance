<?php
// admin/includes/footer.php
?>
</main>

<!-- Bootstrap JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Custom JavaScript -->
<script>
    // ==================== SIDEBAR TOGGLE ====================
    function toggleSidebar() {
        const sidebar = document.querySelector('nav.sidebar');
        const overlay = document.querySelector('.sidebar-overlay');
        
        sidebar.classList.toggle('active');
        overlay.classList.toggle('active');
        
        // Prevent body scroll when sidebar is open on mobile
        if (sidebar.classList.contains('active')) {
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = '';
        }
    }

    // Close sidebar with Escape key
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

    // ==================== NOTIFICATION PANEL ====================
    function toggleNotifications() {
        let notificationPanel = document.getElementById('notificationPanel');
        
        if (!notificationPanel) {
            // Create notification panel
            notificationPanel = document.createElement('div');
            notificationPanel.id = 'notificationPanel';
            notificationPanel.className = 'position-absolute end-0 mt-2 me-4';
            notificationPanel.style.cssText = `
                top: 70px;
                z-index: 1050;
                min-width: 360px;
                background: var(--sidebar-secondary);
                border: 1px solid var(--border-color);
                border-radius: 16px;
                box-shadow: 0 20px 30px -10px rgba(0, 0, 0, 0.5);
                overflow: hidden;
            `;
            
            // Sample notifications
            notificationPanel.innerHTML = `
                <div class="p-3 border-bottom" style="border-color: var(--border-color) !important;">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold" style="color: var(--text-primary);">Notifications</h6>
                        <span class="badge" style="background: var(--sidebar-active);">3 new</span>
                    </div>
                </div>
                <div class="notification-list" style="max-height: 320px; overflow-y: auto;">
                    <a href="#" class="text-decoration-none">
                        <div class="d-flex gap-3 p-3" style="transition: all 0.2s; border-bottom: 1px solid var(--border-color);" 
                           onmouseover="this.style.background='var(--sidebar-hover)'" 
                           onmouseout="this.style.background='transparent'">
                            <div style="background: rgba(59, 130, 246, 0.1); width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                                <i class="bi bi-person-plus" style="color: var(--sidebar-active);"></i>
                            </div>
                            <div>
                                <p style="color: var(--text-primary); margin: 0; font-size: 0.9rem; font-weight: 500;">New Student Registered</p>
                                <small style="color: var(--text-muted);">John Doe joined CS101</small>
                                <div style="color: var(--text-muted); font-size: 0.7rem; margin-top: 4px;">5 minutes ago</div>
                            </div>
                        </div>
                    </a>
                    <a href="#" class="text-decoration-none">
                        <div class="d-flex gap-3 p-3" style="transition: all 0.2s; border-bottom: 1px solid var(--border-color);"
                           onmouseover="this.style.background='var(--sidebar-hover)'" 
                           onmouseout="this.style.background='transparent'">
                            <div style="background: rgba(16, 185, 129, 0.1); width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                                <i class="bi bi-check-circle" style="color: var(--success);"></i>
                            </div>
                            <div>
                                <p style="color: var(--text-primary); margin: 0; font-size: 0.9rem; font-weight: 500;">Attendance Marked</p>
                                <small style="color: var(--text-muted);">CS101 - 45 students present</small>
                                <div style="color: var(--text-muted); font-size: 0.7rem; margin-top: 4px;">1 hour ago</div>
                            </div>
                        </div>
                    </a>
                    <a href="#" class="text-decoration-none">
                        <div class="d-flex gap-3 p-3" style="transition: all 0.2s;"
                           onmouseover="this.style.background='var(--sidebar-hover)'" 
                           onmouseout="this.style.background='transparent'">
                            <div style="background: rgba(245, 158, 11, 0.1); width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                                <i class="bi bi-exclamation-triangle" style="color: var(--warning);"></i>
                            </div>
                            <div>
                                <p style="color: var(--text-primary); margin: 0; font-size: 0.9rem; font-weight: 500;">Low Attendance Alert</p>
                                <small style="color: var(--text-muted);">5 students below 75%</small>
                                <div style="color: var(--text-muted); font-size: 0.7rem; margin-top: 4px;">3 hours ago</div>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="p-3 text-center border-top" style="border-color: var(--border-color) !important;">
                    <a href="#" style="color: var(--sidebar-active); text-decoration: none; font-size: 0.9rem;">View All Notifications</a>
                </div>
            `;
            
            // Position relative to notification button
            const btn = document.getElementById('notificationBtn');
            const rect = btn.getBoundingClientRect();
            notificationPanel.style.position = 'fixed';
            notificationPanel.style.top = (rect.bottom + 10) + 'px';
            notificationPanel.style.right = (window.innerWidth - rect.right) + 'px';
            
            document.body.appendChild(notificationPanel);
            
            // Close when clicking outside
            setTimeout(() => {
                document.addEventListener('click', function closeNotifications(e) {
                    if (!notificationPanel.contains(e.target) && e.target.id !== 'notificationBtn' && !e.target.closest('#notificationBtn')) {
                        notificationPanel.remove();
                        document.removeEventListener('click', closeNotifications);
                    }
                });
            }, 100);
            
        } else {
            notificationPanel.remove();
        }
    }

    // ==================== GLOBAL SEARCH ====================
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('globalSearch');
        
        if (searchInput) {
            // Keyboard shortcut (Cmd/Ctrl + K)
            document.addEventListener('keydown', function(e) {
                if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                    e.preventDefault();
                    searchInput.focus();
                    
                    // Add highlight effect
                    searchInput.parentElement.style.borderColor = 'var(--sidebar-active)';
                    searchInput.parentElement.style.boxShadow = '0 0 0 3px rgba(59, 130, 246, 0.2)';
                    
                    setTimeout(() => {
                        searchInput.parentElement.style.borderColor = '';
                        searchInput.parentElement.style.boxShadow = '';
                    }, 200);
                }
                
                // Clear search with Escape
                if (e.key === 'Escape' && document.activeElement === searchInput) {
                    searchInput.value = '';
                    searchInput.blur();
                }
            });
            
            // Search with debounce
            let searchTimeout;
            searchInput.addEventListener('input', function(e) {
                clearTimeout(searchTimeout);
                const query = e.target.value.trim();
                
                searchTimeout = setTimeout(() => {
                    if (query.length > 0) {
                        console.log('Searching for:', query);
                        // Implement your search logic here
                    }
                }, 300);
            });
        }
    });

    // ==================== ACTIVE LINK HIGHLIGHT ====================
    document.addEventListener('DOMContentLoaded', function() {
        const currentPath = window.location.pathname;
        const currentFile = currentPath.split('/').pop();
        
        document.querySelectorAll('.nav-link').forEach(link => {
            const href = link.getAttribute('href');
            if (href && href.includes(currentFile)) {
                link.classList.add('active');
                
                // Scroll to active link in sidebar
                setTimeout(() => {
                    link.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }, 100);
            }
        });
    });

    // ==================== RESPONSIVE HANDLING ====================
    window.addEventListener('resize', function() {
        // Close sidebar on window resize if open and screen becomes large
        if (window.innerWidth > 991.98) {
            const sidebar = document.querySelector('nav.sidebar');
            const overlay = document.querySelector('.sidebar-overlay');
            
            if (sidebar.classList.contains('active')) {
                sidebar.classList.remove('active');
                overlay.classList.remove('active');
                document.body.style.overflow = '';
            }
        }
        
        // Reposition notification panel if open
        const notificationPanel = document.getElementById('notificationPanel');
        if (notificationPanel) {
            const btn = document.getElementById('notificationBtn');
            const rect = btn.getBoundingClientRect();
            notificationPanel.style.top = (rect.bottom + 10) + 'px';
            notificationPanel.style.right = (window.innerWidth - rect.right) + 'px';
        }
    });

    // ==================== TOOLTIPS ====================
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize Bootstrap tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    });

    // ==================== PAGE TRANSITION ====================
    document.addEventListener('DOMContentLoaded', function() {
        document.body.style.opacity = '1';
    });

    // ==================== DROPDOWN POSITIONING ====================
    // Fix dropdown positioning for user menu
    document.querySelectorAll('.dropdown-toggle').forEach(dropdown => {
        new bootstrap.Dropdown(dropdown);
    });

    // ==================== UPDATE NOTIFICATION COUNT ====================
    function updateNotificationCount(count) {
        const badge = document.querySelector('.badge-count');
        if (badge) {
            if (count > 0) {
                badge.textContent = count;
                badge.style.display = 'flex';
            } else {
                badge.style.display = 'none';
            }
        }
    }

    // ==================== DEBUG INFO ====================
    console.log('Admin Panel Initialized', {
        user: '<?= htmlspecialchars($adminName) ?>',
        role: 'admin',
        page: '<?= $current_page ?>',
        theme: 'dark'
    });
</script>

<!-- Optional: Add any page-specific styles -->
<style>
    /* Smooth transitions */
    * {
        transition: background-color 0.2s ease, border-color 0.2s ease, transform 0.2s ease;
    }
    
    /* Custom focus styles */
    :focus {
        outline: none;
    }
    
    /* Loading state for content */
    .main-content {
        opacity: 1;
        transition: opacity 0.3s ease;
    }
    
    /* Better dropdown animations */
    .dropdown-menu {
        display: block;
        opacity: 0;
        transform: translateY(-10px);
        transition: opacity 0.2s ease, transform 0.2s ease;
        pointer-events: none;
    }
    
    .dropdown-menu.show {
        opacity: 1;
        transform: translateY(0);
        pointer-events: all;
    }
</style>

</body>
</html>