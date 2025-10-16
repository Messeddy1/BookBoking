// Mobile Navigation Toggle
document.addEventListener('DOMContentLoaded', function() {
    const hamburger = document.querySelector('.hamburger');
    const navMenu = document.querySelector('.nav-menu');

    if (hamburger && navMenu) {
        hamburger.addEventListener('click', function() {
            hamburger.classList.toggle('active');
            navMenu.classList.toggle('active');
        });

        // Close menu when clicking on a nav link
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', function() {
                hamburger.classList.remove('active');
                navMenu.classList.remove('active');
            });
        });
    }

    // Smooth scrolling for navigation links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                const offsetTop = target.offsetTop - 70; // Account for fixed navbar
                window.scrollTo({
                    top: offsetTop,
                    behavior: 'smooth'
                });
            }
        });
    });

    // Initialize modal functionality
    initializeModal();
    
    // Initialize forms
    initializeForms();

    // Add scroll animations
    addScrollAnimations();
});

// Modal functionality
function initializeModal() {
    const modal = document.getElementById('membershipModal');
    const closeBtn = document.querySelector('.close');

    if (closeBtn) {
        closeBtn.addEventListener('click', closeModal);
    }

    if (modal) {
        window.addEventListener('click', function(event) {
            if (event.target === modal) {
                closeModal();
            }
        });
    }
}

// Select membership plan
function selectPlan(planType) {
    const modal = document.getElementById('membershipModal');
    const selectElement = document.getElementById('selectedPlan');
    
    if (selectElement) {
        selectElement.value = planType;
    }
    
    if (modal) {
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden';
    }
}

// Close modal
function closeModal() {
    const modal = document.getElementById('membershipModal');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
}

// Initialize forms
function initializeForms() {
    // Contact form
    const contactForm = document.getElementById('contactForm');
    if (contactForm) {
        contactForm.addEventListener('submit', handleContactForm);
    }

    // Membership form
    const membershipForm = document.getElementById('membershipForm');
    if (membershipForm) {
        membershipForm.addEventListener('submit', handleMembershipForm);
    }

    // Add input validation
    addInputValidation();
}

// Handle contact form submission
function handleContactForm(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const data = {
        name: formData.get('name'),
        email: formData.get('email'),
        phone: formData.get('phone'),
        subject: formData.get('subject'),
        message: formData.get('message')
    };

    // Validate required fields
    if (!data.name || !data.email || !data.subject || !data.message) {
        showNotification('يرجى ملء جميع الحقول المطلوبة', 'error');
        return;
    }

    // Email validation
    if (!isValidEmail(data.email)) {
        showNotification('يرجى إدخال عنوان بريد إلكتروني صحيح', 'error');
        return;
    }

    // Simulate form submission
    showLoadingSpinner(e.target);
    
    setTimeout(() => {
        hideLoadingSpinner(e.target);
        showNotification('تم إرسال رسالتك بنجاح! سنتواصل معك قريباً.', 'success');
        e.target.reset();
    }, 2000);
}

// Handle membership form submission
function handleMembershipForm(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const data = {
        fullName: formData.get('fullName'),
        email: formData.get('memberEmail'),
        phone: formData.get('memberPhone'),
        birthDate: formData.get('birthDate'),
        address: formData.get('address'),
        interests: formData.getAll('interests'),
        selectedPlan: formData.get('selectedPlan'),
        terms: formData.get('terms')
    };

    // Validate required fields
    if (!data.fullName || !data.email || !data.phone || !data.selectedPlan || !data.terms) {
        showNotification('يرجى ملء جميع الحقول المطلوبة والموافقة على الشروط', 'error');
        return;
    }

    // Email validation
    if (!isValidEmail(data.email)) {
        showNotification('يرجى إدخال عنوان بريد إلكتروني صحيح', 'error');
        return;
    }

    // Phone validation
    if (!isValidPhone(data.phone)) {
        showNotification('يرجى إدخال رقم هاتف صحيح', 'error');
        return;
    }

    // Simulate form submission
    showLoadingSpinner(e.target);
    
    setTimeout(() => {
        hideLoadingSpinner(e.target);
        showNotification('تم تسجيل اشتراكك بنجاح! مرحباً بك في نادي القراءة.', 'success');
        closeModal();
        e.target.reset();
    }, 3000);
}

