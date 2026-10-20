<?php
require_once '/var/www/src/config/session.php';
require_once '/var/www/src/config/database.php';

// Lấy ID sản phẩm từ URL và ép kiểu số nguyên để bảo mật
$productId = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Truy vấn thông tin chi tiết sản phẩm kết hợp categories và suppliers
$sql = "
    SELECT
        p.ProductID,
        p.ProductCode,
        p.ProductName,
        p.Description,
        p.Price,
        p.Unit,
        p.StockQuantity,
        c.CategoryName,
        s.SupplierName
    FROM
        products p,
        categories c,
        suppliers s
    WHERE
        p.CategoryID = c.CategoryID
        AND p.SupplierID = s.SupplierID
        AND p.ProductID = $productId
        AND p.IsActive = 1
";

$result = $conn->query($sql);

if (!$result || $result->num_rows === 0) {
    $pageTitle = 'Không tìm thấy sản phẩm';
    require_once '/var/www/src/includes/frontend/header.php';
    require_once '/var/www/src/includes/frontend/navbar.php';
    echo '<main class="container py-5"><div class="alert alert-warning">Sản phẩm không tồn tại hoặc đã ngừng kinh doanh.</div></main>';
    require_once '/var/www/src/includes/frontend/footer.php';
    exit;
}

$product = $result->fetch_assoc();
$pageTitle = $product['ProductName'];

// Xử lý thêm sản phẩm vào giỏ hàng (POST request)
$cartMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['add_to_cart'])) {

    $quantity = isset($_POST['quantity'])
        ? (int) $_POST['quantity']
        : 1;

    if ($quantity < 1) {
        $quantity = 1;
    }

    $stockQuantity = (int) $product['StockQuantity'];

    if ($stockQuantity <= 0) {
        $cartMessage = 'Sản phẩm hiện đã hết hàng.';
    } else {
        $currentQuantity = $_SESSION['cart'][$productId] ?? 0;
        $newQuantity = $currentQuantity + $quantity;

        if ($newQuantity > $stockQuantity) {
            $newQuantity = $stockQuantity;
        }

        $_SESSION['cart'][$productId] = $newQuantity;
        $cartMessage = 'Đã thêm sản phẩm vào giỏ hàng.';
    }
}

// Truy vấn danh sách tất cả hình ảnh của sản phẩm này
$sqlImages = "
    SELECT ImageFile, IsPrimary 
    FROM product_images 
    WHERE ProductID = $productId 
    ORDER BY IsPrimary DESC
";
$imagesResult = $conn->query($sqlImages);

require_once '/var/www/src/includes/frontend/header.php';
require_once '/var/www/src/includes/frontend/navbar.php';
?>

<main class="container py-5">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/products.php">Sản phẩm</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($product['ProductName']) ?></li>
        </ol>
    </nav>

    <?php if (!empty($cartMessage)): ?>
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            <?= htmlspecialchars($cartMessage) ?>
            <a href="/cart.php" class="alert-link ms-2">Xem giỏ hàng</a>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row g-5">
        <!-- Cột hình ảnh sản phẩm (Gallery) -->
        <div class="col-md-6">
            <?php if ($imagesResult && $imagesResult->num_rows > 0): ?>
                <?php 
                $images = $imagesResult->fetch_all(MYSQLI_ASSOC);
                $primaryImage = $images[0]['ImageFile'];
                ?>
                <div class="card mb-3 border-0 shadow-sm bg-light text-center p-3" style="height: 350px;">
                    <img id="mainImage" src="/uploads/products/<?= htmlspecialchars($primaryImage) ?>" class="w-100 h-100" style="object-fit: contain;" alt="<?= htmlspecialchars($product['ProductName']) ?>">
                </div>
                
                <?php if (count($images) > 1): ?>
                    <div class="row g-2">
                        <?php foreach ($images as $img): ?>
                            <div class="col-3">
                                <div class="card border p-1 text-center" style="height: 80px; cursor: pointer;" onclick="document.getElementById('mainImage').src='/uploads/products/<?= htmlspecialchars($img['ImageFile']) ?>'">
                                    <img src="/uploads/products/<?= htmlspecialchars($img['ImageFile']) ?>" class="w-100 h-100" style="object-fit: contain;" alt="Thumbnail">
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="card bg-light d-flex align-items-center justify-content-center text-muted" style="height: 350px;">
                    Chưa có hình ảnh
                </div>
            <?php endif; ?>
        </div>

        <!-- Cột thông tin chi tiết sản phẩm -->
        <div class="col-md-6">
            <span class="badge bg-secondary mb-2"><?= htmlspecialchars($product['CategoryName']) ?></span>
            <h1 class="h3 fw-bold mb-3"><?= htmlspecialchars($product['ProductName']) ?></h1>
            
            <p class="text-muted small mb-2">Mã sản phẩm: <strong><?= htmlspecialchars($product['ProductCode']) ?></strong></p>
            <p class="text-muted small mb-2">Nhà cung cấp: <strong><?= htmlspecialchars($product['SupplierName']) ?></strong></p>
            
            <p class="fw-bold fs-4 text-danger mb-4">
                <?= number_format((float) $product['Price'], 0, ',', '.') ?> đ
            </p>

            <div class="mb-4">
                <p class="mb-1"><strong>Đơn vị tính:</strong> <?= htmlspecialchars($product['Unit']) ?></p>
                <p class="mb-1"><strong>Tồn kho:</strong> <?= (int) $product['StockQuantity'] ?> sản phẩm</p>
            </div>

            <div class="mb-4">
                <h5>Mô tả sản phẩm</h5>
                <p class="text-secondary"><?= nl2br(htmlspecialchars($product['Description'] ?? 'Không có mô tả cho sản phẩm này.')) ?></p>
            </div>

            <!-- Form Thêm vào giỏ hàng -->
            <form action="" method="POST" class="row g-3">
                <div class="col-auto">
                    <label for="quantity" class="visually-hidden">Số lượng</label>
                    <input type="number" id="quantity" name="quantity" value="1" min="1" max="<?= (int) $product['StockQuantity'] ?>" class="form-control form-control-lg text-center" style="width: 90px;">
                </div>
                <div class="col">
                    <button type="submit" name="add_to_cart" class="btn btn-primary btn-lg w-100">
                        Thêm vào giỏ hàng
                    </button>
                </div>
            </form>
        </div>
    </div>
</main>

<?php
if (isset($imagesResult)) {
    $imagesResult->free();
}
if (isset($result)) {
    $result->free();
}
require_once '/var/www/src/includes/frontend/footer.php';
?>