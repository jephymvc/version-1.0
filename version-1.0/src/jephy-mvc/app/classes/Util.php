<?php
namespace App\Classes;
use App\Core\{ Validator, QueryBuilder, JephyORM };
use App\Hooks\GlobalDataHook;
use App\Entities\{ ProductCategoryAlt, ProductSubCategoryAlt, Vendor, Brand, ProductAlt };

class Util{
	public static function formatProductData( $products ){
		$productsData = [];
		foreach( $products as $product ){
			
			$category 			= ( new QueryBuilder( 'product_category' ) )->select( [ 'id', 'category', 'slug' ] )->where( 'id', '=', $product['category_id'] )->first();
			$subCategory 		= ( new QueryBuilder( 'product_sub_category' ) )->select( [ 'id', 'category', 'slug' ] )->where( 'id', '=', $product['sub_category_id'] )->first();
			$vendor 			= ( new QueryBuilder( 'vendor' ) )->select( [ 'id', 'name', 'slug' ] )->where( 'id', '=', $product['vendor_id'] )->first();
			$brand 				= ( new QueryBuilder( 'brand' ) )->select( [ 'id', 'name', 'slug' ] )->where( 'id', '=', $product['brand_id'] )->first();
			$productImageData 	= explode( ";", $product['images'] );
			$productImageUrls 	= [];
			$globalData 		= GlobalDataHook::getInstance();
			
			foreach( $productImageData as $image ){
				$productImageUrls[] = $globalData->get( 'url.home' ) . '/products/' .$image;
			}
			
			$discount = ( $product['discount'] / 100 ) * $product['price'];
			$newPrice = $product['price'] - $discount;
			
			$productsData[] = [
				...$product,
				"tags" 			=> self::formatProductTags( $product['keyword'] ),
				"category" 		=> $category,
				"sub_category" 	=> $subCategory,
				"brand" 	=> $brand,
				"vendor" 	=> $vendor,
				"images" 	=> $productImageUrls,
				"old_price" => $product['price'],
				"price" 	=> $newPrice
			];
			
		}
		
		return $productsData ;
		
	}
	
	public static function formatProductImageLinks( $product )
	{
		
		$productData 	= [];		
		$category 		= ( new QueryBuilder( 'product_category' ) )->select( [ 'category', 'slug' ] )->where( 'id', '=', $product['category_id'] )->first();
		$subCategory 	= ( new QueryBuilder( 'product_sub_category' ) )->select( [ 'category', 'slug' ] )->where( 'id', '=', $product['sub_category_id'] )->first();
		$vendor 		= ( new QueryBuilder( 'vendor' ) )->select( [ 'id', 'name', 'slug' ] )->where( 'id', '=', $product['vendor_id'] )->first();
		$brand 			= ( new QueryBuilder( 'brand' ) )->select( [ 'id', 'name', 'slug' ] )->where( 'id', '=', $product['brand_id'] )->first();
		$productImageData 	= explode( ";", $product['images'] );
		$productImageUrls 	= [];
		$globalData 		= GlobalDataHook::getInstance();
		
		foreach( $productImageData as $image ){
			$productImageUrls[] = $globalData->get( 'url.home' ) . '/products/' .$image;
		}
		
		$discount = ( $product['discount'] / 100 ) * $product['price'];
		$newPrice = $product['price'] - $discount;
		
		$productData = [
			...$product,
			"tags" 			=> self::formatProductTags( $product['keyword'] ),
			"category" 		=> $category,
			"sub_category" 	=> $subCategory,
			"brand" 	=> $brand,
			"vendor" 	=> $vendor,
			"images" 	=> $productImageUrls,
			"old_price" => $product['price'],
			"price" 	=> $newPrice
		];
			
		return $productData ;
		
	}
	
	public static function formatProductObjectData( $product )
	{
	
		$productData 	= [];		
		$category 		= ( new JephyORM( ProductCategoryAlt::class ) )->findFirst( [
			"where" => [
				'id' => $product->category_id
			]
		] );
		
		$subCategory 		= ( new JephyORM( ProductSubCategoryAlt::class ) )->findFirst( [
			"where" => [
				'id' => $product->sub_category_id
			]
		] );
		
		$vendor 		= ( new JephyORM( Vendor::class ) )->findFirst( [
			"where" => [
				'id' => $product->vendor_id
			]
		] );
		
		$brand 		= ( new JephyORM( Brand::class ) )->findFirst( [
			"where" => [
				'id' => $product->brand_id
			],
			"includes" => [
				"id", "name", "slug"
			]
		] );
		
		$productImageData 	= $product->images != "" ? explode( ";", $product->images ) : [];
		$productImageUrls 	= [];
		$globalData 		= GlobalDataHook::getInstance();
		
		foreach( $productImageData as $image ){
			$productImageUrls[] = $globalData->get( 'url.home' ) . '/products/' .$image;
		}
		
		$discount = ( $product->discount / 100 ) * $product->price ;
		$newPrice = $product->price - $discount;
		
		$product->tags 			= self::formatProductTags( $product->keyword );
		$product->category 		= $category;
		$product->sub_category 	= $subCategory;
		$product->brand 		= $brand;
		$product->vendor 		= $vendor;
		$product->images 		= $productImageUrls;
		$product->old_price 	= $product->price;
		$product->price 		= $newPrice;
		
		//	$productData = [
		//		...$product,
		//		"tags" 			=> self::formatProductTags( $product->keyword ),
		//		"category" 		=> $category,
		//		"sub_category" 	=> $subCategory,
		//		"brand" 	=> $brand,
		//		"vendor" 	=> $vendor,
		//		"images" 	=> $productImageUrls,
		//		"old_price" => $product['price'],
		//		"price" 	=> $newPrice
		//	];
			
		return $product ;
		
	}
	
	public static function formatProductTags( $keywords )
	{		
		return $keywords != "" ? str_replace( ";", ", ", $keywords ): ""; 		
	}
	
	public static function shortenTexts( $texts, $max = 21 ){
		if( strlen( $texts ) > $max ){
			return substr( $texts, 0, $max );
		}
		return $text;
	}
	
	
	public static function sanitizeInput($input, $rules = [])
    {
        $sanitized = [];
        
        foreach ($input as $key => $value) {
            if (is_array($value)) {
                $sanitized[$key] = self::sanitizeArray($value);
            } else {
                $sanitized[$key] = self::sanitizeValue($value, $rules[$key] ?? '');
            }
        }
        
        return $sanitized;
    }
	
	public static function validate($data, $rules)
    {
        if (!class_exists('App\\Core\\Validator')) {
            // Simple fallback validation
            return $data;
        }
        
        $validator = new Validator($data);
        $validator->make($data, $rules);
        
        if (!$validator->validate()) {
            $this->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 400);
        }
        
        return $validator->validated();
    }
	
	
	    
    public static function sanitizeArray($array)
    {
        $sanitized = [];
        
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $sanitized[$key] = self::sanitizeArray($value);
            } else {
                $sanitized[$key] = self::sanitizeValue($value);
            }
        }
        
        return $sanitized;
    }
    
    public static function sanitizeValue($value, $rule = '')
    {
        if (is_numeric($value)) {
            return $value;
        }
        
        if (is_bool($value)) {
            return $value;
        }
        
        if ($value === null) {
            return $value;
        }
        
        // Default sanitization
        $value = trim($value);
        $value = stripslashes($value);
        $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        
        return $value;
    }
    
	
	
}


