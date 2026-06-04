<?php
namespace App\Hooks;
use App\Core\Framework;
use App\Core\HookManager;
class EcommerceHooks
{
    public function registerHooks(HookManager $hooks)
    {
        $hooks->registerHook( 'shoppingCart', [ $this, 'displayCartSummary' ] );
        $hooks->registerHook( 'productReviews', [ $this, 'displayReviewSection' ] );
        $hooks->registerHook( 'categorySidebar', [ $this, 'displayCategoryFilters' ] );
    }
    
    public function displayCartSummary($params)
    {
        $cartTotal = $params['total'] ?? 0;
        $itemCount = $params['item_count'] ?? 0;
        
        return '
            <div class="cart-summary">
                <div class="cart-total">Total: $' . number_format($cartTotal, 2) . '</div>
                <div class="item-count">Items: ' . $itemCount . '</div>
                <a href="/checkout" class="checkout-btn">Proceed to Checkout</a>
            </div>
        ';
    }
    
    public function displayReviewSection($params)
    {
        $product = $params['product'];
        
        // Fetch reviews from database
        $reviews = Review::where('product_id', $product['id'])
            ->where('approved', 1)
            ->orderBy('created_at', 'DESC')
            ->limit(5)
            ->get();
        
        $html = '<div class="product-reviews">
                    <h3>Customer Reviews</h3>';
        
        if (count($reviews) > 0) {
            foreach ($reviews as $review) {
                $html .= '
                    <div class="review">
                        <div class="review-author">' . htmlspecialchars($review->author) . '</div>
                        <div class="review-rating">' . str_repeat('★', $review->rating) . '</div>
                        <div class="review-content">' . nl2br(htmlspecialchars($review->content)) . '</div>
                        <div class="review-date">' . date('M j, Y', strtotime($review->created_at)) . '</div>
                    </div>
                ';
            }
        } else {
            $html .= '<p>No reviews yet. Be the first to review this product!</p>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    public function displayCategoryFilters($params)
    {
        $category = $params['category'];
        
        return '
            <div class="category-filters">
                <h4>Filter By</h4>
                
                <div class="filter-group">
                    <h5>Price Range</h5>
                    <label><input type="checkbox" name="price[]" value="0-25"> $0 - $25</label>
                    <label><input type="checkbox" name="price[]" value="25-50"> $25 - $50</label>
                    <label><input type="checkbox" name="price[]" value="50-100"> $50 - $100</label>
                    <label><input type="checkbox" name="price[]" value="100-999"> $100+</label>
                </div>
                
                <div class="filter-group">
                    <h5>Brand</h5>
                    <label><input type="checkbox" name="brand[]" value="nike"> Nike</label>
                    <label><input type="checkbox" name="brand[]" value="adidas"> Adidas</label>
                    <label><input type="checkbox" name="brand[]" value="puma"> Puma</label>
                </div>
                
                <button type="button" class="apply-filters">Apply Filters</button>
            </div>
        ';
    }
}