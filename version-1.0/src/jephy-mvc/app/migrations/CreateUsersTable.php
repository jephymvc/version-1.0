<?php
namespace App\Migrations;

use App\Core\Schema;
use App\Core\Blueprint;

class CreateUsersTable
{
    /**
     * Run the migration
     */
    public function up()
    {
        Schema::create( 'users', function(Blueprint $table) {
            $table->increments('id');
            $table->string('name', 100);
            $table->string('email', 255)->unique();
            $table->string('password', 255);
            $table->string('remember_token', 100)->nullable();
            $table->enum('role', ['admin', 'user'])->default('user');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->string('avatar', 255)->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('email');
            $table->index('status');
            $table->index('role');
        });
    }
    
    /**
     * Reverse the migration
     */
    public function down()
    {
        Schema::dropIfExists('users');
    }
}

