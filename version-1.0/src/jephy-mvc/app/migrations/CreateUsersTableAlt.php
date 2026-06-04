<?php
namespace App\Migrations;
// jephy-mvc/app/migrations/CreateUsersTable.php

use App\Core\Migration;
use App\Core\Schema;
use App\Core\Blueprint;

class CreateUsersTable extends Migration
{
    public function up()
    {
        Schema::create('users', function(Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('email', 255)->unique();
            $table->string('password', 255);
            $table->string('remember_token', 100)->nullable();
            $table->enum('role', ['admin', 'user', 'editor'])->default('user');
            $table->enum('status', ['active', 'inactive', 'banned'])->default('active');
            $table->string('avatar', 255)->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->timestamps();
            $table->softDeletes();            
            $table->index('email');
            $table->index('status');
            $table->index('role');
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('users');
    }
}

// jephy-mvc/app/migrations/CreatePostsTable.php
namespace App\Migrations;

use App\Core\Migration;
use App\Core\Schema;
use App\Core\Blueprint;

class CreatePostsTable extends Migration
{
    public function up()
    {
        Schema::create('posts', function(Blueprint $table) {
            $table->id();
            $table->integer('user_id')->unsigned();
            $table->string('title', 500);
            $table->string('slug', 500)->unique();
            $table->longText('content');
            $table->text('excerpt')->nullable();
            $table->string('featured_image', 255)->nullable();
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->integer('views')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('slug');
            $table->index('user_id');
            $table->index('status');
            $table->index('published_at');
            
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('CASCADE');
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('posts');
    }
}

// jephy-mvc/app/migrations/CreateCategoriesTable.php
namespace App\Migrations;

use App\Core\Migration;
use App\Core\Schema;
use App\Core\Blueprint;

class CreateCategoriesTable extends Migration
{
    public function up()
    {
        Schema::create('categories', function(Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->text('description')->nullable();
            $table->integer('parent_id')->unsigned()->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            
            $table->index('slug');
            $table->index('parent_id');
            
            $table->foreign('parent_id')
                  ->references('id')
                  ->on('categories')
                  ->onDelete('SET NULL');
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('categories');
    }
}

// jephy-mvc/app/migrations/CreatePostCategoriesTable.php
namespace App\Migrations;

use App\Core\Migration;
use App\Core\Schema;
use App\Core\Blueprint;

class CreatePostCategoriesTable extends Migration
{
    public function up()
    {
        Schema::create('post_categories', function(Blueprint $table) {
            $table->integer('post_id')->unsigned();
            $table->integer('category_id')->unsigned();
            $table->primary(['post_id', 'category_id']);
            
            $table->foreign('post_id')
                  ->references('id')
                  ->on('posts')
                  ->onDelete('CASCADE');
            
            $table->foreign('category_id')
                  ->references('id')
                  ->on('categories')
                  ->onDelete('CASCADE');
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('post_categories');
    }
}

