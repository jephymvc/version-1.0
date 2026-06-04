<?php
namespace App\Core\GraphQL\Shield;

abstract class Rule
{
    abstract public function execute($root, $args, $context, $info);
}