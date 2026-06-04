<?php
namespace App\GraphQL\Queries;

use App\Core\GraphQL\BaseQuery;

class PostQueries extends BaseQuery
{
    public function queries(): array
    {
        return [
            'posts' => $this->field('[Post!]', [
                'args' => [
                    'limit' => $this->arg('limit', 'int', ['defaultValue' => 10])
                ],
                'resolve' => function($root, $args, $context) {
                    // Temporary mock data
                    return [
                        ['id' => 1, 'title' => 'First Post', 'content' => 'Content 1', 'author_id' => 1, 'published' => true, 'created_at' => '2024-01-01'],
                        ['id' => 2, 'title' => 'Second Post', 'content' => 'Content 2', 'author_id' => 1, 'published' => true, 'created_at' => '2024-01-02'],
                    ];
                }
            ]),

            'post' => $this->field('Post', [
                'args' => [
                    'id' => $this->arg('id', 'id!')
                ],
                'resolve' => function($root, $args, $context) {
                    return ['id' => $args['id'], 'title' => 'Sample Post', 'content' => 'Content', 'author_id' => 1, 'published' => true, 'created_at' => '2024-01-01'];
                }
            ])
        ];
    }
}