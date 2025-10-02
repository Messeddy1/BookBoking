<?php if ($role === 'superadmin' || $role === 'gestionnaire') : ?>
    <nav class="navbar navbar-expand-md navbar-light bg-light p-3 rounded shadow-sm">
        <a class="navbar-brand fw-bold" href="/dashboard">لوحة التحكم</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminMenu" aria-controls="adminMenu" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="adminMenu">
            <ul class="navbar-nav ms-auto d-flex flex-wrap gap-2">

                <?php if ($role === 'superadmin') : ?>
                    <li class="nav-item">
                        <a href="/categories" class="btn btn-outline-secondary d-flex align-items-center">
                            <i class="fas fa-layer-group me-2"></i> إدارة الأصناف
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="/users" class="btn btn-outline-secondary d-flex align-items-center">
                            <i class="fas fa-users me-2"></i> إدارة المستخدمين
                        </a>
                    </li>
                <?php endif; ?>

                <li class="nav-item">
                    <a href="/loans" class="btn btn-outline-secondary d-flex align-items-center">
                        <i class="fas fa-book-reader me-2"></i> الكتب المعارة
                    </a>
                </li>

            </ul>
        </div>
    </nav>
<?php endif; ?>