<?php

$pageTitle = 'Thêm sản phẩm';

require_once '/var/www/src/config/database.php';

$error = '';

$sqlCategories = "
    SELECT CategoryID, CategoryName
    FROM categories
    ORDER BY CategoryName
";

$categories = $conn->query($sqlCategories);

$sqlSuppliers = "
    SELECT SupplierID, SupplierName
    FROM suppliers
    ORDER BY SupplierName
";

$suppliers = $conn->query($sqlSuppliers);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $productCode = trim($_POST['product_code'] ?? '');
    $productName = trim($_POST['product_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $unit = trim($_POST['unit'] ?? '');

    $price = (float) ($_POST['price'] ?? 0);
    $stockQuantity = (int) ($_POST['stock_quantity'] ?? 0);

    $categoryID = (int) ($_POST['category_id'] ?? 0);
    $supplierID = (int) ($_POST['supplier_id'] ?? 0);

    $isActive = isset($_POST['is_active']) ? 1 : 0;

    if ($productCode === '') {$error = 'Mã sản phẩm không được để trống.';

    } elseif ($productName === '') {$error = 'Tên sản phẩm không được để trống.';

    } elseif ($price < 0) {$error = 'Giá sản phẩm không hợp lệ.';

    } elseif ($stockQuantity < 0) {$error = 'Số lượng tồn kho không hợp lệ.';

    } elseif ($categoryID <= 0) {$error = 'Vui lòng chọn danh mục.';

    } elseif ($supplierID <= 0) {$error = 'Vui lòng chọn nhà cung cấp.';

    } else {

        $sql = "
            INSERT INTO products
            (
                ProductCode,
                ProductName,
                Description,
                Unit,
                Price,
                StockQuantity,
                IsActive,
                SupplierID,
                CategoryID
            )
            VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";

        $stmt = $conn->prepare($sql);

        $stmt->bind_param(
            'ssssdiiii',
            $productCode,
            $productName,$description,
            $unit,$price,
            $stockQuantity,$isActive,
            $supplierID,$categoryID
        );

        if ($stmt->execute()) {
            header('Location: /admin/products/');
            exit;
        }

        $error = 'Không thể thêm sản phẩm.';
        $stmt->close();
    }
}

require_once '/var/www/src/includes/admin/header.php';
require_once '/var/www/src/includes/admin/navbar.php';
?>

<main class="container py-4">
    <h2 class="mb-4">Thêm sản phẩm mới</h2>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="" class="card shadow-sm p-4">
        <div class="mb-3">
            <label for="product_code" class="form-label">Mã sản phẩm</label>
            <input type="text" class="form-control" id="product_code" name="product_code" required>
        </div>

        <div class="mb-3">
            <label for="product_name" class="form-label">Tên sản phẩm</label>
            <input type="text" class="form-control" id="product_name" name="product_name" required>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="category_id" class="form-label">Danh mục</label>
                <select class="form-select" id="category_id" name="category_id" required>
                    <option value="">-- Chọn danh mục --</option>
                    <?php while ($cat =$categories->fetch_assoc()): ?>
                        <option value="<?= $cat['CategoryID'] ?>"><?= htmlspecialchars($cat['CategoryName']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="col-md-6 mb-3">
                <label for="supplier_id" class="form-label">Nhà cung cấp</label>
                <select class="form-select" id="supplier_id" name="supplier_id" required>
                    <option value="">-- Chọn nhà cung cấp --</option>
                    <?php while ($sup =$suppliers->fetch_assoc()): ?>
                        <option value="<?= $sup['SupplierID'] ?>"><?= htmlspecialchars($sup['SupplierName']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="price" class="form-label">Giá (VNĐ)</label>
                <input type="number" step="0.01" class="form-control" id="price" name="price" required>
            </div>

            <div class="col-md-6 mb-3">
                <label for="stock_quantity" class="form-label">Số lượng tồn kho</label>
                <input type="number" class="form-control" id="stock_quantity" name="stock_quantity" value="0" required>
            </div>
        </div>

        <div class="mb-3">
            <label for="unit" class="form-label">Đơn vị tính</label>
            <input type="text" class="form-control" id="unit" name="unit" placeholder="Ví dụ: Cái, Hộp, Kg...">
        </div>

        <div class="mb-3">
            <label for="description" class="form-label">Mô tả sản phẩm</label>
            <textarea class="form-control" id="description" name="description" rows="4"></textarea>
        </div>

        <div class="mb-3 form-check">
            <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1" checked>
            <label class="form-check-label" for="is_active">Đang kinh doanh (IsActive)</label>
        </div>

        <div class="d-flex justify-content-between">
            <a href="/admin/products/" class="btn btn-secondary">Quay lại danh sách</a>
            <button type="submit" class="btn btn-primary">Lưu sản phẩm</button>
        </div>
    </form>
</