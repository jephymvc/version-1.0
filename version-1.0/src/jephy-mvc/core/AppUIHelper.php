<?php
namespace App\Core;
class AppUIHelper {
    /**
     * Formats currency based on app settings
     * Usage in TPL: {format_price amount=1500}
     */
    public function formatPrice($params, $smarty) {
        $amount = isset($params['amount']) ? $params['amount'] : 0;
        $currency = isset($params['currency']) ? $params['currency'] : 'USD';        
        return $currency . ' ' . number_format($amount, 2);
    }
	
	
	
}