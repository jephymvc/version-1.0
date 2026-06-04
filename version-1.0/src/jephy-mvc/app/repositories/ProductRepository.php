<?php
namespace App\Repositories;
// jephy-mvc/app/repositories/ProductRepository.php


use Core\BaseRepository;

class ProductRepository extends BaseRepository
{
    /**
     * Table name
     */
    protected $table = 'products';
    
    /**
     * Primary key
     */
    protected $primaryKey = 'id';
    
    /**
     * Fillable fields
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'compare_price',
        'sku',
        'stock',
        'category_id',
        'status',
        'images',
        'meta_title',
        'meta_description',
        'weight',
        'dimensions'
    ];
    
    /**
     * Guarded fields
     */
    protected $guarded = [
        'id',
        'created_at',
        'updated_at'
    ];
    
    /**
     * Find product by slug
     */
    public function findBySlug($slug)
    {
        return $this->findBy('slug', $slug, 1);
    }
    
    /**
     * Find products by category
     */
    public function findByCategory($categoryId, $limit = null, $sortBy = 'created_at', $sortDir = 'DESC')
    {
        
		$sql = "SELECT * FROM {$this->table} WHERE category_id = :category_id AND status = 'active' ORDER BY {$sortBy} {$sortDir}";
        
        if ($limit !== null) {
            $sql .= " LIMIT {$limit}";
        }
        
        $stmt = $this->execute($sql, [':category_id' => $categoryId]);
        return $stmt->fetchAll();
		
    }
    
    /**
     * Find products in price range
     */
    public function findByPriceRange($minPrice, $maxPrice, $limit = null)
    {
        $sql = "SELECT * FROM {$this->table} WHERE price BETWEEN :min AND :max AND status = 'active'";
        
        if ($limit !== null) {
            $sql .= " LIMIT {$limit}";
        }
        
        $stmt = $this->execute($sql, [
            ':min' => $minPrice,
            ':max' => $maxPrice
        ]);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get low stock products
     */
    public function getLowStockProducts($threshold = 10)
    {
        $sql = "SELECT * FROM {$this->table} WHERE stock <= :threshold AND status = 'active'";
        $stmt = $this->execute($sql, [':threshold' => $threshold]);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Update product stock
     */
    public function updateStock($productId, $quantity)
    {
        $sql = "UPDATE {$this->table} SET stock = stock - :quantity WHERE id = :id";
        return $this->execute($sql, [
            ':id' => $productId,
            ':quantity' => $quantity
        ]);
    }
    
    /**
     * Search products
     */
    public function search($keyword, $categoryId = null)
    {
        $sql = "SELECT * FROM {$this->table} WHERE (name LIKE :keyword OR description LIKE :keyword) AND status = 'active'";
        $params = [':keyword' => "%{$keyword}%"];
        
        if ($categoryId !== null) {
            $sql .= " AND category_id = :category_id";
            $params[':category_id'] = $categoryId;
        }
        
        $stmt = $this->execute($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Get featured products
     */
    public function getFeatured($limit = 8)
    {
        $sql = "SELECT * FROM {$this->table} WHERE status = 'active' AND featured = 1 ORDER BY created_at DESC LIMIT :limit";
        $stmt = $this->execute($sql, [':limit' => $limit]);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get related products
     */
    public function getRelatedProducts($productId, $categoryId, $limit = 4)
    {
        $sql = "SELECT * FROM {$this->table} WHERE category_id = :category_id AND id != :product_id AND status = 'active' LIMIT :limit";
        $stmt = $this->execute($sql, [
            ':category_id' => $categoryId,
            ':product_id' => $productId,
            ':limit' => $limit
        ]);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get product statistics
     */
    public function getStats()
    {
        $sql = "SELECT COUNT(*) as total_products,
                       SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_products,
                       SUM(CASE WHEN stock <= 0 THEN 1 ELSE 0 END) as out_of_stock,
                       AVG(price) as avg_price,
                       MIN(price) as min_price,
                       MAX(price) as max_price,
                       SUM(stock) as total_inventory
                FROM {$this->table}";
        
        $stmt = $this->execute($sql);
        return $stmt->fetch();
    }
    
    /**
     * Get products with pagination and filters
     */
    public function getFiltered($filters = [], $page = 1, $perPage = 20)
    {
        $where = [];
        $params = [];
        
        // Apply filters
        if (!empty($filters['category_id'])) {
            $where[] = "category_id = :category_id";
            $params[':category_id'] = $filters['category_id'];
        }
        
        if (!empty($filters['min_price'])) {
            $where[] = "price >= :min_price";
            $params[':min_price'] = $filters['min_price'];
        }
        
        if (!empty($filters['max_price'])) {
            $where[] = "price <= :max_price";
            $params[':max_price'] = $filters['max_price'];
        }
        
        if (!empty($filters['status'])) {
            $where[] = "status = :status";
            $params[':status'] = $filters['status'];
        }
        
        if (!empty($filters['in_stock'])) {
            $where[] = "stock > 0";
        }
        
        // Build WHERE clause
        $whereClause = !empty($where) ? "WHERE " . implode(' AND ', $where) : "";
        
        // Order by
        $orderBy = "created_at DESC";
        if (!empty($filters['sort_by'])) {
            $direction = !empty($filters['sort_dir']) ? $filters['sort_dir'] : 'ASC';
            $orderBy = "{$filters['sort_by']} {$direction}";
        }
        
        // Pagination
        $offset = ($page - 1) * $perPage;
        
        // Get total count
        $countSql 	= "SELECT COUNT(*) as total FROM {$this->table} {$whereClause}";
        $stmt 		= $this->execute($countSql, $params);
        $total 		= $stmt->fetch()->total;
        
        // Get paginated results
        $sql = "SELECT * FROM {$this->table} {$whereClause} ORDER BY {$orderBy} LIMIT :limit OFFSET :offset";
        $params[':limit'] 	= $perPage;
        $params[':offset'] 	= $offset;
        
        $stmt 		= $this->execute($sql, $params);
        $products 	= $stmt->fetchAll();
        
        return [
            'data' 		=> $products,
            'current_page' => $page,
            'per_page' 	=> $perPage,
            'total' 	=> $total,
            'last_page' => ceil($total / $perPage),
            'filters' 	=> $filters
        ];
		
    }
    
    /**
     * Bulk update product prices
     */
    public function bulkUpdatePrices($percentage, $operation = 'increase')
    {
        $operator = $operation === 'increase' ? '+' : '-';
        $sql = "UPDATE {$this->table} SET price = price {$operator} (price * :percentage / 100)";
        $stmt = $this->execute($sql, [':percentage' => $percentage]);
        
        return $stmt->rowCount();
    }
    
    /**
     * Delete products by category
     */
    public function deleteByCategory($categoryId, $hardDelete = false)
    {
        if ($hardDelete) {
            $sql = "DELETE FROM {$this->table} WHERE category_id = :category_id";
        } else {
            $sql = "UPDATE {$this->table} SET status = 'deleted', deleted_at = NOW() WHERE category_id = :category_id";
        }
        
        $stmt = $this->execute($sql, [':category_id' => $categoryId]);        
        return $stmt->rowCount();
    }
}

