<?php
namespace App\Permissions;

use App\Core\GraphQL\Shield\Shield;
use App\Core\GraphQL\Shield\Rules;

class Permissions
{
    public static function define()
    {
        return new Shield([
            // Public queries
            'Query.publicInfo' => Shield::rule(function() {
                return true;
            }),

            // Protected queries
            'Query.users' => Rules::isAuthenticated(),
            'Query.user' => Rules::isAuthenticated(),
            'Query.posts' => Rules::isAuthenticated(),
            'Query.post' => Rules::isAuthenticated(),

            // Role-based permissions
            'Query.adminData' => Shield::and(
                Rules::isAuthenticated(),
                Rules::hasRole('ADMIN')
            ),

            // Mutations with complex rules
            'Mutation.createPost' => Shield::and(
                Rules::isAuthenticated(),
                Shield::or(
                    Rules::hasRole('ADMIN'),
                    Rules::hasPermission('post:create')
                )
            )->error('You need to be an admin or have post:create permission'),

            'Mutation.updatePost' => Shield::and(
                Rules::isAuthenticated(),
                Shield::or(
                    Rules::hasRole('ADMIN'),
                    Shield::and(
                        Rules::hasPermission('post:update'),
                        Rules::isOwner('author_id')
                    )
                )
            ),

            'Mutation.deletePost' => Shield::and(
                Rules::isAuthenticated(),
                Shield::or(
                    Rules::hasRole('ADMIN'),
                    Rules::isOwner('author_id')
                )
            ),

            // Field-level permissions
            'User.email' => Shield::or(
                Rules::hasRole('ADMIN'),
                Rules::isOwner()
            ),

            // Wildcard rule for everything else
            '*' => Shield::or(
                Rules::isAuthenticated(),
                Rules::hasRole('ADMIN')
            )
        ])->fallback(
            Shield::rule(function() {
                return false;
            }),
            'Access denied. Please authenticate.'
        );
    }
}