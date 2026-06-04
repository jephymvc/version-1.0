<?php
namespace App\Controllers;

#	use App\Core\GraphQL\GraphQLBaseController;
use App\Core\GraphQLBaseController;
use App\Core\GraphQL\Type;
use App\Core\GraphQL\TypeRegistry;
use GraphQL\Type\Definition\ObjectType;

class GraphQLController extends GraphQLBaseController
{
    
	public function __construct()
    {
        parent::__construct();
        $this->setSandboxTitle('My API GraphQL Sandbox');
        
        if (isset($_SESSION['user'])) {
            $this->setContext(['user' => $_SESSION['user']]);
        }
		
		// Disable CSRF verification for GraphQL endpoint
        $this->disableCSRF();
		
    }
	
	
	   
    /**
     * Disable CSRF verification for GraphQL requests
     */
    protected function disableCSRF(): void
    {
        // If your framework has a CSRF verification method, disable it
        if (method_exists($this, 'skipCSRF')) {
            $this->skipCSRF();
        }
        
        // Remove CSRF token check from request
        if (isset($_POST['csrf_token'])) {
            unset($_POST['csrf_token']);
        }
        
        // Clear CSRF headers if any
        if (function_exists('header_remove')) {
            header_remove('X-CSRF-Token');
            header_remove('X-CSRF-Protection');
        }
    }
	
	 /**
     * Override the handle method to bypass CSRF checks
     */
    public function handle(): void
    {
        // Set headers to indicate this is a JSON API endpoint
        header('Content-Type: application/json');        
        // Allow CSRF token to be bypassed
        if (function_exists('csrf_protection')) {
            csrf_protection(false);
        }
        
        // Call parent handle method
        parent::handle();
    }
    
    /**
     * Override getUserArray to use your actual User model
     */
    protected function getUserArray($userId): ?array
    {
        // TODO: Replace with your actual User model
        $users = [
            1 => ['id' => 1, 'name' => 'Admin', 'email' => 'admin@example.com', 'role' => 'ADMIN', 'permissions' => ['post:create', 'post:update', 'post:delete']],
            2 => ['id' => 2, 'name' => 'User', 'email' => 'user@example.com', 'role' => 'USER', 'permissions' => ['post:create']]
        ];
        
        return $users[$userId] ?? null;
    }
	
    
    
    protected function registerSchema(): void
    {
        // Register types using string names
        $this->registerTypeDefinitions();
        
        // Register queries with string-based type references
        $queries = [
            // Simple query returning a string
            'hello' => [
                'type' => 'String',
                'resolve' => function() {
                    return 'Hello, GraphQL!';
                }
            ],
            
            // Query returning a User object
            'user' => [
                'type' => 'User',  // String reference to registered type
                'args' => [
                    'id' => Type::nonNull('ID')  // Helper for non-null type
                ],
                'resolve' => function($root, $args, $context) {
                    return User::find($args['id']);
                }
            ],
            
            // Query returning list of Users
            'users' => [
                'type' => '[User]',  // List syntax: [Type]
                'args' => [
                    'limit' => 'Int',
                    'offset' => 'Int'
                ],
                'resolve' => function($root, $args, $context) {
                    $limit = $args['limit'] ?? 10;
                    $offset = $args['offset'] ?? 0;
                    return User::all($limit, $offset);
                }
            ],
            
            // Query with non-null list return type
            'activeUsers' => [
                'type' => '[User]!',  // Non-null list of Users
                'resolve' => function() {
                    return User::where('active', true);
                }
            ],
            
            // Query with complex arguments
            'searchPosts' => [
                'type' => '[Post]',
                'args' => [
                    'term' => Type::nonNull('String'),
                    'limit' => 'Int',
                    'status' => 'PostStatus'  // Enum type
                ],
                'resolve' => function($root, $args, $context) {
                    return Post::search($args['term'], $args['limit'] ?? 20);
                }
            ],
            
            // Query with nested field resolvers
            'post' => [
                'type' => 'Post',
                'args' => ['id' => Type::nonNull('ID')],
                'resolve' => function($root, $args) {
                    return Post::find($args['id']);
                }
            ]
        ];
        
        // Register mutations with string-based type references
        $mutations = [
            'createUser' => [
                'type' => 'User',
                'args' => [
                    'name' => Type::nonNull('String'),
                    'email' => Type::nonNull('String'),
                    'password' => Type::nonNull('String')
                ],
                'resolve' => function($root, $args, $context) {
                    if (!isset($context['user']) || $context['user']['role'] !== 'ADMIN') {
                        throw new \Exception('Permission denied');
                    }
                    return User::create($args);
                }
            ],
            
            'updatePost' => [
                'type' => 'Post',
                'args' => [
                    'id' => Type::nonNull('ID'),
                    'title' => 'String',
                    'content' => 'String'
                ],
                'resolve' => function($root, $args, $context) {
                    $post = Post::find($args['id']);
                    if (!$post) {
                        throw new \Exception('Post not found');
                    }
                    
                    $user = $context['user'] ?? null;
                    if (!$user || ($user['id'] != $post['author_id'] && $user['role'] !== 'ADMIN')) {
                        throw new \Exception('Permission denied');
                    }
                    
                    return Post::update($args['id'], $args);
                }
            ],
            
            'deletePost' => [
                'type' => 'Boolean',
                'args' => ['id' => Type::nonNull('ID')],
                'resolve' => function($root, $args, $context) {
                    $post = Post::find($args['id']);
                    if (!$post) return false;
                    
                    $user = $context['user'] ?? null;
                    if (!$user || ($user['id'] != $post['author_id'] && $user['role'] !== 'ADMIN')) {
                        throw new \Exception('Permission denied');
                    }
                    
                    return Post::delete($args['id']);
                }
            ]
        ];
        
        $this->graphql->registerQueries($queries);
        $this->graphql->registerMutations($mutations);
    }
    
