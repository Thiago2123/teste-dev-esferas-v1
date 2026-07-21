<?php

/**
 * Catálogo de produtos com cache-aside no Redis.
 */
class CatalogController
{
    private const CACHE_TTL_SECONDS = 300;

    private bool $cacheHit = false;

    public function index(): void
    {
        $category = !empty($_GET['category']) ? $_GET['category'] : null;
        $start = microtime(true);

        $products = $this->fetchCatalogFromCache($category);

        $elapsedMs = round((microtime(true) - $start) * 1000);
        header('X-Catalog-Cache: ' . ($this->cacheHit ? 'HIT' : 'MISS'));

        render('catalog', [
            'products' => $products,
            'category' => $category,
            'elapsedMs' => $elapsedMs,
            'cacheHit' => $this->cacheHit,
            'categories' => $this->categories(),
        ]);
    }

    public function update(int $id): void
    {
        $pdo = Database::connection();

        $price = isset($_POST['price']) && $_POST['price'] !== '' ? (float) $_POST['price'] : null;
        $stock = isset($_POST['stock']) && $_POST['stock'] !== '' ? (int) $_POST['stock'] : null;

        $stmt = $pdo->prepare('
            UPDATE products
            SET price = COALESCE(:price, price),
                stock = COALESCE(:stock, stock)
            WHERE id = :id
            RETURNING category
        ');
        $stmt->execute(['price' => $price, 'stock' => $stock, 'id' => $id]);
        $category = $stmt->fetchColumn();

        if ($category !== false) {
            RedisClient::connection()->del(
                $this->catalogCacheKey(null),
                $this->catalogCacheKey((string) $category)
            );
        }

        header('Content-Type: application/json');
        echo json_encode([
            'message' => 'Produto atualizado e cache do catálogo invalidado.',
        ]);
    }

    private function fetchCatalogFromCache(?string $category): array
    {
        $redis = RedisClient::connection();
        $cacheKey = $this->catalogCacheKey($category);
        $cached = $redis->get($cacheKey);

        if ($cached !== false) {
            $products = json_decode($cached, true);
            if (is_array($products)) {
                $this->cacheHit = true;
                return $products;
            }

            // Um valor inválido não deve impedir a recuperação pelo banco.
            $redis->del($cacheKey);
        }

        $products = $this->fetchCatalog($category);
        $redis->setex(
            $cacheKey,
            self::CACHE_TTL_SECONDS,
            json_encode($products, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION)
        );

        return $products;
    }

    private function catalogCacheKey(?string $category): string
    {
        return $category === null
            ? 'catalog:products:all'
            : 'catalog:products:category:' . hash('sha256', $category);
    }

    private function fetchCatalog(?string $category): array
    {
        $pdo = Database::connection();

        $sql = '
            SELECT
                p.id,
                p.name,
                p.category,
                p.price,
                p.stock,
                COALESCE(rv.avg_rating, 0) AS avg_rating,
                COALESCE(rv.reviews_count, 0) AS reviews_count,
                COALESCE(sales.total_sold, 0) AS total_sold
            FROM products p
            LEFT JOIN (
                SELECT product_id, AVG(rating) AS avg_rating, COUNT(*) AS reviews_count
                FROM product_reviews
                GROUP BY product_id
            ) rv ON rv.product_id = p.id
            LEFT JOIN (
                SELECT product_id, SUM(quantity) AS total_sold
                FROM order_items
                GROUP BY product_id
            ) sales ON sales.product_id = p.id
        ';

        $params = [];
        if ($category) {
            $sql .= ' WHERE p.category = :category';
            $params['category'] = $category;
        }

        $sql .= ' ORDER BY p.name LIMIT 200';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    private function categories(): array
    {
        $pdo = Database::connection();

        return $pdo->query('SELECT DISTINCT category FROM products ORDER BY category')->fetchAll(PDO::FETCH_COLUMN);
    }
}
