/**
 * Global JavaScript Functions
 * Sistem Manajemen Dokumen Laboratorium
 * Dinas Lingkungan Hidup Kabupaten Kediri
 * Version: 1.0.0
 */

// ================================================
// UTILITY FUNCTIONS
// ================================================

/**
 * Format file size to human readable
 */
function formatFileSize(bytes) {
    if (bytes >= 1073741824) {
        return (bytes / 1073741824).toFixed(2) + ' GB';
    } else if (bytes >= 1048576) {
        return (bytes / 1048576).toFixed(2) + ' MB';
    } else if (bytes >= 1024) {
        return (bytes / 1024).toFixed(2) + ' KB';
    } else if (bytes > 1) {
        return bytes + ' bytes';
    } else if (bytes == 1) {
        return bytes + ' byte';
    } else {
        return '0 bytes';
    }
}

/**
 * Escape HTML to prevent XSS
 */
function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}

/**
 * Show loading overlay
 */
function showLoading(message = 'Loading...') {
    const overlay = document.createElement('div');
    overlay.id = 'loadingOverlay';
    overlay.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.7);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 9999;
        color: white;
        font-size: 18px;
    `;
    overlay.innerHTML = `<div>${message}</div>`;
    document.body.appendChild(overlay);
}

/**
 * Hide loading overlay
 */
function hideLoading() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) {
        overlay.remove();
    }
}

/**
 * Show toast notification
 */
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: white;
        padding: 15px 20px;
        border-radius: 8px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        z-index: 10000;
        max-width: 300px;
        animation: slideDown 0.3s ease-out;
        border-left: 4px solid ${type === 'success' ? '#27ae60' : type === 'error' ? '#e74c3c' : '#3498db'};
    `;
    toast.textContent = message;
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.style.animation = 'fadeOut 0.3s ease-out';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// ================================================
// MODAL FUNCTIONS
// ================================================

/**
 * Open modal by ID
 */
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

/**
 * Close modal by ID
 */
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = 'auto';
    }
}

/**
 * Close modal when clicking outside
 */
window.addEventListener('click', function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.classList.remove('active');
        document.body.style.overflow = 'auto';
    }
});

// ================================================
// FILE UPLOAD FUNCTIONS
// ================================================

/**
 * Handle file input change
 */
function handleFileSelect(inputElement, maxSize, allowedTypes) {
    const file = inputElement.files[0];
    
    if (!file) {
        return { success: false, message: 'No file selected' };
    }
    
    // Check file size
    if (file.size > maxSize) {
        inputElement.value = '';
        return {
            success: false,
            message: `Ukuran file terlalu besar! Maksimal ${formatFileSize(maxSize)}`
        };
    }
    
    // Check file type
    if (allowedTypes && !allowedTypes.includes(file.type)) {
        inputElement.value = '';
        return {
            success: false,
            message: 'Tipe file tidak diperbolehkan!'
        };
    }
    
    return {
        success: true,
        file: file,
        name: file.name,
        size: formatFileSize(file.size),
        type: file.type
    };
}

/**
 * Setup drag and drop for file upload
 */
function setupDragDrop(dropZone, fileInput) {
    ['dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, preventDefaults, false);
    });
    
    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }
    
    dropZone.addEventListener('dragover', () => {
        dropZone.classList.add('dragover');
    });
    
    dropZone.addEventListener('dragleave', () => {
        dropZone.classList.remove('dragover');
    });
    
    dropZone.addEventListener('drop', (e) => {
        dropZone.classList.remove('dragover');
        const files = e.dataTransfer.files;
        
        if (files.length > 0) {
            fileInput.files = files;
            // Trigger change event
            const event = new Event('change');
            fileInput.dispatchEvent(event);
        }
    });
}

// ================================================
// FORM VALIDATION
// ================================================

/**
 * Validate form before submit
 */
function validateForm(formElement) {
    const requiredFields = formElement.querySelectorAll('[required]');
    let isValid = true;
    let firstInvalidField = null;
    
    requiredFields.forEach(field => {
        const value = field.value.trim();
        
        if (!value) {
            isValid = false;
            field.style.borderColor = '#e74c3c';
            
            if (!firstInvalidField) {
                firstInvalidField = field;
            }
        } else {
            field.style.borderColor = '';
        }
    });
    
    if (!isValid && firstInvalidField) {
        firstInvalidField.focus();
        showToast('Mohon lengkapi semua field yang wajib diisi!', 'error');
    }
    
    return isValid;
}

/**
 * Real-time validation
 */
function setupRealtimeValidation(formElement) {
    const requiredFields = formElement.querySelectorAll('[required]');
    
    requiredFields.forEach(field => {
        field.addEventListener('blur', function() {
            if (!this.value.trim()) {
                this.style.borderColor = '#e74c3c';
            } else {
                this.style.borderColor = '';
            }
        });
        
        field.addEventListener('input', function() {
            if (this.value.trim()) {
                this.style.borderColor = '';
            }
        });
    });
}