    /**
     * Register type definitions using Type class
     */
    private function registerTypeDefinitions(): void
    {
        // Register User type
        Type::register('User', function() {
            return new ObjectType([
                'name' => 'User',
                'fields' => [
                    'id' => Type::nonNull('ID'),
                    'name' => 'String',
                    'email' => 'String',
                    'role' => 'String',
                    'created_at' => 'String',
                    'posts' => [
                        'type' => '[Post]',
                        'resolve' => function($user) {
                            return Post::where('author_id', $user['id']);
                        }
                    ],
                    'fullName' => [
                        'type' => 'String',
                        'resolve' => function($user) {
                            return $user['first_name'] . ' ' . $user['last_name'];
                        }
                    ]
                ]
            ]);
        });
        
        // Register Post type
        Type::register('Post', function() {
            return new ObjectType([
                'name' => 'Post',
                'fields' => [
                    'id' => Type::nonNull('ID'),
                    'title' => Type::nonNull('String'),
                    'content' => 'String',
                    'status' => 'PostStatus',
                    'author_id' => 'Int',
                    'created_at' => 'String',
                    'updated_at' => 'String',
                    'author' => [
                        'type' => 'User',
                        'resolve' => function($post) {
                            return User::find($post['author_id']);
                        }
                    ],
                    'comments' => [
                        'type' => '[Comment]',
                        'args' => [
                            'limit' => 'Int'
                        ],
                        'resolve' => function($post, $args) {
                            return Comment::where('post_id', $post['id'], $args['limit'] ?? 10);
                        }
                    ]
                ]
            ]);
        });
        
        // Register Comment type
        Type::register('Comment', function() {
            return new ObjectType([
                'name' => 'Comment',
                'fields' => [
                    'id' => Type::nonNull('ID'),
                    'content' => Type::nonNull('String'),
                    'author_id' => 'Int',
                    'post_id' => 'Int',
                    'created_at' => 'String',
                    'author' => [
                        'type' => 'User',
                        'resolve' => function($comment) {
                            return User::find($comment['author_id']);
                        }
                    ]
                ]
            ]);
        });
        
        // Register Enum type for post status
        Type::register('PostStatus', function() {
            return new \GraphQL\Type\Definition\EnumType([
                'name' => 'PostStatus',
                'values' => [
                    'DRAFT' => ['value' => 'draft'],
                    'PUBLISHED' => ['value' => 'published'],
                    'ARCHIVED' => ['value' => 'archived']
                ]
            ]);
        });
        
        // Register Input type for mutations
        Type::register('UserInput', function() {
            return new \GraphQL\Type\Definition\InputObjectType([
                'name' => 'UserInput',
                'fields' => [
                    'name' => Type::nonNull('String'),
                    'email' => Type::nonNull('String'),
                    'role' => 'String'
                ]
            ]);
        });
    }
}