// Input validation
function addInputValidation() {
    // Real-time email validation
    const emailInputs = document.querySelectorAll('input[type="email"]');
    emailInputs.forEach(input => {
        input.addEventListener('blur', function() {
            if (this.value && !isValidEmail(this.value)) {
                this.style.borderColor = '#e74c3c';
                showFieldError(this, 'يرجى إدخال عنوان بريد إلكتروني صحيح');
            } else {
                this.style.borderColor = '#27ae60';
                hideFieldError(this);
            }
        });
    });

    // Real-time phone validation
    const phoneInputs = document.querySelectorAll('input[type="tel"]');
    phoneInputs.forEach(input => {
        input.addEventListener('input', function() {
            // Allow only numbers and common phone characters
            this.value = this.value.replace(/[^\d+\-\s()]/g, '');
        });

        input.addEventListener('blur', function() {
            if (this.value && !isValidPhone(this.value)) {
                this.style.borderColor = '#e74c3c';
                showFieldError(this, 'يرجى إدخال رقم هاتف صحيح');
            } else if (this.value) {
                this.style.borderColor = '#27ae60';
                hideFieldError(this);
            }
        });
    });

    // Required field validation
    const requiredInputs = document.querySelectorAll('input[required], select[required], textarea[required]');
    requiredInputs.forEach(input => {
        input.addEventListener('blur', function() {
            if (!this.value.trim()) {
                this.style.borderColor = '#e74c3c';
                showFieldError(this, 'هذا الحقل مطلوب');
            } else {
                this.style.borderColor = '#27ae60';
                hideFieldError(this);
            }
        });
    });
}

// Utility functions
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

function isValidPhone(phone) {
    const phoneRegex = /^[\+]?[0-9\s\-\(\)]{8,}$/;
    return phoneRegex.test(phone.trim());
}

function showFieldError(field, message) {
    hideFieldError(field); // Remove existing error
    
    const errorDiv = document.createElement('div');
    errorDiv.className = 'field-error';
    errorDiv.textContent = message;
    errorDiv.style.color = '#e74c3c';
    errorDiv.style.fontSize = '14px';
    errorDiv.style.marginTop = '5px';
    
    field.parentNode.appendChild(errorDiv);
}

function hideFieldError(field) {
    const existingError = field.parentNode.querySelector('.field-error');
    if (existingError) {
        existingError.remove();
    }
}

function showLoadingSpinner(form) {
    const submitBtn = form.querySelector('button[type="submit"]');
    if (submitBtn) {
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري الإرسال...';
        submitBtn.disabled = true;
    }
}

function hideLoadingSpinner(form) {
    const submitBtn = form.querySelector('button[type="submit"]');
    if (submitBtn) {
        if (form.id === 'contactForm') {
            submitBtn.innerHTML = 'إرسال الرسالة';
        } else if (form.id === 'membershipForm') {
            submitBtn.innerHTML = 'تأكيد الاشتراك';
        }
        submitBtn.disabled = false;
    }
}

function showNotification(message, type = 'info') {
    // Remove existing notification
    const existingNotification = document.querySelector('.notification');
    if (existingNotification) {
        existingNotification.remove();
    }

    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'}"></i>
            <span>${message}</span>
            <button class="notification-close" onclick="this.parentElement.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;

    // Add notification styles
    notification.style.cssText = `
        position: fixed;
        top: 100px;
        right: 20px;
        z-index: 3000;
        background: ${type === 'success' ? '#27ae60' : type === 'error' ? '#e74c3c' : '#3498db'};
        color: white;
        padding: 15px 20px;
        border-radius: 8px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        max-width: 400px;
        animation: slideInRight 0.3s ease-out;
        direction: rtl;
    `;

    document.body.appendChild(notification);

    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.style.animation = 'slideOutRight 0.3s ease-out';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 300);
        }
    }, 5000);
}

// Add scroll animations
function addScrollAnimations() {
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('fade-in-up');
            }
        });
    }, observerOptions);

    // Observe elements for animation
    const animateElements = document.querySelectorAll('.service-card, .category-card, .plan-card, .contact-item');
    animateElements.forEach(el => observer.observe(el));
}

// Navbar scroll effect
window.addEventListener('scroll', function() {
    const navbar = document.querySelector('.navbar');
    if (navbar) {
        if (window.scrollY > 100) {
            navbar.style.background = 'linear-gradient(135deg, rgba(44, 62, 80, 0.95), rgba(52, 73, 94, 0.95))';
            navbar.style.backdropFilter = 'blur(10px)';
        } else {
            navbar.style.background = 'linear-gradient(135deg, #2c3e50, #34495e)';
            navbar.style.backdropFilter = 'none';
        }
    }
});

