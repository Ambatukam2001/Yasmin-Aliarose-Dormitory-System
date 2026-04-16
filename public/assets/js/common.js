/**
 * common.js - Shared utilities across the site
 */

const YA_DORM = {
    /**
     * Shows a modal prompting the user to log in
     */
    showAuthModal() {
        // Build modal if not already present
        let overlay = document.getElementById('globalAuthOverlay');
        if (!overlay) {
            this.injectAuthModal();
            overlay = document.getElementById('globalAuthOverlay');
        }
        overlay.classList.add('open');
        document.body.style.overflow = 'hidden';
    },

    closeAuthModal() {
        const overlay = document.getElementById('globalAuthOverlay');
        if (overlay) overlay.classList.remove('open');
        document.body.style.overflow = '';
    },

    injectAuthModal() {
        const html = `
        <div id="globalAuthOverlay" class="bk-overlay">
            <div class="bk-modal" role="dialog" aria-modal="true">
                <div class="bk-modal-header">
                    <div>
                        <p class="bk-modal-floor">Access Required</p>
                        <h2 class="bk-modal-title">Please Log In</h2>
                    </div>
                    <button class="bk-close-btn" onclick="YA_DORM.closeAuthModal()" aria-label="Close">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="bk-auth-prompt">
                    <div class="bk-auth-icon">
                        <i class="fas fa-user-lock"></i>
                    </div>
                    <h2 style="margin-bottom: 0.5rem; color: var(--text-primary);">Account Required</h2>
                    <p style="color: var(--text-secondary); margin-bottom: 2rem;">Please log in to your account to proceed with your booking. Don't have an account yet? Register in just a few seconds!</p>
                    <div class="bk-auth-btns" style="display: flex; flex-direction: column; gap: 0.75rem;">
                        <a href="login.php" class="bk-btn bk-btn--primary" style="text-align: center;">Login to Account</a>
                        <a href="register.php" class="bk-btn bk-btn--ghost" style="text-align: center;">Create New Account</a>
                    </div>
                </div>
            </div>
        </div>`;
        document.body.insertAdjacentHTML('beforeend', html);
        
        // Add minimal styles if they don't exist
        if (!document.getElementById('globalModalStyles')) {
            const css = `
                .bk-overlay { position:fixed; inset:0; z-index:9999; background:rgba(0,0,0,0.6); backdrop-filter:blur(5px); display:none; align-items:center; justify-content:center; padding:1rem; }
                .bk-overlay.open { display:flex; }
                .bk-modal { background:var(--white); border-radius:1.5rem; width:100%; max-width:500px; padding:2.5rem; box-shadow:0 20px 50px rgba(0,0,0,0.2); }
                .bk-modal-header { display:flex; justify-content:space-between; margin-bottom:1.5rem; border-bottom: 1px solid var(--glass-border); padding-bottom: 1rem; }
                .bk-modal-title { font-family:'Outfit'; font-weight:800; font-size:1.5rem; margin:0; color: var(--text-primary); }
                .bk-modal-floor { font-size: 0.75rem; font-weight: 800; text-transform: uppercase; color: var(--primary); margin: 0; letter-spacing: 0.05em; }
                .bk-close-btn { background: none; border: none; font-size: 1.25rem; color: var(--text-muted); cursor: pointer; transition: var(--transition-base); }
                .bk-close-btn:hover { color: #ef4444; transform: rotate(90deg); }
                .bk-auth-prompt { text-align:center; }
                .bk-auth-icon { font-size:3.5rem; color:var(--primary); margin-bottom:1.5rem; opacity: 0.9; }
                .bk-btn { padding:1rem 1.5rem; border-radius:1rem; border:none; font-weight:800; cursor:pointer; text-decoration:none; display:inline-block; font-family: 'Outfit', sans-serif; transition: var(--transition-base); }
                .bk-btn--primary { background:var(--primary-gradient); color:#fff; box-shadow: 0 4px 14px 0 rgba(16, 185, 129, 0.39); }
                .bk-btn--primary:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(16, 185, 129, 0.23); }
                .bk-btn--ghost { background:var(--off-white); color:var(--text-secondary); border: 1px solid var(--glass-border); }
                .bk-btn--ghost:hover { background: #f1f5f9; color: var(--text-primary); }
            `;
            const s = document.createElement('style');
            s.id = 'globalModalStyles';
            s.textContent = css;
            document.head.appendChild(s);
        }

        // Close on overlay click
        const overlay = document.getElementById('globalAuthOverlay');
        overlay.onclick = (e) => { if (e.target === overlay) this.closeAuthModal(); };
    },

    /**
     * Standardized click handler for "Book Now" buttons
     */
    handleBookingClick(e) {
        if (!IS_LOGGED_IN) {
            e.preventDefault();
            this.showAuthModal();
            return false;
        }
        return true;
    }
};
