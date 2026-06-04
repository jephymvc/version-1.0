<?php
namespace App\Migrations;
use App\Core\Schema;
use App\Core\Blueprint;

class CreatePostsTable
{
    /**
     * Run the migration
     */
    public function up()
    {
        Schema::create('posts', function(Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id')->unsigned();
            $table->string('title', 255);
            $table->string('slug', 255)->unique();
            $table->text('content');
            $table->text('excerpt')->nullable();
            $table->string('featured_image', 255)->nullable();
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->integer('views')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index('user_id');
            $table->index('slug');
            $table->index('status');
            $table->index('published_at');
            
            // Foreign keys
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('CASCADE');
        });
    }
    
    /**
     * Reverse the migration
     */
    public function down()
    {
        Schema::dropIfExists('posts');
    }
}

