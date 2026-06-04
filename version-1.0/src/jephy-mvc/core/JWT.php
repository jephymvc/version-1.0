<?php
namespace App\Core;

// jephy-mvc/core/JWT.php
use App\Core\Config;


class JWT
{
    private $secret;
    private $algorithm;
    private $ttl;
    
    public function __construct()
    {
        $config 			= Config::getInstance();
        $this->secret 		= $config->get( 'site.jwt_secret_key', '32r32e32r32f32rr32e12');
        $this->algorithm 	= $config->get( 'site.jwt_algo', 'HS256' );
        $this->ttl 			= $config->get( 'site.jwt_ttl', 3600 ); // 1 hour default
    }
    
    public function encode($payload)
    {
        // Header
        $header = json_encode([
            'typ' => 'JWT',
            'alg' => $this->algorithm
        ]);
        
        // Payload
        $payload['iat'] = time(); // Issued at
        $payload['exp'] = time() + $this->ttl; // Expiration
        
        $encodedHeader = $this->base64UrlEncode($header);
        $encodedPayload = $this->base64UrlEncode(json_encode($payload));
        
        // Signature
        $signature = hash_hmac('sha256', "{$encodedHeader}.{$encodedPayload}", $this->secret, true);
        $encodedSignature = $this->base64UrlEncode($signature);
        
        return "{$encodedHeader}.{$encodedPayload}.{$encodedSignature}";
    }
    
    public function decode($token)
    {
        $parts = explode('.', $token);
        
        if (count($parts) !== 3) {
            throw new \Exception('Invalid token format');
        }
        
        list($encodedHeader, $encodedPayload, $encodedSignature) = $parts;
        
        // Verify signature
        $signature = $this->base64UrlDecode($encodedSignature);
        $expectedSignature = hash_hmac('sha256', "{$encodedHeader}.{$encodedPayload}", $this->secret, true);
        
        if (!hash_equals($signature, $expectedSignature)) {
            throw new \Exception('Invalid signature');
        }
        
        // Decode payload
        $payload = json_decode($this->base64UrlDecode($encodedPayload), true);
        
        // Check expiration
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            throw new \Exception('Token has expired');
        }
        
        return $payload;
    }
    
    public function refresh($token)
    {
        $payload = $this->decode($token);
        unset($payload['iat'], $payload['exp']);
        return $this->encode($payload);
    }
    
    public function validate($token)
    {
        try {
            $this->decode($token);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    private function base64UrlEncode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    private function base64UrlDecode($data)
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}