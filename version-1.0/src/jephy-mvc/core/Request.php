<?php
namespace App\Core;

class Request
{
    protected $get;
    protected $post;
    protected $files;
    protected $server;
    protected $headers;
    protected $json;
    protected $input;
    
    public function __construct()
    {
        $this->get = $_GET;
        $this->post = $_POST;
        $this->files = $_FILES;
        $this->server = $_SERVER;
        $this->headers = $this->parseHeaders();
        $this->json = $this->parseJsonInput();
        $this->input = $this->parseInput();
    }
    
    /**
     * Get all POST data or specific field
     */
    public function post($key = null, $default = null)
    {
        if ($key === null) {
            return $this->post;
        }
        
        return $this->post[$key] ?? $default;
    }
    
    /**
     * Get all GET data or specific field
     */
    public function get($key = null, $default = null)
    {
        if ($key === null) {
            return $this->get;
        }
        
        return $this->get[$key] ?? $default;
    }
    
    /**
     * Get all request data (POST + GET merged)
     */
    public function all()
    {
        return array_merge($this->get, $this->post, $this->json);
    }
    
    /**
     * Get input from POST, GET, or JSON
     */
    public function input($key = null, $default = null)
    {
        if ($key === null) {
            return $this->input;
        }
        
        return $this->input[$key] ?? $default;
    }
    
    /**
     * Get JSON input
     */
    public function json($key = null, $default = null)
    {
        if ($key === null) {
            return $this->json;
        }
        
        return $this->json[$key] ?? $default;
    }
    
    /**
     * Check if request method is POST
     */
    public function isPost()
    {
        return $this->getMethod() === 'POST';
    }
    
    /**
     * Check if request method is GET
     */
    public function isGet()
    {
        return $this->getMethod() === 'GET';
    }
    
    /**
     * Check if request is AJAX
     */
    public function isAjax()
    {
        return isset($this->server['HTTP_X_REQUESTED_WITH']) && 
               strtolower($this->server['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
    
    /**
     * Get request method
     */
    public function getMethod()
    {
        return $this->server['REQUEST_METHOD'] ?? 'GET';
    }
    
    /**
     * Get the bearer token from Authorization header
     */
    public function bearerToken()
    {
        $authHeader = $this->header('Authorization');
        
        if ($authHeader && preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
    
    /**
     * Get a specific header
     */
    public function header($key, $default = null)
    {
        $normalizedKey = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
        
        // Check standard HTTP_ prefixed headers
        if (isset($this->server[$normalizedKey])) {
            return $this->server[$normalizedKey];
        }
        
        // Check direct header name
        if (isset($this->headers[$key])) {
            return $this->headers[$key];
        }
        
        return $default;
    }
    
    /**
     * Get client IP address
     */
    public function ip()
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP'
        ];
        
        foreach ($headers as $header) {
            if (!empty($this->server[$header])) {
                $ips = explode(',', $this->server[$header]);
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return $this->server['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Get all headers
     */
    protected function parseHeaders()
    {
        $headers = [];
        
        foreach ($this->server as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $header = str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower(substr($key, 5)))));
                $headers[$header] = $value;
            }
        }
        
        return $headers;
    }
    
    /**
     * Parse JSON input from request body - FIXED VERSION
     */
    protected function parseJsonInput()
    {
        // Get content type safely
        $contentType = $this->header('Content-Type', '');
        
        // Check if content type exists and contains application/json
        if (!empty($contentType) && strpos($contentType, 'application/json') !== false) {
            $input = file_get_contents('php://input');
            if (!empty($input)) {
                $decoded = json_decode($input, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        }
        
        return [];
    }
    
    /**
     * Parse all input (POST, GET, JSON)
     */
    protected function parseInput()
    {
        $input = array_merge($this->get, $this->post);
        
        if (!empty($this->json)) {
            $input = array_merge($input, $this->json);
        }
        
        return $input;
    }
    
    /**
     * Get only specific fields from input
     */
    public function only($fields)
    {
        $fields = is_array($fields) ? $fields : func_get_args();
        $result = [];
        
        foreach ($fields as $field) {
            if (isset($this->input[$field])) {
                $result[$field] = $this->input[$field];
            }
        }
        
        return $result;
    }
    
    /**
     * Get all input except specific fields
     */
    public function except($fields)
    {
        $fields = is_array($fields) ? $fields : func_get_args();
        $result = $this->input;
        
        foreach ($fields as $field) {
            unset($result[$field]);
        }
        
        return $result;
    }
    
    /**
     * Check if input has a specific field
     */
    public function has($key)
    {
        return isset($this->input[$key]);
    }
    
    /**
     * Get a file from the request
     */
    public function file($key)
    {
        return $this->files[$key] ?? null;
    }
    
    /**
     * Check if a file was uploaded
     */
    public function hasFile($key)
    {
        return isset($this->files[$key]) && $this->files[$key]['error'] !== UPLOAD_ERR_NO_FILE;
    }
    
    /**
     * Get all files
     */
    public function allFiles()
    {
        return $this->files;
    }
    
    /**
     * Get request URI
     */
    public function uri()
    {
        return $this->server['REQUEST_URI'] ?? '/';
    }
    
    /**
     * Get request path (without query string)
     */
    public function path()
    {
        $uri = $this->uri();
        $queryPos = strpos($uri, '?');
        
        if ($queryPos !== false) {
            return substr($uri, 0, $queryPos);
        }
        
        return $uri;
    }
    
    /**
     * Check if request expects JSON response
     */
    public function expectsJson()
    {
        $accept = $this->header('Accept', '');
        return strpos($accept, 'application/json') !== false;
    }
    
    /**
     * Get request scheme (http or https)
     */
    public function scheme()
    {
        return isset($this->server['HTTPS']) && $this->server['HTTPS'] !== 'off' ? 'https' : 'http';
    }
    
    /**
     * Get full URL
     */
    public function fullUrl()
    {
        $scheme = $this->scheme();
        $host = $this->server['HTTP_HOST'] ?? '';
        $uri = $this->uri();
        
        return $scheme . '://' . $host . $uri;
    }
}

