<?php
namespace App\Core;
class AppUIBooleanHelper {
   	
	public static function isLoggedIn( $session_key )
    {
       return isset( $_SESSION[ "user_session" ][ $session_key ] );
    }
	
	public static function isGuestLoggedIn()
    {
       return isset( $_SESSION[ "user_session" ][ "guest" ] );
    }
	
	public static function isAdminLoggedIn()
    {
       return isset( $_SESSION[ "user_session" ][ "admin" ] );
    }
	
}