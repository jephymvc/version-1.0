<?php
namespace App\GraphQL\Inputs;

use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\Type;

class PostInput
{
    public function getType()
    {
        return new InputObjectType([
            'name' => 'PostInput',
            'fields' => [
                'title' => Type::nonNull(Type::string()),
                'content' => Type::nonNull(Type::string()),
                'published' => Type::boolean()
            ]
        ]);
    }
}