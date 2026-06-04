<?php
namespace App\GraphQL\Mutations;

use App\Core\GraphQL\BaseMutation;

class PostMutations extends BaseMutation
{
    public function mutations(): array
    {
        return [
            'createPost' => $this->field('Post!', [
                'args' => [
                    'title' => $this->arg('title', 'string!'),
                    'content' => $this->arg('content', 'string!'),
                    'published' => $this->arg('published', 'boolean', ['defaultValue' => false])
                ],
                'resolve' => function($root, $args, $context) {
                    // Mock create
                    return [
                        'id' => 3,
                        'title' => $args['title'],
                        'content' => $args['content'],
                        'author_id' => 1,
                        'published' => $args['published'],
                        'created_at' => date('Y-m-d H:i:s')
                    ];
                }
            ])
        ];
    }
}