<?php
namespace  App\Core;
class Encryption
{
		
    /**
     * Encrypt data using AES-256-CBC
     */
    public static function AESEncrypt( $data, $password = "" ) 
    {
        
		// Set a random salt 
        $salt 	= openssl_random_pseudo_bytes( 16 );
		$cipher = Config::getInstance()->get( 'site.encryption_cipher', 'AES-256-CBC' );
		$encPassword 	= $password == "" ? $password : Config::getInstance()->get( 'site.encryption_key', self::generateKey() );
        $salted 		= '';
        $dx 			= '';
        
        while (strlen($salted) < 48) {
            $dx = hash('sha256', $dx . $encPassword . $salt, true);
            $salted .= $dx;
        }

        $key = substr( $salted, 0, 32 );
        $iv  = substr( $salted, 32, 16 );

        $encrypted_data = openssl_encrypt($data, $cipher, $key, OPENSSL_RAW_DATA, $iv);
        return base64_encode($salt . $encrypted_data);
		
    }
    
    /**
     * Decrypt AES-256-CBC encrypted data
     */
    public static function AESDecrypt( $edata, $password = "" ) 
    {
        
		$data 	= base64_decode($edata);
        $salt 	= substr($data, 0, 16);
        $ct 	= substr($data, 16);
		
		$encPassword = $password == "" ? $password : Config::getInstance()->get( 'site.encryption_key', self::generateKey() );
        
		$rounds = 3; // depends on key length
        $data00 = $encPassword . $salt;
        $hash = array();
        $hash[0] = hash('sha256', $data00, true);
        $result = $hash[0];
        for ($i = 1; $i < $rounds; $i++) {
            $hash[$i] = hash('sha256', $hash[$i - 1] . $data00, true);
            $result .= $hash[$i];
        }
        $key = substr($result, 0, 32);
        $iv  = substr($result, 32, 16);
		
		$cipher = Config::getInstance()->get( 'site.encryption_cipher', 'AES-256-CBC' );
        return openssl_decrypt( $ct, $cipher, $key, OPENSSL_RAW_DATA, $iv );
		
    }
	
	    // Generate random encryption key
    public static function generateKey()
    {
        return base64_encode(openssl_random_pseudo_bytes(32));
    }
    	
	
}  
