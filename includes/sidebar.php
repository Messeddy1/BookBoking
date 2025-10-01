<?php
// includes/sidebar.php
?>
<div class="container-fluid">
    <div class="row">
        <div class="col-md-2 sidebar p-3">
            <div class="nav flex-column nav-pills">
                <a class="nav-link active" id="link-overview" onclick="showDashboardSection('overview')">
                    <i class="fas fa-chart-line me-2"></i> نظرة عامة
                </a>
                <a class="nav-link" id="link-books" onclick="showDashboardSection('books')">
                    <i class="fas fa-book me-2"></i> إدارة الكتب
                </a>
                <a class="nav-link" id="link-rents" onclick="showDashboardSection('rents')">
                    <i class="fas fa-exchange-alt me-2"></i> إدارة الإعارات
                </a>
                <a class="nav-link" id="link-categories" onclick="showDashboardSection('categories')">
                    <i class="fas fa-layer-group me-2"></i> الأصناف والأنواع
                </a>
                <a class="nav-link" id="link-users" onclick="showDashboardSection('users')" style="display:none;">
                    <i class="fas fa-users me-2"></i> إدارة المستخدمين
                </a>
            </div>
        </div>
        <div class="col-md-10 mt-4" id="mainContent">
            <!-- المحتوى الديناميكي سيُدرج هنا -->