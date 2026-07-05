/**
 * Hotel Bellmounth - Premium Admin Dashboard Script
 * Handles seamless page transitions, interactive loading states, and elegant UI behaviors.
 */

document.addEventListener('DOMContentLoaded', () => {
    // 1. Initialize Seamless Page Transitions
    initPageTransitions();
    
    // 2. Enhance Dropdowns & Interactive Elements
    initInteractiveEnhancements();
    
    // 3. Prevent Modal Stacking Context Traps
    initModalStackingFix();
});

/**
 * Creates a premium slim golden progress bar at the top of the viewport
 * and intercepts internal link clicks to perform a beautiful page fade-out.
 */
function initPageTransitions() {
    // Dynamically inject the premium top progress bar
    const progressBar = document.createElement('div');
    progressBar.id = 'topProgressBar';
    progressBar.className = 'top-progress-bar';
    document.body.appendChild(progressBar);
    
    // Trigger entry transition (Page In)
    requestAnimationFrame(() => {
        progressBar.style.width = '40%';
        progressBar.style.opacity = '1';
        
        setTimeout(() => {
            progressBar.style.width = '100%';
            setTimeout(() => {
                progressBar.style.opacity = '0';
                setTimeout(() => {
                    progressBar.style.width = '0%';
                }, 300);
            }, 150);
        }, 100);
    });

    // Clear the stacking context of .main-content once the fade-in animation finishes.
    // This allows nested modals within the content area to render in the global stacking
    // context and correctly appear in front of the blurred backdrop.
    const mainContent = document.querySelector('.main-content');
    if (mainContent) {
        mainContent.addEventListener('animationend', (e) => {
            if (e.animationName === 'pageFadeIn') {
                mainContent.style.animation = 'none';
                mainContent.style.transform = 'none';
                mainContent.style.opacity = '1';
            }
        });
        
        // Stacking context fallback timeout (fires even if animation is halted/backgrounded)
        setTimeout(() => {
            mainContent.style.animation = 'none';
            mainContent.style.transform = 'none';
            mainContent.style.opacity = '1';
        }, 600);
    }

    // Intercept internal navigation for fade-out (Page Out)
    document.addEventListener('click', (e) => {
        const anchor = e.target.closest('a');
        if (!anchor) return;
        
        const href = anchor.getAttribute('href');
        
        // Skip handling for:
        // - Null or empty href
        // - External links (HTTP/HTTPS domains different from current)
        // - JavaScript calls / Void
        // - Target blank links
        // - Modals / collapse toggles
        // - Same page anchors (#)
        // - Logout (immediate redirect)
        // - Non-GET link actions (e.g. data-attributes or download links)
        if (
            !href ||
            href.startsWith('http') && !href.includes(window.location.hostname) ||
            href.startsWith('javascript:') ||
            href.startsWith('#') ||
            anchor.getAttribute('target') === '_blank' ||
            anchor.getAttribute('data-bs-toggle') ||
            anchor.getAttribute('data-bs-target') ||
            anchor.hasAttribute('download') ||
            href.includes('logout.php')
        ) {
            return;
        }

        // Check if navigation keys are pressed (Ctrl, Command, Shift, Alt)
        if (e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) {
            return;
        }

        // Seamless Page Transition Out
        e.preventDefault();
        
        // Animate the golden progress bar across the top
        progressBar.style.opacity = '1';
        progressBar.style.width = '75%';
        
        // Animate the main content out
        document.body.classList.add('page-transitioning');
        
        // Redirect after animation completes
        setTimeout(() => {
            window.location.href = href;
        }, 280); // Synchronized with CSS transition timings
    });

    // Address browser back/forward cache restore (prevent frozen fade-out state)
    window.addEventListener('pageshow', (event) => {
        if (event.persisted) {
            document.body.classList.remove('page-transitioning');
            progressBar.style.width = '0%';
            progressBar.style.opacity = '0';
        }
    });
}

/**
 * Standardizes micro-interactions for forms, dropdowns, and button ripple effects
 */
function initInteractiveEnhancements() {
    // Subtle loading feedback for action forms (e.g. Save, Update buttons)
    const actionForms = document.querySelectorAll('form:not([method="get"])');
    actionForms.forEach(form => {
        form.addEventListener('submit', (e) => {
            // Find submit button in this form
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn && !form.classList.contains('no-spinner')) {
                // Show standard loading indicator in submit button
                const originalHtml = submitBtn.innerHTML;
                submitBtn.disabled = true;
                submitBtn.innerHTML = `<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Memproses...`;
                
                // Show global elegant loading overlay
                const spinner = document.getElementById('spinnerOverlay');
                if (spinner) {
                    spinner.classList.add('show');
                }
                
                // Fallback in case of form failure to restore button
                setTimeout(() => {
                    if (submitBtn.disabled) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalHtml;
                        if (spinner) spinner.classList.remove('show');
                    }
                }, 8000);
            }
        });
    });

    // Make dropdown toggles trigger active hover animations
    const dropdownToggles = document.querySelectorAll('.dropdown-toggle');
    dropdownToggles.forEach(toggle => {
        toggle.addEventListener('show.bs.dropdown', () => {
            toggle.style.transform = 'scale(0.98)';
            setTimeout(() => { toggle.style.transform = ''; }, 150);
        });
    });
}

/**
 * Automatically relocates all modals to the document body to prevent stacking context
 * issues from parent elements with active animations, positions, or overflows.
 */
function initModalStackingFix() {
    const relocateModal = (modal) => {
        if (modal.parentElement !== document.body) {
            // Check if the modal is currently open to avoid destroying Bootstrap states
            const isShown = modal.classList.contains('show');
            document.body.appendChild(modal);
            
            // Re-initialize Bootstrap modal instance if it was already created and open
            if (isShown && window.bootstrap && window.bootstrap.Modal) {
                const modalInstance = window.bootstrap.Modal.getInstance(modal);
                if (modalInstance) {
                    modalInstance.handleUpdate();
                }
            }
        }
    };

    // Relocate existing modals on page load
    document.querySelectorAll('.modal').forEach(relocateModal);

    // Observe body for dynamically inserted modals to ensure they also get relocated
    const observer = new MutationObserver(mutations => {
        mutations.forEach(mutation => {
            mutation.addedNodes.forEach(node => {
                if (node.nodeType === Node.ELEMENT_NODE) {
                    if (node.classList && node.classList.contains('modal')) {
                        relocateModal(node);
                    }
                    // Also check for nested modals in added elements
                    node.querySelectorAll('.modal').forEach(relocateModal);
                }
            });
        });
    });

    observer.observe(document.body, { childList: true, subtree: true });
}
