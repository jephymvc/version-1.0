<?php
namespace App\GraphQL\Queries;

use App\Core\GraphQL\BaseQuery;

class UserQueries extends BaseQuery
{
    public function queries(): array
    {
        return [
            'users' => $this->field('[User!]', [
                'args' => [
                    'limit' => $this->arg('limit', 'int', ['defaultValue' => 10]),
                    'offset' => $this->arg('offset', 'int', ['defaultValue' => 0])
                ],
                'resolve' => function($root, $args, $context) {
                    // Temporary mock data
                    return [
                        ['id' => 1, 'username' => 'john', 'email' => 'john@example.com', 'created_at' => '2024-01-01'],
                        ['id' => 2, 'username' => 'jane', 'email' => 'jane@example.com', 'created_at' => '2024-01-02'],
                    ];
                }
            ]),

            'user' => $this->field('User', [
                'args' => [
                    'id' => $this->arg('id', 'id!')
                ],
                'resolve' => function($root, $args, $context) {
                    return ['id' => $args['id'], 'username' => 'john', 'email' => 'john@example.com', 'created_at' => '2024-01-01'];
                }
            ])
        ];
    }
}