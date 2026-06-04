<?php
namespace App\GraphQL;

use App\Core\GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

class Schema
{
    public static function register(GraphQL $graphql): void
    {
        // Define basic types
        $userType = new ObjectType([
            'name' => 'User',
            'fields' => [
                'id' => Type::id(),
                'name' => Type::string(),
                'email' => Type::string(),
                'role' => Type::string()
            ]
        ]);
        
        $postType = new ObjectType([
            'name' => 'Post',
            'fields' => [
                'id' => Type::id(),
                'title' => Type::string(),
                'content' => Type::string(),
                'likes' => Type::int(),
                'author' => [
                    'type' => $userType,
                    'resolve' => function($post) {
                        // Return author data
                        return ['id' => 1, 'name' => 'Admin', 'email' => 'admin@example.com', 'role' => 'ADMIN'];
                    }
                ]
            ]
        ]);
        
        // Define input type for mutations
        $createPostInputType = new ObjectType([
            'name' => 'CreatePostInput',
            'fields' => [
                'title' => Type::nonNull(Type::string()),
                'content' => Type::nonNull(Type::string())
            ]
        ]);
        
        // Register queries
        $queries = [
            'me' => [
                'type' => $userType,
                'resolve' => function($root, $args, $context) {
                    return $context['user'] ?? null;
                }
            ],
            'users' => [
                'type' => Type::listOf($userType),
                'resolve' => function() {
                    return [
                        ['id' => 1, 'name' => 'Admin', 'email' => 'admin@example.com', 'role' => 'ADMIN'],
                        ['id' => 2, 'name' => 'User', 'email' => 'user@example.com', 'role' => 'USER']
                    ];
                }
            ],
            'posts' => [
                'type' => Type::listOf($postType),
                'args' => ['limit' => Type::int()],
                'resolve' => function($root, $args) {
                    $limit = $args['limit'] ?? 10;
                    return [
                        ['id' => 1, 'title' => 'First Post', 'content' => 'Content 1', 'likes' => 5],
                        ['id' => 2, 'title' => 'Second Post', 'content' => 'Content 2', 'likes' => 3]
                    ];
                }
            ]
        ];
        
        // Register mutations
        $mutations = [
            'createPost' => [
                'type' => $postType,
                'args' => ['input' => Type::nonNull($createPostInputType)],
                'resolve' => function($root, $args, $context) {
                    $user = $context['user'] ?? null;
                    if (!$user) {
                        throw new \Exception('Authentication required');
                    }
                    $input = $args['input'];
                    return [
                        'id' => rand(100, 999),
                        'title' => $input['title'],
                        'content' => $input['content'],
                        'likes' => 0,
                        'author' => $user
                    ];
                }
            ]
        ];
        
        $graphql->registerQueries($queries);
        $graphql->registerMutations($mutations);
    }
}