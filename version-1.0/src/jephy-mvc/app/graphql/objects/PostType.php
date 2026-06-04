<?php
namespace App\GraphQL\Objects;

use App\Core\GraphQL\BaseType;
use App\Core\Framework;

class PostType extends BaseType
{
    protected function fields(): array
    {
        return [
            'id' => 'id!',
            'title' => 'string!',
            'content' => 'string!',
            'author_id' => 'id!',
            'author' => [
                'type' => 'User!',
                'resolve' => function($post) {
                    // You'll implement this later
                    return null;
                }
            ],
            'published' => 'boolean',
            'created_at' => 'string'
        ];
    }

    protected function description(): ?string
    {
        return 'A blog post';
    }
}