// Add CSS animations for notifications
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOutRight {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }

    .notification-content {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .notification-close {
        background: none;
        border: none;
        color: white;
        cursor: pointer;
        margin-right: auto;
        padding: 5px;
        border-radius: 3px;
        transition: background-color 0.3s ease;
    }

    .notification-close:hover {
        background-color: rgba(255, 255, 255, 0.2);
    }
`;
document.head.appendChild(style);

// Statistics counter animation (if you want to add stats later)
function animateCounters() {
    const counters = document.querySelectorAll('.counter');
    
    counters.forEach(counter => {
        const target = parseInt(counter.getAttribute('data-target'));
        const speed = 2000; // Animation duration in ms
        const increment = target / (speed / 16); // 60fps
        let current = 0;
        
        const updateCounter = () => {
            if (current < target) {
                current += increment;
                counter.textContent = Math.ceil(current);
                requestAnimationFrame(updateCounter);
            } else {
                counter.textContent = target;
            }
        };
        
        updateCounter();
    });
}

// Search functionality (can be extended later)
function initializeSearch() {
    const searchInput = document.getElementById('searchInput');
    const searchResults = document.getElementById('searchResults');
    
    if (searchInput && searchResults) {
        let searchTimeout;
        
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value.trim();
            
            if (query.length < 2) {
                searchResults.style.display = 'none';
                return;
            }
            
            searchTimeout = setTimeout(() => {
                performSearch(query);
            }, 300);
        });
    }
}

function performSearch(query) {
    // This can be extended to search through books, services, etc.
    console.log('البحث عن:', query);
    // Implement actual search logic here
}

// Book recommendation system (placeholder for future enhancement)
function getBookRecommendations(interests) {
    const recommendations = {
        'literature': ['نجيب محفوظ - الثلاثية', 'طه حسين - الأيام', 'إحسان عبد القدوس - لا أنام'],
        'theater': ['توفيق الحكيم - أهل الكهف', 'سعد الله ونوس - الملك هو الملك', 'ألفريد فرج - سليمان الحلبي'],
        'novels': ['إبراهيم نصر الله - وقت بين الولع والجنون', 'حيدر حيدر - وليمة لأعشاب البحر', 'عبد الرحمن منيف - مدن الملح'],
        'history': ['ابن خلدون - المقدمة', 'الطبري - تاريخ الرسل والملوك', 'ابن الأثير - الكامل في التاريخ'],
        'science': ['أحمد زويل - رحلة عبر الزمن', 'مصطفى محمود - العلم والإيمان', 'فاروق الباز - الأرض من الفضاء'],
        'philosophy': ['زكي نجيب محمود - تجديد الفكر العربي', 'عبد الرحمن بدوي - فلسفة العصور الوسطى', 'محمد عابد الجابري - نقد العقل العربي']
    };
    
    return interests.flatMap(interest => recommendations[interest] || []);
}

// Language toggle (if needed for bilingual support)
function toggleLanguage() {
    const currentLang = document.documentElement.lang;
    if (currentLang === 'ar') {
        document.documentElement.lang = 'en';
        document.documentElement.dir = 'ltr';
        // Update content to English
    } else {
        document.documentElement.lang = 'ar';
        document.documentElement.dir = 'rtl';
        // Update content to Arabic
    }
}

// Print functionality for membership cards
function printMembershipCard(memberData) {
    const printWindow = window.open('', '_blank');
    const printContent = `
        <!DOCTYPE html>
        <html lang="ar" dir="rtl">
        <head>
            <meta charset="UTF-8">
            <title>بطاقة عضوية نادي القراءة</title>
            <style>
                body { font-family: 'Cairo', sans-serif; direction: rtl; text-align: right; }
                .card { width: 350px; height: 220px; border: 2px solid #3498db; border-radius: 15px; padding: 20px; margin: 20px; }
                .header { text-align: center; color: #3498db; margin-bottom: 20px; }
                .info { margin: 5px 0; }
            </style>
        </head>
        <body>
            <div class="card">
                <div class="header">
                    <h2>نادي القراءة</h2>
                    <p>بطاقة العضوية</p>
                </div>
                <div class="info"><strong>الاسم:</strong> ${memberData.fullName}</div>
                <div class="info"><strong>رقم العضوية:</strong> ${Math.random().toString(36).substr(2, 9).toUpperCase()}</div>
                <div class="info"><strong>الخطة:</strong> ${memberData.selectedPlan}</div>
                <div class="info"><strong>تاريخ الانضمام:</strong> ${new Date().toLocaleDateString('ar-EG')}</div>
            </div>
            <script>window.print();</script>
        </body>
        </html>
    `;
    
    printWindow.document.write(printContent);
    printWindow.document.close();
}