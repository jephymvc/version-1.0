<?php
namespace App\Entities;
use App\Core\{ Entity, BaseEntity, ImageThumbnailer, Framework };
use Carbon\Carbon;

class Product extends BaseEntity
{
    
	protected static $table 	= 'product';	
	protected static $fillable 	= [ 
		'category_id', 
		'sub_category_id', 
		'vendor_id', 
		'brand_id',
		'name',
		'slug',
		'sku',
		'images',
		'keyword',
		'description',
		'content',
		'price',
		'discount',
		'stock',
		'views',
		'rating',
		'is_active',		
		"tags", 			
		"category", 		
		"sub_category",
		"brand",
		"vendor",
		"images", 
		"old_price",
		"price" 
		
	];
	
	public static function create( array $data )
    {
        // Filter data to only include fillable fields
        if (isset(static::$fillable)) {
            $data = array_intersect_key($data, array_flip(static::$fillable));
        }
        
        return parent::create($data);
    }
	
	
	public static function createSlug( $string ) 
	{
		
		// 1. Transliterate non-ASCII characters to ASCII (e.g., 'ü' to 'u')
		$string = iconv('UTF-8', 'ASCII//TRANSLIT', $string);
		// 2. Lowercase the string
		$string = strtolower($string);
		// 3. Remove any character that isn't a letter, number, or space
		$string = preg_replace('/[^a-z0-9\s-]/', '', $string);
		// 4. Replace spaces and multiple hyphens with a single hyphen
		$string = preg_replace('/[\s-]+/', '-', $string);
		// 5. Trim hyphens from the beginning and end
		$string = trim($string, '-');
		return $string;
		
	}

   
}


?>