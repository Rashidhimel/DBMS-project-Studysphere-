document.addEventListener('DOMContentLoaded', function() {
    
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const requiredFields = form.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.style.borderColor = '#e74c3c';
                } else {
                    field.style.borderColor = '#ddd';
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                alert('Please fill in all required fields.');
            }
        });
    });
    
    const passwordConfirm = document.getElementById('confirm_password');
    if (passwordConfirm) {
        const password = document.getElementById('password');
        passwordConfirm.addEventListener('input', function() {
            if (password.value !== passwordConfirm.value) {
                passwordConfirm.style.borderColor = '#e74c3c';
            } else {
                passwordConfirm.style.borderColor = '#27ae60';
            }
        });
    }
    
    const fileInput = document.getElementById('file');
    if (fileInput) {
        fileInput.addEventListener('change', function() {
            const fileName = this.files[0] ? this.files[0].name : 'No file chosen';
            const label = this.nextElementSibling || this.parentNode.querySelector('small');
            if (label) {
                label.textContent = fileName;
            }
            
            const file = this.files[0];
            if (file && file.size > 5 * 1024 * 1024) {
                alert('File is too large. Maximum size is 5MB.');
                this.value = '';
            }
        });
    }
    
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            setTimeout(() => {
                alert.style.display = 'none';
            }, 300);
        }, 5000);
    });
    
    const deleteButtons = document.querySelectorAll('[data-confirm]');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (!confirm(this.getAttribute('data-confirm'))) {
                e.preventDefault();
            }
        });
    });
    
    const addMoreButtons = document.querySelectorAll('.add-more');
    addMoreButtons.forEach(button => {
        button.addEventListener('click', function() {
            const template = this.previousElementSibling;
            const clone = template.cloneNode(true);
            
            clone.querySelectorAll('input, select, textarea').forEach(input => {
                if (input.type !== 'hidden') {
                    input.value = '';
                }
            });
            
            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'btn btn-small btn-danger';
            removeBtn.innerHTML = '<i class="fas fa-times"></i>';
            removeBtn.addEventListener('click', function() {
                this.parentElement.remove();
            });
            
            clone.appendChild(removeBtn);
            this.parentElement.insertBefore(clone, this);
        });
    });
    
    const dateInputs = document.querySelectorAll('input[type="date"]');
    dateInputs.forEach(input => {
        const today = new Date().toISOString().split('T')[0];
        input.max = '3100-01-01';
    });
    
    const tableRows = document.querySelectorAll('tbody tr[data-href]');
    tableRows.forEach(row => {
        row.addEventListener('click', function() {
            window.location.href = this.getAttribute('data-href');
        });
        row.style.cursor = 'pointer';
    });
});
function toggleMobileMenu() {
    const navMenu = document.getElementById('navMenu');
    navMenu.classList.toggle('show');
}

document.addEventListener('click', function(event) {
    const navMenu = document.getElementById('navMenu');
    const mobileToggle = document.querySelector('.mobile-menu-toggle');
    
    if (!navMenu.contains(event.target) && !mobileToggle.contains(event.target)) {
        navMenu.classList.remove('show');
    }
});

document.addEventListener('click', function(event) {
    if (!event.target.closest('.nav-dropdown')) {
        const dropdowns = document.querySelectorAll('.dropdown-menu');
        dropdowns.forEach(dropdown => {
            dropdown.style.display = 'none';
        });
    }
});

document.querySelectorAll('.dropdown-toggle').forEach(toggle => {
    toggle.addEventListener('click', function(e) {
        e.preventDefault();
        const dropdown = this.nextElementSibling;
        dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
    });
});

document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            setTimeout(() => {
                alert.style.display = 'none';
            }, 300);
        }, 5000);
    });
});