<?php
namespace App\Core\Services;
use App\Core\Config;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use RuntimeException;

class JwtService
{
    private string $secretKey;
    private string $algorithm;
    private int $tokenTtl;
	private Config $config;

    public function __construct()
    {
		
		$this->config = Config::getInstance();
        $this->secretKey = $this->config->get( "site.jwt_secret_key" );
        $this->algorithm = $this->config->get( "site.jwt_algo", "HS256" );
        $this->tokenTtl = (int) $this->config->get( "site.jwt_ttl", 3600 ); // 1 hour in seconds
       
    }

    /**
     * Generate a new JWT for a user.
     *
     * @param array $payload Custom data to store in the token (e.g., ['user_id' => 123]).
     * @return string The generated JWT.
     */
    public function generateToken(array $payload): string
    {
        $issuedAt = time();
        $payload['iat'] = $issuedAt; // Issued at
        $payload['exp'] = $issuedAt + $this->tokenTtl; // Expiration time

        return JWT::encode($payload, $this->secretKey, $this->algorithm);
    }

    /**
     * Validate and decode a JWT.
     *
     * @param string $token The JWT string from the Authorization header.
     * @return array The decoded payload if valid.
     * @throws RuntimeException If the token is invalid or expired.
     */
    public function validateToken(string $token): array
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secretKey, $this->algorithm));
            return (array)$decoded;
        } catch (\Exception $e) {
            throw new RuntimeException("Invalid or expired token: " . $e->getMessage());
        }
    }
	


}