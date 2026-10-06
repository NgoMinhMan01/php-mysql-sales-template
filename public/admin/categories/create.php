<?php

$pageTitle = 'Thêm danh mục';
require_once '/var/www/src/config/database.php';

$error = '';

// 6. Xử lý dữ liệu POST & 7. INSERT bằng Prepared Statement
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $categoryName = trim($_POST['category_name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($categoryName === '') {

        $error = 'Tên danh mục không được để trống.';

    } else {

        $sql = "
            INSERT INTO categories
                (CategoryName, Description)
            VALUES
                (?, ?)
        ";

        $stmt = $conn->prepare($sql);

        $stmt->bind_param(
            'ss',
            $categoryName,
            $description
        );

        if ($stmt->execute()) {

            header('Location: /admin/categories/');
            exit;

        } else {

            $error = 'Không thể thêm danh mục.';
        }

        $stmt->close();

    }
}

require_once '/var/www/src/includes/admin/header.php';
require_once '/var/www/src/includes/admin/navbar.php';

?>

<div class="container mt-4">

    <h2 class="mb-4">Thêm danh mục</h2>

    <!-- 8.1. Hiển thị thông báo lỗi -->
    <?php if ($error !== ''): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="post">

        <div class="mb-3">
            <label for="categoryName" class="form-label">
                Tên danh mục
            </label>

            <!-- 8.2. Giữ lại tên danh mục đã nhập -->
            <input
                type="text"
                class="form-control"
                id="categoryName"
                name="category_name"
                required
                value="<?= htmlspecialchars($_POST['category_name'] ?? '') ?>"
            >
        </div>

        <div class="mb-3">
            <label for="description" class="form-label">
                Mô tả
            </label>

            <!-- 8.3. Giữ lại mô tả đã nhập -->
            <textarea
                class="form-control"
                id="description"
                name="description"
                rows="3"
            ><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
        </div>

        <button type="submit" class="btn btn-primary">
            Lưu
        </button>

        <a href="/admin/categories/" class="btn btn-secondary">
            Hủy
        </a>

    </form>

</div>

<?php

require_once '/var/www/src/includes/admin/footer.php';
