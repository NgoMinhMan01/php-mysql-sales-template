<?php

$pageTitle = 'Chỉnh sửa sản phẩm';

require_once '/var/www/src/config/database.php';

// 1. Lấy ProductID từ $_GET
$productID = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($productID <= 0) {
    header('Location: /products/');
    exit;
}

// 2. Lấy thông tin sản phẩm hiện tại
$sqlProduct = "SELECT * FROM products WHERE ProductID = ?";
$stmtProduct = $conn->prepare($sqlProduct);
$stmtProduct->bind_param('i', $productID);
$stmtProduct->execute();
$product = $stmtProduct->get_result()->fetch_assoc();

if (!$product) {
    header('Location: /products/');
    exit;
}

// 3. Lấy danh sách Categories và Suppliers cho Dropdown
$categories = $conn->query("SELECT CategoryID, CategoryName FROM categories ORDER BY CategoryName");
$suppliers = $conn->query("SELECT SupplierID, SupplierName FROM suppliers ORDER BY SupplierName");

$errors = [];

// 4. Xử lý khi Form gửi dữ liệu (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $productCode   = trim($_POST['product_code'] ?? '');
    $productName   = trim($_POST['product_name'] ?? '');
    $description   = trim($_POST['description'] ?? '');
    $unit          = trim($_POST['unit'] ?? '');
    $price         = (float) ($_POST['price'] ?? 0);
    $stockQuantity = (int) ($_POST['stock_quantity'] ?? 0);
    $isActive      = isset($_POST['is_active']) ? 1 : 0;
    $categoryID    = (int) ($_POST['category_id'] ?? 0);
    $supplierID    = (int) ($_POST['supplier_id'] ?? 0);

    // Kiểm tra dữ liệu đầu vào (Validation)
    if (empty($productCode)) {
        $errors[] = 'Vui lòng nhập mã sản phẩm.';
    }
    if (empty($productName)) {
        $errors[] = 'Vui lòng nhập tên sản phẩm.';
    }
    if ($categoryID <= 0) {
        $errors[] = 'Vui lòng chọn danh mục.';
    }
    if ($supplierID <= 0) {
        $errors[] = 'Vui lòng chọn nhà cung cấp.';
    }

    // Nếu không có lỗi, tiến hành UPDATE dữ liệu
    if (empty($errors)) {
        $sql = "
            UPDATE products
            SET
                ProductCode = ?,
                ProductName = ?,
                Description = ?,
                Unit = ?,
                Price = ?,
                StockQuantity = ?,
                IsActive = ?,
                SupplierID = ?,
                CategoryID = ?
            WHERE ProductID = ?
        ";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            'ssssdiiiii',
            $productCode,
            $productName,
            $description,
            $unit,
            $price,
            $stockQuantity,
            $isActive,
            $supplierID,
            $categoryID,
            $productID
        );

        if ($stmt->execute()) {
            header('Location: /products/');
            exit;
        } else {
            $errors[] = 'Lỗi hệ thống: ' . $stmt->error;
        }
    }
}

// Xác định ID đang chọn (Ưu tiên POST khi form lỗi, nếu không dùng dữ liệu CSDL)
$selectedCategoryID = $_POST['category_id'] ?? $product['CategoryID'];
$selectedSupplierID = $_POST['supplier_id'] ?? $product['SupplierID'];

require_once '/var/www/src/includes/header.php';
require_once '/var/www/src/includes/navbar.php';

?>

<div class="container mt-4">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Chỉnh sửa sản phẩm</h2>
        <a href="/products/" class="btn btn-secondary">Quay lại</a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" action="">

        <div class="row">

            <div class="col-md-6 mb-3">
                <label class="form-label">Mã sản phẩm (*)</label>
                <input
                    type="text"
                    name="product_code"
                    class="form-control"
                    value="<?= htmlspecialchars($_POST['product_code'] ?? $product['ProductCode']) ?>"
                    required
                >
            </div>

            <div class="col-md-6 mb-3">
                <label class="form-label">Tên sản phẩm (*)</label>
                <input
                    type="text"
                    name="product_name"
                    class="form-control"
                    value="<?= htmlspecialchars($_POST['product_name'] ?? $product['ProductName']) ?>"
                    required
                >
            </div>

            <div class="col-md-6 mb-3">
                <label class="form-label">Danh mục (*)</label>
                <select name="category_id" class="form-select" required>
                    <option value="">-- Chọn danh mục --</option>
                    <?php while ($cat = $categories->fetch_assoc()): ?>
                        <option
                            value="<?= $cat['CategoryID'] ?>"
                            <?= (int)$selectedCategoryID === (int)$cat['CategoryID'] ? 'selected' : '' ?>
                        >
                            <?= htmlspecialchars($cat['CategoryName']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="col-md-6 mb-3">
                <label class="form-label">Nhà cung cấp (*)</label>
                <select name="supplier_id" class="form-select" required>
                    <option value="">-- Chọn nhà cung cấp --</option>
                    <?php while ($sup = $suppliers->fetch_assoc()): ?>
                        <option
                            value="<?= $sup['SupplierID'] ?>"
                            <?= (int)$selectedSupplierID === (int)$sup['SupplierID'] ? 'selected' : '' ?>
                        >
                            <?= htmlspecialchars($sup['SupplierName']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="col-md-4 mb-3">
                <label class="form-label">Đơn vị tính</label>
                <input
                    type="text"
                    name="unit"
                    class="form-control"
                    value="<?= htmlspecialchars($_POST['unit'] ?? $product['Unit']) ?>"
                >
            </div>

            <div class="col-md-4 mb-3">
                <label class="form-label">Giá bán (VNĐ)</label>
                <input
                    type="number"
                    step="0.01"
                    name="price"
                    class="form-control"
                    value="<?= htmlspecialchars($_POST['price'] ?? $product['Price']) ?>"
                >
            </div>

            <div class="col-md-4 mb-3">
                <label class="form-label">Số lượng tồn kho</label>
                <input
                    type="number"
                    name="stock_quantity"
                    class="form-control"
                    value="<?= htmlspecialchars($_POST['stock_quantity'] ?? $product['StockQuantity']) ?>"
                >
            </div>

            <div class="col-12 mb-3">
                <label class="form-label">Mô tả sản phẩm</label>
                <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($_POST['description'] ?? $product['Description']) ?></textarea>
            </div>

            <div class="col-12 mb-3">
                <div class="form-check">
                    <input
                        type="checkbox"
                        name="is_active"
                        class="form-check-input"
                        id="isActive"
                        <?= isset($_POST['is_active']) ? 'checked' : ($product['IsActive'] ? 'checked' : '') ?>
                    >
                    <label class="form-check-label" for="isActive">Đang kinh doanh (Active)</label>
                </div>
            </div>

        </div>

        <button type="submit" class="btn btn-primary">Lưu thay đổi</button>

    </form>

</div>

<?php

require_once '/var/www/src/includes/footer.php';

$conn->close();