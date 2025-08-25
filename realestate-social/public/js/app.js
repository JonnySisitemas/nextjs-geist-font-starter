/**
 * Real Estate Social Network - Main JavaScript Application
 */

// Global application state
const App = {
    currentUser: null,
    apiBase: '/api',
    
    // Initialize the application
    init() {
        this.setupGlobalErrorHandling();
        this.setupFormValidation();
        this.setupImageHandling();
    },
    
    // Setup global error handling
    setupGlobalErrorHandling() {
        window.addEventListener('unhandledrejection', (event) => {
            console.error('Unhandled promise rejection:', event.reason);
            this.showNotification('Error inesperado. Por favor, recarga la página.', 'error');
        });
        
        window.addEventListener('error', (event) => {
            console.error('JavaScript error:', event.error);
        });
    },
    
    // Setup form validation
    setupFormValidation() {
        // Add Bootstrap validation classes
        document.addEventListener('submit', (event) => {
            const form = event.target;
            if (form.classList.contains('needs-validation')) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }
        });
    },
    
    // Setup image handling
    setupImageHandling() {
        // Add error handling for images
        document.addEventListener('error', (event) => {
            if (event.target.tagName === 'IMG') {
                event.target.src = '/images/placeholder.jpg';
                event.target.alt = 'Imagen no disponible';
            }
        }, true);
    }
};

// API Helper functions
const API = {
    // Make authenticated API request
    async request(endpoint, options = {}) {
        const url = `${App.apiBase}${endpoint}`;
        const config = {
            headers: {
                'Content-Type': 'application/json',
                ...options.headers
            },
            ...options
        };
        
        try {
            const response = await fetch(url, config);
            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.message || `HTTP ${response.status}`);
            }
            
            return data;
        } catch (error) {
            console.error('API Request failed:', error);
            throw error;
        }
    },
    
    // Authentication methods
    auth: {
        async login(credentials) {
            return API.request('/auth.php?action=login', {
                method: 'POST',
                body: JSON.stringify(credentials)
            });
        },
        
        async register(userData) {
            return API.request('/auth.php?action=register', {
                method: 'POST',
                body: JSON.stringify(userData)
            });
        },
        
        async logout() {
            return API.request('/auth.php?action=logout', {
                method: 'POST'
            });
        },
        
        async getCurrentUser() {
            return API.request('/auth.php?action=me');
        }
    },
    
    // User management methods
    users: {
        async getPending() {
            return API.request('/users.php?action=pending');
        },
        
        async getAll() {
            return API.request('/users.php?action=all');
        },
        
        async approve(userId) {
            return API.request('/users.php?action=approve', {
                method: 'POST',
                body: JSON.stringify({ user_id: userId })
            });
        },
        
        async reject(userId) {
            return API.request('/users.php?action=reject', {
                method: 'POST',
                body: JSON.stringify({ user_id: userId })
            });
        },
        
        async ban(userId) {
            return API.request('/users.php?action=ban', {
                method: 'POST',
                body: JSON.stringify({ user_id: userId })
            });
        },
        
        async unban(userId) {
            return API.request('/users.php?action=unban', {
                method: 'POST',
                body: JSON.stringify({ user_id: userId })
            });
        },
        
        async promote(userId, role) {
            return API.request('/users.php?action=promote', {
                method: 'POST',
                body: JSON.stringify({ user_id: userId, role })
            });
        }
    },
    
    // Posts methods
    posts: {
        async getList(params = {}) {
            const queryString = new URLSearchParams(params).toString();
            return API.request(`/posts.php?${queryString}`);
        },
        
        async getDetail(postId) {
            return API.request(`/posts.php?action=detail&id=${postId}`);
        },
        
        async getMy(params = {}) {
            const queryString = new URLSearchParams({ action: 'my', ...params }).toString();
            return API.request(`/posts.php?${queryString}`);
        },
        
        async create(postData) {
            return API.request('/posts.php', {
                method: 'POST',
                body: JSON.stringify(postData)
            });
        },
        
        async update(postId, postData) {
            return API.request(`/posts.php?id=${postId}`, {
                method: 'PUT',
                body: JSON.stringify(postData)
            });
        },
        
        async delete(postId) {
            return API.request(`/posts.php?id=${postId}`, {
                method: 'DELETE'
            });
        }
    },
    
    // Messages methods
    messages: {
        async getList(params = {}) {
            const queryString = new URLSearchParams(params).toString();
            return API.request(`/messages.php?${queryString}`);
        },
        
        async getConversation(userId, params = {}) {
            const queryString = new URLSearchParams({ action: 'conversation', user_id: userId, ...params }).toString();
            return API.request(`/messages.php?${queryString}`);
        },
        
        async getUnreadCount() {
            return API.request('/messages.php?action=unread');
        },
        
        async send(messageData) {
            return API.request('/messages.php', {
                method: 'POST',
                body: JSON.stringify(messageData)
            });
        },
        
        async markAsRead(messageId) {
            return API.request(`/messages.php?action=read&id=${messageId}`, {
                method: 'PUT'
            });
        },
        
        async delete(messageId) {
            return API.request(`/messages.php?id=${messageId}`, {
                method: 'DELETE'
            });
        }
    },
    
    // Upload methods
    uploads: {
        async uploadImage(formData) {
            return fetch('/api/uploads.php', {
                method: 'POST',
                body: formData // Don't set Content-Type for FormData
            }).then(response => response.json());
        },
        
        async deleteImage(imageId) {
            return API.request(`/uploads.php?id=${imageId}`, {
                method: 'DELETE'
            });
        }
    }
};

