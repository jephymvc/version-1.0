<?php
namespace App\Migrations;
use App\Core\Schema;
use App\Core\Blueprint;

class CreateCommentsTable
{
    /**
     * Run the migration
     */
    public function up()
    {
        Schema::create('comments', function(Blueprint $table) {
            $table->increments('id');
            $table->integer('post_id')->unsigned();
            $table->integer('user_id')->unsigned()->nullable();
            $table->string('author_name', 100)->nullable();
            $table->string('author_email', 255)->nullable();
            $table->text('content');
            $table->enum('status', ['pending', 'approved', 'spam'])->default('pending');
            $table->timestamps();
            
            // Indexes
            $table->index('post_id');
            $table->index('user_id');
            $table->index('status');
            $table->index('created_at');
            
            // Foreign keys
            $table->foreign('post_id')
                  ->references('id')
                  ->on('posts')
                  ->onDelete('CASCADE');
                  
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('SET NULL');
        });
    }
    
    /**
     * Reverse the migration
     */
    public function down()
    {
        Schema::dropIfExists('comments');
    }
}

