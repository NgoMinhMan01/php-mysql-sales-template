<?php
require_once '/var/www/src/config/database.php';

// 1. Lấy danh sách danh mục để đổ vào thẻ <select>
$sqlCategories = "
    SELECT CategoryID, CategoryName 
    FROM categories 
    ORDER BY CategoryName
";
$categoryResult = $conn->query($sqlCategories);
$categories = [];
while ($cat = $categoryResult->fetch_assoc()) {
    $categories[] = $cat;
}
$categoryResult->free();

// 2. Nhận tham số tìm kiếm và lọc từ URL (phương thức GET)
$keyword = trim($_GET['keyword'] ?? '');
$categoryID = intval($_GET['category_id'] ?? 0);

// 3. Cấu hình phân trang
$itemsPerPage = 6;
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1) {
    $page = 1;
}
$searchKeyword = '%' . $keyword . '%';

// 4. Đếm tổng số sản phẩm phù hợp
$sqlCount = "
    SELECT COUNT(*) AS TotalProducts
    FROM products p, categories c
    WHERE p.CategoryID = c.CategoryID
      AND p.IsActive = 1
";

$countParams = [];
$countTypes = '';

if ($categoryID > 0) {
    $sqlCount .= " AND p.CategoryID = ?";
    $countParams[] = $categoryID;
    $countTypes .= 'i';
}

if ($keyword !== '') {
    $sqlCount .= " AND (p.ProductName LIKE ? OR p.ProductCode LIKE ?)";
    $countParams[] = $searchKeyword;
    $countParams[] = $searchKeyword;
    $countTypes .= 'ss';
}

$stmtCount = $conn->prepare($sqlCount);
if (!empty($countParams)) {
    $stmtCount->bind_param($countTypes, ...$countParams);
}
$stmtCount->execute();
$countResult = $stmtCount->get_result();
$countRow = $countResult->fetch_assoc();
$totalProducts = (int) $countRow['TotalProducts'];
$countResult->free();
$stmtCount->close();

// 5. Tính tổng số trang và offset
$totalPages = (int) ceil($totalProducts / $itemsPerPage);
if ($totalPages > 0 && $page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $itemsPerPage;

// 6. Xây dựng câu lệnh SQL lấy sản phẩm với LIMIT và OFFSET
$sql = "
    SELECT
        p.ProductID,
        p.ProductCode,
        p.ProductName,
        p.Price,
        c.CategoryName,
        (
            SELECT pi.ImageFile
            FROM product_images pi
            WHERE pi.ProductID = p.ProductID
              AND pi.IsPrimary = 1
            LIMIT 1
        ) AS ImageFile
    FROM
        products p,
        categories c
    WHERE
        p.CategoryID = c.CategoryID
        AND p.IsActive = 1
";

$params = [];
$types = '';

if ($categoryID > 0) {
    $sql .= " AND p.CategoryID = ?";
    $params[] = $categoryID;
    $types .= 'i';
}

if ($keyword !== '') {
    $sql .= " AND (p.ProductName LIKE ? OR p.ProductCode LIKE ?)";
    $params[] = $searchKeyword;
    $params[] = $searchKeyword;
    $types .= 'ss';
}

$sql .= " ORDER BY p.ProductID DESC LIMIT ? OFFSET ?";
$params[] = $itemsPerPage;
$params[] = $offset;
$types .= 'ii';

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$pageTitle = 'Sản phẩm';

require_once '/var/www/src/includes/frontend/header.php';
require_once '/var/www/src/includes/frontend/navbar.php';
?>

<div class="container my-4">
    <h2 class="mb-4">Danh sách sản phẩm</h2>

    <!-- Form Tìm kiếm và Lọc danh mục -->
    <form method="GET" action="products.php" class="row g-3 mb-4">
        <div class="col-md-5">
            <input 
                type="text" 
                name="keyword" 
                class="form-control" 
                placeholder="Nhập tên hoặc mã sản phẩm..." 
                value="<?= htmlspecialchars($keyword) ?>"
            >
        </div>
        <div class="col-md-4">
            <select name="category_id" class="form-select">
                <option value="0">-- Tất cả danh mục --</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['CategoryID'] ?>" <?= ($categoryID == $cat['CategoryID']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat['CategoryName']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-primary w-50">Tìm kiếm</button>
            <a href="products.php" class="btn btn-outline-secondary w-50">Đặt lại</a>
        </div>
    </form>

    <p class="text-muted mb-4">
        Tìm thấy <strong><?= $totalProducts ?></strong> sản phẩm.
    </p>

    <div class="row">
        <?php if ($result && $result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <div class="col-md-3 mb-4">
                    <div class="card h-100 shadow-sm">
                        <?php 
                        $imagePath = !empty($row['ImageFile']) 
                            ? '/uploads/products/' . htmlspecialchars($row['ImageFile']) 
                            : 'https://via.placeholder.com/300';
                        ?>
                        <img src="<?= $imagePath ?>" class="card-img-top" alt="<?= htmlspecialchars($row['ProductName']) ?>" style="height: 200px; object-fit: cover;">
                        
                        <div class="card-body d-flex flex-column">
                            <span class="badge bg-secondary mb-2 align-self-start"><?= htmlspecialchars($row['CategoryName']) ?></span>
                            <h5 class="card-title"><?= htmlspecialchars($row['ProductName']) ?></h5>
                            <p class="card-text text-danger fw-bold mb-2"><?= number_format($row['Price'], 0, ',', '.') ?> đ</p>
                            
                            <div class="mt-auto">
                                <a href="/product-detail.php?id=<?= $row['ProductID'] ?>" class="btn btn-outline-primary w-100">Xem chi tiết</a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="alert alert-info">Không tìm thấy sản phẩm nào phù hợp.</div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Thanh phân trang (Pagination UI) -->
    <?php if ($totalPages > 1): ?>
        <nav class="mt-5" aria-label="Phân trang sản phẩm">
            <ul class="pagination justify-content-center flex-wrap">
                <!-- Nút Trước -->
                <?php
                $previousQuery = http_build_query([
                    'keyword' => $keyword,
                    'category_id' => $categoryID,
                    'page' => max(1, $page - 1)
                ]);
                ?>
                <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                    <a class="page-link" href="products.php?<?= $previousQuery ?>">&laquo; Trước</a>
                </li>

                <!-- Các số trang -->
                <?php for ($pageNumber = 1; $pageNumber <= $totalPages; $pageNumber++): ?>
                    <?php
                    $query = http_build_query([
                        'keyword' => $keyword,
                        'category_id' => $categoryID,
                        'page' => $pageNumber
                    ]);
                    ?>
                    <li class="page-item <?= ($pageNumber === $page) ? 'active' : '' ?>">
                        <a class="page-link" href="products.php?<?= $query ?>"><?= $pageNumber ?></a>
                    </li>
                <?php endfor; ?>

                <!-- Nút Sau -->
                <?php
                $nextQuery = http_build_query([
                    'keyword' => $keyword,
                    'category_id' => $categoryID,
                    'page' => min($totalPages, $page + 1)
                ]);
                ?>
                <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                    <a class="page-link" href="products.php?<?= $nextQuery ?>">Sau &raquo;</a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
</div>

<?php
if (isset($result)) {
    $result->free();
}
if (isset($stmt)) {
    $stmt->close();
}
require_once '/var/www/src/includes/frontend/footer.php';
?>