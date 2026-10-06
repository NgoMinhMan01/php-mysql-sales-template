<?php

$pageTitle = 'Chỉnh sửa sản phẩm';

require_once '/var/www/src/config/database.php';

// 1. Lấy ProductID từ $_GET
$productID = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($productID <= 0) {
    header('Location: /admin/products/');
    exit;
}

// 2. Lấy thông tin sản phẩm hiện tại
$sqlProduct = "SELECT * FROM products WHERE ProductID = ?";
$stmtProduct = $conn->prepare($sqlProduct);
$stmtProduct->bind_param('i', $productID);
$stmtProduct->execute();
$product = $stmtProduct->get_result()->fetch_assoc();

if (!$product) {
    header('Location: /admin/products/');
    exit;
}

// 3. Lấy danh sách Categories và Suppliers cho Dropdown
$categories = $conn->query("SELECT CategoryID, CategoryName FROM categories ORDER BY CategoryName");
$suppliers  = $conn->query("SELECT SupplierID, SupplierName FROM suppliers ORDER BY SupplierName");

$errors = [];

// Hàm hỗ trợ cập nhật lại SortOrder và đảm bảo IsPrimary = 1 cho ảnh đầu tiên
function reorderImages($conn, $productID) {
    $stmt = $conn->prepare("SELECT ProductImageID FROM product_images WHERE ProductID = ? ORDER BY SortOrder ASC, ProductImageID ASC");
    $stmt->bind_param('i', $productID);
    $stmt->execute();
    $res = $stmt->get_result();

    $images = [];
    while ($row = $res->fetch_assoc()) {
        $images[] = $row['ProductImageID'];
    }

    if (empty($images)) {
        return;
    }

    // Đặt IsPrimary = 0 cho tất cả
    $stmtReset = $conn->prepare("UPDATE product_images SET IsPrimary = 0 WHERE ProductID = ?");
    $stmtReset->bind_param('i', $productID);
    $stmtReset->execute();

    // Cập nhật SortOrder liên tục 1, 2, 3...
    $stmtUpdate = $conn->prepare("UPDATE product_images SET SortOrder = ? WHERE ProductImageID = ?");
    foreach ($images as $index => $imageId) {
        $sortOrder = $index + 1;
        $stmtUpdate->bind_param('ii', $sortOrder, $imageId);
        $stmtUpdate->execute();
    }

    // Kiểm tra xem đã có ảnh nào làm IsPrimary chưa
    $stmtCheckPrimary = $conn->prepare("SELECT COUNT(*) AS cnt FROM product_images WHERE ProductID = ? AND IsPrimary = 1");
    $stmtCheckPrimary->bind_param('i', $productID);
    $stmtCheckPrimary->execute();
    $primaryCount = $stmtCheckPrimary->get_result()->fetch_assoc()['cnt'];

    // Nếu chưa có ảnh nào là IsPrimary, đặt ảnh đầu tiên (SortOrder = 1) làm ảnh chính
    if ($primaryCount == 0) {
        $stmtSetPrimary = $conn->prepare("UPDATE product_images SET IsPrimary = 1 WHERE ProductID = ? AND SortOrder = 1");
        $stmtSetPrimary->bind_param('i', $productID);
        $stmtSetPrimary->execute();
    }
}

