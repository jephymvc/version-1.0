<?php
namespace App\Controllers;
use Carbon\Carbon;
use App\Core\{ Controller, QueryBuilder };

class HomeController extends Controller
{
    
	public function index()
    {

        $data = [
            'title' 	=> 'Welcome to Jephy MVC',
            'message' 	=> 'Hello from Jephy MVC!'
        ];
  
        // Let hooks modify the data
        #	$data = $this->hooks->exec( 'homepageData', $data );        
        echo $this->render( 'home/index.tpl', $data);
		
    }
   
	public function documentation( $target = "" )
    {

        $data = [
            'title' 	=> 'Welcome to Jephy MVC',
            'message' 	=> 'Hello from Jephy MVC!'
        ];
  
        // Let hooks modify the data
        #	$data = $this->hooks->exec( 'homepageData', $data );        
        echo $this->render( 'home/documentation.tpl', $data);
		
    }

   
	public function mediaMixer()
    {

        $data = [
            'title' 	=> 'Welcome to Jephy MVC',
            'message' 	=> 'Hello from Jephy MVC!'
        ];
  
        $this->render( 'home/media-mixer.tpl', $data);
		
    }

	
	public function handleContactMessage()
	{
		
		if ($_POST) {			
			
            $sanitized = $this->sanitizeInput( $_POST, [
                'firstname' 	=> 'string|strip_tags',
                'lastname' 		=> 'string|strip_tags',
                'subject' 		=> 'string|strip_tags',
                'email' 		=> 'string|strip_tags',
                'message' 		=> 'string|strip_tags',
            ]);
            
			$rules = [
                'email' 		=> 'required|string|min:3',
                'firstname' 	=> 'required|string|min:3',
                'lastname' 		=> 'string|min:3',
                'subject' 		=> 'required|string|min:3',
                'email' 		=> 'required|string|min:3',
                'message' 		=> 'required|string|min:3',
            ];            
            
            $validatedData 		= $this->validate( $sanitized, $rules );
			
			if( $validatedData !== null ){
				
				$firstname 	= $validatedData[ 'firstname' ];			
                $lastname 	= $validatedData[ 'lastname' ];	
                $subject 	= $validatedData[ 'subject' ]; 	
                $email 		= $validatedData[ 'email' ]; 	
                $message 	= $validatedData[ 'message' ]; 	
				
				# TODO - Send mail both administrator and guest here
				
				$this->json( [
					'status' 	=> "ok",
					'message' 	=> "We have received your message and we would get in touch asap. Thanks."
				] );
			}
			
		}
		
		$this->json( [
			'status' 	=> "not-ok",
			'message' 	=> "Sorry, something isn't working right! Please, try again later.",
		] );
	}
    
    
	
	public function subscribeNewsletter()
	{
		
		if ($_POST) {			
			
            $sanitized = $this->sanitizeInput( $_POST, [
                'email' 		=> 'string|strip_tags',
            ]);
            
			$rules = [
                'email' 		=> 'required|string|min:3'
            ];            
            
            $validatedData 		= $this->validate( $sanitized, $rules );
			
			if( $validatedData !== null ){
				
				$existingCount = ( new QueryBuilder( 'newsletter' ) )
				->where( "email", "=", $validatedData['email'] )
				->count();				
					
				if( $existingCount < 1 ){
					( new QueryBuilder( 'newsletter' ) )->insert( [
						"email" => $validatedData['email']
					] );
				}else{
					( new QueryBuilder( 'newsletter' ) )->set( "is_active", "ACTIVE" )
					->where( "email", "=", $validatedData['email'] )
					->update();					
				}				
				
				$this->json( [
					'status' 	=> "ok",
					'message' 	=> "Your email address has been successfully subscribed."
				] );
			}
			
		}
		
		$this->json( [
			'status' 	=> "not-ok",
			'message' 	=> "Sorry, something isn't working right! Please, try again later.",
		] );
	}
    
    
	
}

