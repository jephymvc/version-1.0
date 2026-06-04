<?php
namespace App\Controllers;
use App\Core\Controller;
use App\Entities\Blog;
class ErrorController extends Controller
{
    public function index()
    {
        
		// Basic data
        $data = [
            'title' 	=> 'Error 404: Page not found',
            'message' 	=> 'The page you currently seek or not available or temporarily moved'
        ];
        
        // Let hooks modify the data
        $data = $this->hooks->exec( 'homepageData', $data );        
        echo $this->render( 'home/404.tpl', $data );
		
    }
    
    public function notFound()
    {	
		
		$template = $this->isSubdomain( $_SERVER['HTTP_HOST'] ) ?  'admin/404.tpl' : 'home/404.tpl';
        $data = [
            'title' 	=> 'Error 404: Page not found',
            'message' 	=> 'The page you currently seek or not available or temporarily moved'
        ];
        // Let hooks modify the data
        $data = $this->hooks->exec( 'homepageData', $data );		
        echo $this->render( $template, $data );
		
    }
    
  
}