// 4. Xử lý khi Form gửi dữ liệu (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- A. THAO TÁC ĐẶT ẢNH CHÍNH ---
    if (isset($_POST['action']) && $_POST['action'] === 'set_primary') {
        $imageId = (int)($_POST['image_id'] ?? 0);
        if ($imageId > 0) {
            // Đặt tất cả ảnh của sản phẩm này về IsPrimary = 0
            $stmtReset = $conn->prepare("UPDATE product_images SET IsPrimary = 0 WHERE ProductID = ?");
            $stmtReset->bind_param('i', $productID);
            $stmtReset->execute();

            // Đặt ảnh chọn thành IsPrimary = 1
            $stmtSet = $conn->prepare("UPDATE product_images SET IsPrimary = 1 WHERE ProductImageID = ? AND ProductID = ?");
            $stmtSet->bind_param('ii', $imageId, $productID);
            $stmtSet->execute();

            header("Location: /admin/products/edit.php?id=" . $productID);
            exit;
        }
    }

    // --- B. THAO TÁC XÓA ẢNH ---
    if (isset($_POST['action']) && $_POST['action'] === 'delete_image') {
        $imageId = (int)($_POST['image_id'] ?? 0);
        if ($imageId > 0) {
            // Lấy tên file ảnh để xóa file vật lý
            $stmtFile = $conn->prepare("SELECT ImageFile FROM product_images WHERE ProductImageID = ? AND ProductID = ?");
            $stmtFile->bind_param('ii', $imageId, $productID);
            $stmtFile->execute();
            $imgRow = $stmtFile->get_result()->fetch_assoc();

            if ($imgRow) {
                // Xóa khỏi CSDL
                $stmtDel = $conn->prepare("DELETE FROM product_images WHERE ProductImageID = ?");
                $stmtDel->bind_param('i', $imageId);
                $stmtDel->execute();

                // Xóa file vật lý nếu không phải ảnh seed mẫu
                $filePath = '/var/www/html/uploads/products/' . $imgRow['ImageFile'];
                if (file_exists($filePath) && strpos($imgRow['ImageFile'], 'product-') === 0) {
                    unlink($filePath);
                }

                // Sắp xếp lại SortOrder & IsPrimary
                reorderImages($conn, $productID);
            }

            header("Location: /admin/products/edit.php?id=" . $productID);
            exit;
        }
    }

    // --- C. THAO TÁC CẬP NHẬT THÔNG TIN SẢN PHẨM & UPLOAD ẢNH MỚI ---
    if (!isset($_POST['action'])) {
        $productCode   = trim($_POST['product_code'] ?? '');
        $productName   = trim($_POST['product_name'] ?? '');
        $description   = trim($_POST['description'] ?? '');
        $unit          = trim($_POST['unit'] ?? '');
        $price         = (float) ($_POST['price'] ?? 0);
        $stockQuantity = (int) ($_POST['stock_quantity'] ?? 0);
        $isActive      = isset($_POST['is_active']) ? 1 : 0;
        $categoryID    = (int) ($_POST['category_id'] ?? 0);
        $supplierID    = (int) ($_POST['supplier_id'] ?? 0);

        // Validation
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
                // Xử lý upload ảnh mới (nếu có)
                if (isset($_FILES['product_images']) && !empty($_FILES['product_images']['name'][0])) {
                    $uploadDir = '/var/www/html/uploads/products/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }

                    // Lấy SortOrder lớn nhất hiện tại
                    $stmtMax = $conn->prepare("SELECT MAX(SortOrder) AS max_order FROM product_images WHERE ProductID = ?");
                    $stmtMax->bind_param('i', $productID);
                    $stmtMax->execute();
                    $maxOrder = (int)($stmtMax->get_result()->fetch_assoc()['max_order'] ?? 0);

                    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];

                    foreach ($_FILES['product_images']['tmp_name'] as $key => $tmpName) {
                        if ($_FILES['product_images']['error'][$key] === UPLOAD_ERR_OK) {
                            $fileType = $_FILES['product_images']['type'][$key];
                            if (in_array($fileType, $allowedTypes)) {
                                $ext = pathinfo($_FILES['product_images']['name'][$key], PATHINFO_EXTENSION);
                                $newFileName = 'product-' . bin2hex(random_bytes(8)) . '.' . $ext;
                                $targetPath = $uploadDir . $newFileName;

                                if (move_uploaded_file($tmpName, $targetPath)) {
                                    $maxOrder++;
                                    $stmtAddImg = $conn->prepare("INSERT INTO product_images (ProductID, ImageFile, IsPrimary, SortOrder) VALUES (?, ?, 0, ?)");
                                    $stmtAddImg->bind_param('isi', $productID, $newFileName, $maxOrder);
                                    $stmtAddImg->execute();
                                }
                            }
                        }
                    }

                    // Sắp xếp lại và đảm bảo có 1 ảnh làm IsPrimary
                    reorderImages($conn, $productID);
                }

                header('Location: /admin/products/');
                exit;
            } else {
                $errors[] = 'Lỗi hệ thống: ' . $stmt->error;
            }
        }
    }
}

// 5. Lấy danh sách ảnh hiện tại của sản phẩm
$stmtImages = $conn->prepare("SELECT * FROM product_images WHERE ProductID = ? ORDER BY SortOrder ASC");
$stmtImages->bind_param('i', $productID);
$stmtImages->execute();
$productImages = $stmtImages->get_result();

// Xác định ID đang chọn
$selectedCategoryID = $_POST['category_id'] ?? $product['CategoryID'];
$selectedSupplierID = $_POST['supplier_id'] ?? $product['SupplierID'];

require_once '/var/www/src/includes/admin/header.php';
require_once '/var/www/src/includes/admin/navbar.php';

?>

<div class="container mt-4">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Chỉnh sửa sản phẩm</h2>
        <a href="/admin/products/" class="btn btn-secondary">Quay lại</a>
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

    <form method="POST" action="" enctype="multipart/form-data">

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

            <!-- QUẢN LÝ ẢNH SẢN PHẨM -->
            <div class="col-12 mb-3">
                <hr>
                <h5>Quản lý hình ảnh</h5>

                <!-- Upload ảnh mới -->
                <div class="mb-3">
                    <label class="form-label">Thêm ảnh mới (JPG, PNG, WEBP):</label>
                    <input type="file" name="product_images[]" class="form-control" multiple accept="image/jpeg,image/png,image/webp">
                </div>

                <!-- Danh sách ảnh hiện tại -->
                <div class="row g-3">
                    <?php if ($productImages->num_rows > 0): ?>
                        <?php while ($img = $productImages->fetch_assoc()): ?>
                            <div class="col-md-3">
                                <div class="card h-100 <?= $img['IsPrimary'] ? 'border-primary' : '' ?>">
                                    <img src="/uploads/products/<?= htmlspecialchars($img['ImageFile']) ?>" class="card-img-top" style="height: 150px; object-fit: cover;" alt="Product Image">
                                    <div class="card-body p-2 text-center">
                                        <p class="card-text mb-1 small text-muted">Thứ tự: <?= $img['SortOrder'] ?></p>

                                        <?php if ($img['IsPrimary']): ?>
                                            <span class="badge bg-primary mb-2">Ảnh chính</span>
                                        <?php else: ?>
                                            <form method="POST" action="" class="d-inline">
                                                <input type="hidden" name="action" value="set_primary">
                                                <input type="hidden" name="image_id" value="<?= $img['ProductImageID'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-primary mb-2">Đặt làm ảnh chính</button>
                                            </form>
                                        <?php endif; ?>

                                        <div>
                                            <form method="POST" action="" class="d-inline" onsubmit="return confirm('Bạn có chắc chắn muốn xóa ảnh này?');">
                                                <input type="hidden" name="action" value="delete_image">
                                                <input type="hidden" name="image_id" value="<?= $img['ProductImageID'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Xóa</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <p class="text-muted">Chưa có ảnh nào cho sản phẩm này.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <div class="mt-3">
            <button type="submit" class="btn btn-primary">Lưu thay đổi</button>
        </div>

    </form>

</div>

<?php

require_once '/var/www/src/includes/admin/footer.php';

$conn->close();
