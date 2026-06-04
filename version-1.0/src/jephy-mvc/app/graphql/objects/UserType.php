<?php
namespace App\GraphQL\Objects;

use App\Core\GraphQL\BaseType;

class UserType extends BaseType
{
    protected function fields(): array
    {
        return [
            'id' => 'id!',
            'username' => 'string!',
            'email' => 'string!',
            'created_at' => 'string'
        ];
    }

    protected function description(): ?string
    {
        return 'A user in the system';
    }
}