<?php
$pageTitle = 'Trang quản trị';

require_once '/var/www/src/config/database.php';
require_once '/var/www/src/includes/admin/header.php';
require_once '/var/www/src/includes/admin/navbar.php';
?>

<div class="container mt-4">
    <div class="p-5 mb-4 bg-light rounded-3">
        <div class="container-fluid py-3">
            <h1 class="display-5 fw-bold">Hệ thống Quản trị (Admin)</h1>
            <p class="col-md-8 fs-4">Chào mừng bạn đến với trang quản lý hệ thống bán hàng.</p>
            <hr class="my-4">
            <div class="d-flex gap-2">
                <a href="/admin/products/" class="btn btn-primary btn-lg">Quản lý Sản phẩm</a>
                <a href="/admin/categories/" class="btn btn-secondary btn-lg">Quản lý Danh mục</a>
            </div>
        </div>
    </div>
</div>

<?php
require_once '/var/www/src/includes/admin/footer.php';
$conn->close();
