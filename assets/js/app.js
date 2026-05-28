// assets/js/app.js
document.addEventListener('DOMContentLoaded', () => {

    // Add slight fade in animation to cards
    const cards = document.querySelectorAll('.card');
    cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(10px)';
        card.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
        setTimeout(() => {
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
            setTimeout(() => {
                card.style.transform = ''; // Clean up inline transform
            }, 400);
        }, 50 * index);
    });

    // Tabs System Logic
    const tabBtns = document.querySelectorAll('.tab-btn');
    const tabPanes = document.querySelectorAll('.tab-pane');

    if (tabBtns.length > 0) {
        tabBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                // Remove active class from all
                tabBtns.forEach(b => b.classList.remove('active'));
                tabPanes.forEach(p => p.classList.remove('active'));
                
                // Add active class to clicked
                btn.classList.add('active');
                const targetId = btn.getAttribute('data-tab');
                const targetPane = document.getElementById(targetId);
                if (targetPane) {
                    targetPane.classList.add('active');
                }
            });
        });
    }

    // Modals Logic
    const openModalBtns = document.querySelectorAll('[data-modal-target]');
    const closeBtns = document.querySelectorAll('.btn-close-modal');
    
    if (openModalBtns.length > 0) {
        openModalBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const targetId = btn.getAttribute('data-modal-target');
                const targetModal = document.getElementById(targetId);
                if (targetModal) {
                    targetModal.classList.add('active');
                }
            });
        });
    }

    if (closeBtns.length > 0) {
        closeBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const modal = btn.closest('.modal-overlay');
                if (modal) {
                    modal.classList.remove('active');
                }
            });
        });
    }

    // Close modal on click outside (mousedown+mouseup must both be on overlay)
    const modals = document.querySelectorAll('.modal-overlay');
    modals.forEach(modal => {
        let mouseDownTarget = null;
        modal.addEventListener('mousedown', (e) => {
            mouseDownTarget = e.target;
        });
        modal.addEventListener('mouseup', (e) => {
            if (mouseDownTarget === modal && e.target === modal) {
                modal.classList.remove('active');
            }
            mouseDownTarget = null;
        });
    });

    // Sidebar Dropdown Logic
    const dropdownToggles = document.querySelectorAll('.dropdown-toggle');
    dropdownToggles.forEach(toggle => {
        toggle.addEventListener('click', (e) => {
            e.preventDefault();
            const parent = toggle.closest('.nav-dropdown');
            parent.classList.toggle('active');
        });
    });

    // Mobile Sidebar Drawer Logic
    const mobileMenuToggle = document.getElementById('mobile-menu-toggle');
    const sidebar = document.querySelector('.sidebar');
    const sidebarOverlay = document.getElementById('sidebar-overlay');

    if (mobileMenuToggle && sidebar && sidebarOverlay) {
        function openSidebar() {
            sidebar.classList.add('open');
            sidebarOverlay.classList.add('active');
            document.body.style.overflow = 'hidden'; // Prevent background scrolling
        }

        function closeSidebar() {
            sidebar.classList.remove('open');
            sidebarOverlay.classList.remove('active');
            document.body.style.overflow = '';
        }

        mobileMenuToggle.addEventListener('click', openSidebar);
        sidebarOverlay.addEventListener('click', closeSidebar);
        
        // Also close sidebar if a nav item is clicked (useful on mobile)
        const navItems = sidebar.querySelectorAll('.nav-item');
        navItems.forEach(item => {
            item.addEventListener('click', closeSidebar);
        });
    }

    // Theme Toggle Logic
    const themeToggleBtn = document.querySelector('.theme-toggle-btn');
    if (themeToggleBtn) {
        const darkIcon = themeToggleBtn.querySelector('.dark-icon');
        const lightIcon = themeToggleBtn.querySelector('.light-icon');
        
        function updateThemeIcon(theme) {
            if (theme === 'dark') {
                darkIcon.style.display = 'none';
                lightIcon.style.display = 'block';
            } else {
                darkIcon.style.display = 'block';
                lightIcon.style.display = 'none';
            }
        }

        // Init icon state
        const currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
        updateThemeIcon(currentTheme);

        themeToggleBtn.addEventListener('click', () => {
            const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
            const newTheme = isDark ? 'light' : 'dark';
            
            if (newTheme === 'dark') {
                document.documentElement.setAttribute('data-theme', 'dark');
            } else {
                document.documentElement.removeAttribute('data-theme');
            }
            
            localStorage.setItem('theme', newTheme);
            updateThemeIcon(newTheme);
        });
    }
});