// ================================================
// CONFIRMATION DIALOG
// ================================================

/**
 * Show confirmation dialog
 */
function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

/**
 * Delete confirmation
 */
function confirmDelete(itemName, deleteUrl) {
    if (confirm(`Apakah Anda yakin ingin menghapus "${itemName}"?`)) {
        window.location.href = deleteUrl;
    }
}

// ================================================
// TABLE FUNCTIONS
// ================================================

/**
 * Search/filter table
 */
function filterTable(searchInput, tableId) {
    const filter = searchInput.value.toLowerCase();
    const table = document.getElementById(tableId);
    const rows = table.getElementsByTagName('tr');
    
    for (let i = 1; i < rows.length; i++) {
        const row = rows[i];
        const text = row.textContent.toLowerCase();
        
        if (text.includes(filter)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    }
}

/**
 * Sort table
 */
function sortTable(tableId, columnIndex) {
    const table = document.getElementById(tableId);
    const tbody = table.getElementsByTagName('tbody')[0];
    const rows = Array.from(tbody.getElementsByTagName('tr'));
    
    let isAscending = true;
    const header = table.getElementsByTagName('th')[columnIndex];
    
    if (header.classList.contains('sort-asc')) {
        isAscending = false;
        header.classList.remove('sort-asc');
        header.classList.add('sort-desc');
    } else {
        header.classList.remove('sort-desc');
        header.classList.add('sort-asc');
    }
    
    rows.sort((a, b) => {
        const aValue = a.cells[columnIndex].textContent.trim();
        const bValue = b.cells[columnIndex].textContent.trim();
        
        if (isAscending) {
            return aValue.localeCompare(bValue);
        } else {
            return bValue.localeCompare(aValue);
        }
    });
    
    rows.forEach(row => tbody.appendChild(row));
}

// ================================================
// PASSWORD FUNCTIONS
// ================================================

/**
 * Toggle password visibility
 */
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const btn = input.nextElementSibling;
    
    if (input.type === 'password') {
        input.type = 'text';
        if (btn) btn.textContent = '🙈';
    } else {
        input.type = 'password';
        if (btn) btn.textContent = '👁️';
    }
}

/**
 * Check password strength
 */
function checkPasswordStrength(password) {
    let strength = 0;
    
    if (password.length >= 8) strength++;
    if (password.length >= 12) strength++;
    if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
    if (/\d/.test(password)) strength++;
    if (/[^a-zA-Z0-9]/.test(password)) strength++;
    
    const levels = ['Very Weak', 'Weak', 'Fair', 'Good', 'Strong'];
    const colors = ['#e74c3c', '#f39c12', '#f1c40f', '#3498db', '#27ae60'];
    
    return {
        score: strength,
        level: levels[strength - 1] || 'Very Weak',
        color: colors[strength - 1] || '#e74c3c'
    };
}

// ================================================
// LOCAL STORAGE HELPERS
// ================================================

/**
 * Save to local storage
 */
function saveToStorage(key, value) {
    try {
        localStorage.setItem(key, JSON.stringify(value));
        return true;
    } catch (e) {
        console.error('Error saving to storage:', e);
        return false;
    }
}

/**
 * Get from local storage
 */
function getFromStorage(key, defaultValue = null) {
    try {
        const item = localStorage.getItem(key);
        return item ? JSON.parse(item) : defaultValue;
    } catch (e) {
        console.error('Error getting from storage:', e);
        return defaultValue;
    }
}

/**
 * Remove from local storage
 */
function removeFromStorage(key) {
    try {
        localStorage.removeItem(key);
        return true;
    } catch (e) {
        console.error('Error removing from storage:', e);
        return false;
    }
}

// ================================================
// AUTO INITIALIZE
// ================================================

document.addEventListener('DOMContentLoaded', function() {
    // Auto-close alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.animation = 'fadeOut 0.3s ease-out';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    });
    
    // Setup real-time validation for all forms
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        setupRealtimeValidation(form);
    });
    
    // Auto-focus first input in modals when opened
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this && this.classList.contains('active')) {
                const firstInput = this.querySelector('input:not([type="hidden"])');
                if (firstInput) firstInput.focus();
            }
        });
    });
});

// ================================================
// EXPORT FOR GLOBAL USE
// ================================================

window.AppHelpers = {
    formatFileSize,
    escapeHtml,
    showLoading,
    hideLoading,
    showToast,
    openModal,
    closeModal,
    handleFileSelect,
    setupDragDrop,
    validateForm,
    confirmAction,
    confirmDelete,
    filterTable,
    sortTable,
    togglePassword,
    checkPasswordStrength,
    saveToStorage,
    getFromStorage,
    removeFromStorage
};