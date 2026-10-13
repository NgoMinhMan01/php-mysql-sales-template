<?php
require_once '/var/www/src/config/database.php';

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

    ORDER BY
        p.ProductID DESC
";

$result = $conn->query($sql);

$pageTitle = 'Sản phẩm';

require_once '/var/www/src/includes/frontend/header.php';
require_once '/var/www/src/includes/frontend/navbar.php';
?>

<div class="container my-4">
    <h2 class="mb-4">Danh sách sản phẩm</h2>

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
                <div class="alert alert-info">Hiện chưa có sản phẩm nào đang hoạt động.</div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
require_once '/var/www/src/includes/frontend/footer.php';
?>