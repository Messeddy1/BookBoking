<?php
// includes/footer.php
?>
</div> <!-- end mainContent -->
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    // showDashboardSection and basic routing between sections
    function hideAllSections() {
        document.querySelectorAll('.dashboard-section').forEach(s => s.style.display = 'none');
        document.querySelectorAll('.nav-link').forEach(n => n.classList.remove('active'));
    }

    function showDashboardSection(name) {
        hideAllSections();
        document.getElementById(name + 'Section').style.display = 'block';
        document.getElementById('link-' + name).classList.add('active');

        // load data for the shown section
        if (name === 'overview') loadOverview();
        if (name === 'books') loadBooks();
        if (name === 'rents') loadRents();
        if (name === 'categories') {
            loadCategories();
            loadTypesCategoryOptions();
        }
        if (name === 'users') loadUsers && loadUsers();
    }

    // load initial view
    document.addEventListener('DOMContentLoaded', function() {
        showDashboardSection('overview');
    });
</script>

<!-- لاحقًا، كل صفحة يمكن إضافة سكريبتاتها الخاصة بعد هذا الملف -->
</body>

</html>