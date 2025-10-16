<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نادي القراءة - فضاء ومكتبة لكراء الكتب</title>

    <!-- Google Fonts for Arabic -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&family=Amiri:wght@400;700&display=swap" rel="stylesheet">

    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="../landingPage/css/style.css">
</head>

<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-logo">
                <i class="fas fa-book-open"></i>
                <span>نادي القراءة</span>
            </div>
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="#home" class="nav-link">الرئيسية</a>
                </li>
                <li class="nav-item">
                    <a href="#services" class="nav-link">خدماتنا</a>
                </li>
                <li class="nav-item">
                    <a href="#books" class="nav-link">مكتبة الكتب</a>
                </li>
                <li class="nav-item">
                    <a href="#membership" class="nav-link">أحدث الكتب</a>
                </li>
                <li class="nav-item">
                    <a href="#contact" class="nav-link">اتصل بنا</a>
                </li>
            </ul>
            <div class="hamburger">
                <span class="bar"></span>
                <span class="bar"></span>
                <span class="bar"></span>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section id="home" class="hero">
        <div class="hero-content">
            <h1 class="hero-title">مرحباً بكم في نادي القراءة</h1>
            <p class="hero-subtitle">فضاء ومكتبة لكراء الكتب في عدة مجالات</p>
            <div class="hero-description">
                <p>نوفر لكم مجموعة واسعة من الكتب في مجالات الأدب والمسرح والرواية وغيرها من المجالات الثقافية والعلمية</p>
            </div>
            <div class="hero-buttons">
                <a href="/login" class="btn btn-primary">انضم إلينا الآن</a>
                <a href="/home" class="btn btn-secondary">استعرض الكتب</a>
            </div>
        </div>
        <div class="hero-image">
            <i class="fas fa-book-reader"></i>
        </div>
    </section>

    <!-- Services Section -->
    <section id="services" class="services">
        <div class="container">
            <h2 class="section-title">خدماتنا</h2>
            <div class="services-grid">
                <div class="service-card">
                    <i class="fas fa-book"></i>
                    <h3>تأجير الكتب</h3>
                    <p>خدمة تأجير شاملة للكتب في جميع المجالات بأسعار مناسبة ومدة مرنة</p>
                </div>
                <div class="service-card">
                    <i class="fas fa-users"></i>
                    <h3>نادي القراءة</h3>
                    <p>انضم إلى مجتمع القراء وشارك في النقاشات والفعاليات الثقافية</p>
                </div>
                <div class="service-card">
                    <i class="fas fa-calendar-alt"></i>
                    <h3>فعاليات ثقافية</h3>
                    <p>نظم ندوات ومحاضرات وورش عمل في مجال الأدب والثقافة</p>
                </div>
                <div class="service-card">
                    <i class="fas fa-search"></i>
                    <h3>استشارات أدبية</h3>
                    <p>نساعدك في اختيار الكتب المناسبة حسب اهتماماتك ومستواك</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Books Categories Section -->
    <section id="books" class="books">
        <div class="container">
            <h2 class="section-title">مجالات الكتب المتوفرة</h2>
            <div class="books-categories">
                <div class="category-card">
                    <i class="fas fa-feather-alt"></i>
                    <h3>الأدب العربي</h3>
                    <p>مجموعة واسعة من أعمال الأدباء العرب الكلاسيكيين والمعاصرين</p>
                </div>
                <div class="category-card">
                    <i class="fas fa-theater-masks"></i>
                    <h3>المسرح</h3>
                    <p>نصوص مسرحية عربية وعالمية من أشهر الكتاب والمؤلفين</p>
                </div>
                <div class="category-card">
                    <i class="fas fa-book-open"></i>
                    <h3>الرواية</h3>
                    <p>روايات عربية وأجنبية مترجمة من مختلف العصور والثقافات</p>
                </div>
                <div class="category-card">
                    <i class="fas fa-history"></i>
                    <h3>التاريخ</h3>
                    <p>كتب تاريخية تغطي مختلف الحقب والحضارات</p>
                </div>
                <div class="category-card">
                    <i class="fas fa-microscope"></i>
                    <h3>العلوم</h3>
                    <p>كتب علمية متنوعة في مختلف فروع المعرفة</p>
                </div>
                <div class="category-card">
                    <i class="fas fa-lightbulb"></i>
                    <h3>الفلسفة</h3>
                    <p>أعمال الفلاسفة والمفكرين من مختلف المدارس الفكرية</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Membership Section -->

    <?php
    require_once('./db_config.php');
    try {
        $stmt = $db->prepare("
    SELECT 
        b.id, 
        b.title, 
        b.author, 
        b.cover_image, 
        b.custom_id,
        b.excerpts,
        c.name AS category_name,
        t.name AS type_name,
        b.created_at
    FROM books b
    LEFT JOIN categories c ON b.category_id = c.id
    LEFT JOIN types t ON b.type_id = t.id
    ORDER BY b.created_at DESC
    LIMIT 3
");
        $stmt->execute();
        $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        echo "خطأ: " . $e->getMessage();
    }
    function cover_url($book)
    {
        if (!empty($book['cover_image'])) {
            return htmlspecialchars($book['cover_image']);
        }
        return '/assets/placeHolder.webp';
    }
    ?>

    <section id="membership" class="membership">
        <div class="container">
            <h2 class="section-title">أحدث الكتب</h2>
            <div class="membership-plans">
                <?php if (!empty($books)): ?>
                    <?php foreach ($books as $book):
                        $cover = cover_url($book); ?>
                        <div class="plan-card">
                            <div class="cover" style="background-image: url('<?= htmlspecialchars($cover) ?>');"></div>

                            <div class="plan-header">
                                <h3><?= htmlspecialchars($book['title']); ?></h3>
                                <p class="author"><?= htmlspecialchars($book['author']); ?></p>
                                <p class="category">التصنيف: <?= htmlspecialchars($book['category_name']); ?></p>
                                <p class="type">النوع: <?= htmlspecialchars($book['type_name']); ?></p>
                                <p class="excerpt"><?= htmlspecialchars($book['excerpts']); ?></p>
                                <small>ID مخصص: <?= htmlspecialchars($book['custom_id']); ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color:white; text-align:center;">لا توجد كتب متاحة حالياً</p>
                <?php endif; ?>
            </div>
        </div>
        <div>
            <a href="/home" class="view-all">عرض الكل</a>
        </div>
    </section>


    <!-- Contact Section -->
    <section id="contact" class="contact">
        <div class="container">
            <h2 class="section-title">اتصل بنا</h2>
            <div class="contact-content">
                <div class="contact-info">
                    <div class="contact-item">
                        <i class="fas fa-map-marker-alt"></i>
                        <div>
                            <h4>العنوان</h4>
                            <p>شارع المعرفة، حي الثقافة<br>المدينة، المغرب</p>
                        </div>
                    </div>
                    <div class="contact-item">
                        <i class="fas fa-phone"></i>
                        <div>
                            <h4>الهاتف</h4>
                            <p>+212 123 456 789</p>
                        </div>
                    </div>
                    <div class="contact-item">
                        <i class="fas fa-envelope"></i>
                        <div>
                            <h4>البريد الإلكتروني</h4>
                            <p>info@readingclub.ma</p>
                        </div>
                    </div>
                    <div class="contact-item">
                        <i class="fas fa-clock"></i>
                        <div>
                            <h4>ساعات العمل</h4>
                            <p>الاثنين - الجمعة: 9:00 - 20:00<br>السبت: 10:00 - 18:00</p>
                        </div>
                    </div>
                </div>

                <form class="contact-form" id="contactForm">
                    <div class="form-group">
                        <input type="text" id="name" name="name" placeholder="الاسم الكامل" required>
                    </div>
                    <div class="form-group">
                        <input type="email" id="email" name="email" placeholder="البريد الإلكتروني" required>
                    </div>
                    <div class="form-group">
                        <input type="tel" id="phone" name="phone" placeholder="رقم الهاتف">
                    </div>
                    <div class="form-group">
                        <select id="subject" name="subject" required>
                            <option value="">اختر موضوع الرسالة</option>
                            <option value="membership">الاستفسار عن العضوية</option>
                            <option value="books">البحث عن كتاب معين</option>
                            <option value="events">الاستفسار عن الفعاليات</option>
                            <option value="other">أخرى</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <textarea id="message" name="message" placeholder="نص الرسالة" rows="5" required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">إرسال الرسالة</button>
                </form>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <div class="footer-logo">
                        <i class="fas fa-book-open"></i>
                        <span>نادي القراءة</span>
                    </div>
                    <p>فضاء ثقافي متميز يهدف إلى نشر ثقافة القراءة وتوفير مكتبة شاملة لجميع أفراد المجتمع</p>
                    <div class="social-links">
                        <a href="#"><i class="fab fa-facebook"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-youtube"></i></a>
                    </div>
                </div>

                <div class="footer-section">
                    <h4>روابط سريعة</h4>
                    <ul>
                        <li><a href="#home">الرئيسية</a></li>
                        <li><a href="#services">خدماتنا</a></li>
                        <li><a href="#books">مكتبة الكتب</a></li>
                        <li><a href="#membership">الاشتراك</a></li>
                    </ul>
                </div>

                <div class="footer-section">
                    <h4>خدماتنا</h4>
                    <ul>
                        <li><a href="#">تأجير الكتب</a></li>
                        <li><a href="#">نادي القراءة</a></li>
                        <li><a href="#">الفعاليات الثقافية</a></li>
                        <li><a href="#">الاستشارات الأدبية</a></li>
                    </ul>
                </div>

                <div class="footer-section">
                    <h4>معلومات الاتصال</h4>
                    <div class="footer-contact">
                        <p><i class="fas fa-map-marker-alt"></i> شارع المعرفة، حي الثقافة</p>
                        <p><i class="fas fa-phone"></i> +212 123 456 789</p>
                        <p><i class="fas fa-envelope"></i> info@readingclub.ma</p>
                    </div>
                </div>
            </div>

            <div class="footer-bottom">
                <p>&copy; 2024 نادي القراءة. جميع الحقوق محفوظة.</p>
            </div>
        </div>
    </footer>

    <!-- Membership Modal -->
    <!-- <div id="membershipModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>نموذج الاشتراك</h3>
                <span class="close">&times;</span>
            </div>
            <form id="membershipForm" class="membership-form">
                <div class="form-group">
                    <label for="fullName">الاسم الكامل *</label>
                    <input type="text" id="fullName" name="fullName" required>
                </div>
                
                <div class="form-group">
                    <label for="memberEmail">البريد الإلكتروني *</label>
                    <input type="email" id="memberEmail" name="memberEmail" required>
                </div>
                
                <div class="form-group">
                    <label for="memberPhone">رقم الهاتف *</label>
                    <input type="tel" id="memberPhone" name="memberPhone" required>
                </div>
                
                <div class="form-group">
                    <label for="birthDate">تاريخ الميلاد</label>
                    <input type="date" id="birthDate" name="birthDate">
                </div>
                
                <div class="form-group">
                    <label for="address">العنوان</label>
                    <textarea id="address" name="address" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="interests">اهتماماتك القرائية</label>
                    <div class="checkbox-group">
                        <label><input type="checkbox" name="interests" value="literature"> الأدب العربي</label>
                        <label><input type="checkbox" name="interests" value="theater"> المسرح</label>
                        <label><input type="checkbox" name="interests" value="novels"> الرواية</label>
                        <label><input type="checkbox" name="interests" value="history"> التاريخ</label>
                        <label><input type="checkbox" name="interests" value="science"> العلوم</label>
                        <label><input type="checkbox" name="interests" value="philosophy"> الفلسفة</label>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="selectedPlan">الخطة المختارة</label>
                    <select id="selectedPlan" name="selectedPlan" required>
                        <option value="">اختر خطة الاشتراك</option>
                        <option value="basic">الخطة الأساسية - 50 درهم/شهر</option>
                        <option value="premium">الخطة المميزة - 80 درهم/شهر</option>
                        <option value="gold">الخطة الذهبية - 120 درهم/شهر</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" id="terms" name="terms" required>
                        أوافق على <a href="#" class="link">شروط وأحكام</a> نادي القراءة
                    </label>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">إلغاء</button>
                    <button type="submit" class="btn btn-primary">تأكيد الاشتراك</button>
                </div>
            </form>
        </div>
    </div> -->

    <!-- JavaScript -->
    <script src="../landingPage/js/main.js"></script>
</body>

</html>