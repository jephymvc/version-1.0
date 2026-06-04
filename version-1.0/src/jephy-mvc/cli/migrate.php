<?php

require_once __DIR__ . '/../bootstrap.php';

use App\Core\MigrationRunner;

$command = $argv[1] ?? 'migrate';

switch ($command) {
    case 'migrate':
        MigrationRunner::migrate();
        break;
        
    case 'rollback':
        MigrationRunner::rollback();
        break;
        
    case 'reset':
        MigrationRunner::reset();
        break;
        
    case 'refresh':
        MigrationRunner::refresh();
        break;
        
    case 'make':
        $name = $argv[2] ?? null;
        if (!$name) {
            echo "Please provide migration name\n";
            exit(1);
        }
        self::createMigration($name);
        break;
        
    default:
        echo "Commands: migrate, rollback, reset, refresh, make <name>\n";
        break;
}

function createMigration($name)
{
    $className = 'Create' . ucfirst($name) . 'Table';
    $filename = date('Y_m_d_His') . '_' . $className . '.php';
    $path = __DIR__ . '/../app/migrations/' . $filename;
    
    $template = <<<PHP
<?php

namespace App\Migrations;

use App\Core\Migration;
use App\Core\Schema;
use App\Core\Blueprint;

class {$className} extends Migration
{
    public function up()
    {
        Schema::create('{$name}', function(Blueprint \$table) {
            \$table->id();
            // Add your columns here
            \$table->timestamps();
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('{$name}');
    }
}
PHP;
    
    file_put_contents($path, $template);
    echo "Created migration: {$filename}\n";
}