// UI Helper functions
const UI = {
    // Show notification/toast
    showNotification(message, type = 'info', duration = 5000) {
        const alertClass = {
            'success': 'alert-success',
            'error': 'alert-danger',
            'warning': 'alert-warning',
            'info': 'alert-info'
        }[type] || 'alert-info';
        
        const alertHtml = `
            <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        
        // Try to find an alert container, or create one
        let container = document.getElementById('alert-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'alert-container';
            container.className = 'position-fixed top-0 end-0 p-3';
            container.style.zIndex = '9999';
            document.body.appendChild(container);
        }
        
        const alertElement = document.createElement('div');
        alertElement.innerHTML = alertHtml;
        container.appendChild(alertElement.firstElementChild);
        
        // Auto-dismiss after duration
        if (duration > 0) {
            setTimeout(() => {
                const alert = container.querySelector('.alert');
                if (alert) {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }
            }, duration);
        }
    },
    
    // Show loading state
    showLoading(element, show = true) {
        if (show) {
            element.classList.add('loading');
            element.disabled = true;
            
            // Add spinner if it's a button
            if (element.tagName === 'BUTTON') {
                const spinner = element.querySelector('.spinner-border');
                if (spinner) {
                    spinner.classList.remove('d-none');
                }
            }
        } else {
            element.classList.remove('loading');
            element.disabled = false;
            
            // Remove spinner
            if (element.tagName === 'BUTTON') {
                const spinner = element.querySelector('.spinner-border');
                if (spinner) {
                    spinner.classList.add('d-none');
                }
            }
        }
    },
    
    // Format currency
    formatCurrency(amount, currency = 'USD') {
        return new Intl.NumberFormat('es-ES', {
            style: 'currency',
            currency: currency,
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        }).format(amount);
    },
    
    // Format date
    formatDate(dateString, options = {}) {
        const defaultOptions = {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        };
        
        return new Date(dateString).toLocaleDateString('es-ES', { ...defaultOptions, ...options });
    },
    
    // Truncate text
    truncateText(text, maxLength = 100) {
        if (text.length <= maxLength) return text;
        return text.substring(0, maxLength).trim() + '...';
    },
    
    // Debounce function
    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },
    
    // Confirm dialog
    async confirm(message, title = 'Confirmar') {
        return new Promise((resolve) => {
            const result = window.confirm(`${title}\n\n${message}`);
            resolve(result);
        });
    },
    
    // Create pagination
    createPagination(container, pagination, onPageClick) {
        if (pagination.pages <= 1) {
            container.style.display = 'none';
            return;
        }
        
        container.style.display = 'block';
        
        let html = '<ul class="pagination justify-content-center">';
        
        // Previous button
        if (pagination.page > 1) {
            html += `<li class="page-item">
                <a class="page-link" href="#" data-page="${pagination.page - 1}">Anterior</a>
            </li>`;
        }
        
        // Page numbers
        const startPage = Math.max(1, pagination.page - 2);
        const endPage = Math.min(pagination.pages, pagination.page + 2);
        
        for (let i = startPage; i <= endPage; i++) {
            html += `<li class="page-item ${i === pagination.page ? 'active' : ''}">
                <a class="page-link" href="#" data-page="${i}">${i}</a>
            </li>`;
        }
        
        // Next button
        if (pagination.page < pagination.pages) {
            html += `<li class="page-item">
                <a class="page-link" href="#" data-page="${pagination.page + 1}">Siguiente</a>
            </li>`;
        }
        
        html += '</ul>';
        container.innerHTML = html;
        
        // Add click handlers
        container.addEventListener('click', (e) => {
            e.preventDefault();
            if (e.target.classList.contains('page-link')) {
                const page = parseInt(e.target.dataset.page);
                if (page && typeof onPageClick === 'function') {
                    onPageClick(page);
                }
            }
        });
    }
};

// Form validation helpers
const Validation = {
    // Validate email
    isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    },
    
    // Validate password strength
    isStrongPassword(password) {
        return password.length >= 6;
    },
    
    // Validate phone number
    isValidPhone(phone) {
        const phoneRegex = /^[\+]?[1-9][\d]{0,15}$/;
        return phoneRegex.test(phone.replace(/[\s\-\(\)]/g, ''));
    },
    
    // Validate required fields
    validateRequired(form) {
        const errors = {};
        const requiredFields = form.querySelectorAll('[required]');
        
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                errors[field.name] = 'Este campo es requerido';
            }
        });
        
        return errors;
    },
    
    // Show field error
    showFieldError(field, message) {
        // Remove existing error
        this.clearFieldError(field);
        
        field.classList.add('is-invalid');
        
        const errorDiv = document.createElement('div');
        errorDiv.className = 'invalid-feedback';
        errorDiv.textContent = message;
        
        field.parentNode.appendChild(errorDiv);
    },
    
    // Clear field error
    clearFieldError(field) {
        field.classList.remove('is-invalid');
        const errorDiv = field.parentNode.querySelector('.invalid-feedback');
        if (errorDiv) {
            errorDiv.remove();
        }
    },
    
    // Clear all form errors
    clearFormErrors(form) {
        const invalidFields = form.querySelectorAll('.is-invalid');
        invalidFields.forEach(field => this.clearFieldError(field));
    }
};

// Image handling utilities
const ImageUtils = {
    // Validate image file
    validateImage(file) {
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        const maxSize = 5 * 1024 * 1024; // 5MB
        
        if (!allowedTypes.includes(file.type)) {
            throw new Error('Tipo de archivo no permitido. Use JPG, PNG o GIF.');
        }
        
        if (file.size > maxSize) {
            throw new Error('El archivo es muy grande. Máximo 5MB.');
        }
        
        return true;
    },
    
    // Create image preview
    createPreview(file) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = (e) => resolve(e.target.result);
            reader.onerror = () => reject(new Error('Error al leer el archivo'));
            reader.readAsDataURL(file);
        });
    },
    
    // Resize image (basic client-side resize)
    async resizeImage(file, maxWidth = 800, maxHeight = 600, quality = 0.8) {
        return new Promise((resolve) => {
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            const img = new Image();
            
            img.onload = () => {
                // Calculate new dimensions
                let { width, height } = img;
                
                if (width > height) {
                    if (width > maxWidth) {
                        height = (height * maxWidth) / width;
                        width = maxWidth;
                    }
                } else {
                    if (height > maxHeight) {
                        width = (width * maxHeight) / height;
                        height = maxHeight;
                    }
                }
                
                canvas.width = width;
                canvas.height = height;
                
                // Draw and compress
                ctx.drawImage(img, 0, 0, width, height);
                
                canvas.toBlob(resolve, file.type, quality);
            };
            
            img.src = URL.createObjectURL(file);
        });
    }
};

// Local storage utilities
const Storage = {
    // Set item with expiration
    setItem(key, value, expirationMinutes = null) {
        const item = {
            value: value,
            timestamp: Date.now(),
            expiration: expirationMinutes ? Date.now() + (expirationMinutes * 60 * 1000) : null
        };
        
        localStorage.setItem(key, JSON.stringify(item));
    },
    
    // Get item with expiration check
    getItem(key) {
        const itemStr = localStorage.getItem(key);
        if (!itemStr) return null;
        
        try {
            const item = JSON.parse(itemStr);
            
            // Check expiration
            if (item.expiration && Date.now() > item.expiration) {
                localStorage.removeItem(key);
                return null;
            }
            
            return item.value;
        } catch (e) {
            localStorage.removeItem(key);
            return null;
        }
    },
    
    // Remove item
    removeItem(key) {
        localStorage.removeItem(key);
    },
    
    // Clear all items
    clear() {
        localStorage.clear();
    }
};

// Initialize the application when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    App.init();
});

// Export for global access
window.App = App;
window.API = API;
window.UI = UI;
window.Validation = Validation;
window.ImageUtils = ImageUtils;
window.Storage = Storage;
