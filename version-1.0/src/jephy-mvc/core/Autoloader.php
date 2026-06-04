<?php
class Autoloader
{
    public static function register()
    {
        spl_autoload_register(function ($class) {
			
            // Convert namespace to file path
            $class = str_replace( '\\', DIRECTORY_SEPARATOR, $class );            
            // Possible file locations
            $files = [
                __DIR__ . '/' . $class . '.php',
                __DIR__ . '/../app/controllers/' . $class . '.php',
                __DIR__ . '/../app/models/' . $class . '.php',
                __DIR__ . '/../app/entities/' . $class . '.php',
                __DIR__ . '/../app/hooks/' . $class . '.php',
                __DIR__ . '/../app/traits/' . $class . '.php',
                __DIR__ . '/../app/classes/' . $class . '.php',
            ];
            
            foreach ($files as $file) {
                if (file_exists($file)) {
                    require_once $file;
                    return true;
                }
            }
            
            return false;
			
        });
    }
}