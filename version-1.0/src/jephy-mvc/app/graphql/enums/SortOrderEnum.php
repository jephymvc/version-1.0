<?php
namespace App\GraphQL\Enums;

use GraphQL\Type\Definition\EnumType;

class SortOrderEnum
{
    public function getType()
    {
        return new EnumType( [
            'name' 		=> 'SortOrder',
            'values' 	=> [
                'ASC' 	=> [ 'value' => 'asc' ],
                'DESC' 	=> [ 'value' => 'desc' ]
            ]
        ] );
    }
}