// public/assets/js/auth.js
class Auth {
    constructor() {
        this.setupEventListeners();
        this.checkAuthStatus();
    }
    
    setupEventListeners() {
        // Login form handler
        document.getElementById('loginForm')?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.login();
        });
        
        // Register form handler
        document.getElementById('registerForm')?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.register();
        });
        
        // Logout button handler
        document.getElementById('logoutBtn')?.addEventListener('click', () => {
            this.logout();
        });
    }
    
    async login() {
        const email = document.getElementById('email').value;
        const password = document.getElementById('password').value;
        
        // Show loading state
        this.showLoading(true);
        
        try {
            const response = await fetch('/api/login', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ email, password })
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Store user data in localStorage (optional)
                localStorage.setItem('user', JSON.stringify(data.user));
                
                // Show success message
                this.showMessage('Login successful!', 'success');
                
                // Redirect or update UI
                window.location.href = data.redirect || '/dashboard';
            } else {
                this.showMessage(data.message, 'error');
            }
        } catch (error) {
            this.showMessage('An error occurred. Please try again.', 'error');
        } finally {
            this.showLoading(false);
        }
    }
    
    async register() {
        const formData = {
            name: document.getElementById('name').value,
            email: document.getElementById('email').value,
            password: document.getElementById('password').value,
            password_confirmation: document.getElementById('password_confirmation').value
        };
        
        this.showLoading(true);
        
        try {
            const response = await fetch('/api/register', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(formData)
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showMessage('Registration successful! Redirecting...', 'success');
                setTimeout(() => {
                    window.location.href = '/dashboard';
                }, 2000);
            } else {
                if (data.errors) {
                    this.displayValidationErrors(data.errors);
                } else {
                    this.showMessage(data.message, 'error');
                }
            }
        } catch (error) {
            this.showMessage('Registration failed. Please try again.', 'error');
        } finally {
            this.showLoading(false);
        }
    }
    
    async checkAuthStatus() {
        try {
            const response = await fetch('/api/auth/check', {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            const data = await response.json();
            
            if (data.authenticated) {
                // Update UI for authenticated user
                this.updateUIForAuthenticatedUser(data.user);
            } else {
                // Update UI for guest
                this.updateUIForGuest();
            }
        } catch (error) {
            console.error('Auth check failed:', error);
        }
    }
    
    async logout() {
        try {
            const response = await fetch('/api/logout', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            const data = await response.json();
            
            if (data.success) {
                localStorage.removeItem('user');
                window.location.href = '/login';
            }
        } catch (error) {
            console.error('Logout failed:', error);
        }
    }
    
    displayValidationErrors(errors) {
        // Clear existing errors
        document.querySelectorAll('.error-message').forEach(el => el.remove());
        
        // Display new errors
        for (const [field, messages] of Object.entries(errors)) {
            const input = document.getElementById(field);
            if (input) {
                const errorDiv = document.createElement('div');
                errorDiv.className = 'error-message text-red-500 text-sm mt-1';
                errorDiv.textContent = messages[0];
                input.parentNode.appendChild(errorDiv);
                input.classList.add('border-red-500');
            }
        }
    }
    
    showMessage(message, type) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `alert alert-${type} fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg`;
        messageDiv.textContent = message;
        document.body.appendChild(messageDiv);
        
        setTimeout(() => {
            messageDiv.remove();
        }, 3000);
    }
    
    showLoading(show) {
        const loader = document.getElementById('loader');
        if (loader) {
            loader.style.display = show ? 'flex' : 'none';
        }
    }
    
    updateUIForAuthenticatedUser(user) {
        // Update navigation
        const authLinks = document.querySelectorAll('.auth-only');
        authLinks.forEach(el => el.style.display = 'block');
        
        const guestLinks = document.querySelectorAll('.guest-only');
        guestLinks.forEach(el => el.style.display = 'none');
        
        // Update user profile section
        const userNameSpan = document.getElementById('user-name');
        if (userNameSpan) userNameSpan.textContent = user.name;
    }
    
    updateUIForGuest() {
        const authLinks = document.querySelectorAll('.auth-only');
        authLinks.forEach(el => el.style.display = 'none');
        
        const guestLinks = document.querySelectorAll('.guest-only');
        guestLinks.forEach(el => el.style.display = 'block');
    }
}

// Initialize auth when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.auth = new Auth();
});