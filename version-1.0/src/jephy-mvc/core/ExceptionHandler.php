<?php
namespace App\Core;

class ExceptionHandler
{
    public static function handle(\Exception $e)
    {
        // Log the exception
        Logger::critical($e->getMessage(), [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
            'code' => $e->getCode()
        ]);
        
        // Return appropriate response
        if (strpos($_SERVER['REQUEST_URI'], '/api/') === 0) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $_ENV['APP_ENV'] === 'development' ? $e->getMessage() : 'Internal server error'
            ]);
        } else {
            // Show error page
            include __DIR__ . '/../../views/errors/500.php';
        }
        
        exit;
    }
}