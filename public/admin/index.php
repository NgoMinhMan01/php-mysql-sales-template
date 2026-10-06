# 1. Tạo thư mục public\admin\shippers
New-Item -Path public\admin\shippers -ItemType Directory -Force

# 2. Tạo file index.php với mã nguồn mẫu
@'
<?php
$pageTitle = 'Quản lý Người giao hàng';

require_once '/var/www/src/includes/admin/header.php';
require_once '/var/www/src/includes/admin/navbar.php';
?>

<main class="container py-5">
    <h1>Quản lý Người giao hàng (Shippers)</h1>
    <p class="text-muted">Chức năng đang được cập nhật.</p>
</main>

<?php
require_once '/var/www/src/includes/admin/footer.php';
?>
'@ | Set-Content -Path public\admin\shippers\index.php -Encoding utf8