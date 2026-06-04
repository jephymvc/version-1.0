<?php
use App\Core\Framework;
$globalData = Framework::getGlobalDataHook();
return [
    'host' 			=> 'localhost', // Confirm this from your host
    'smtp_auth' 	=> false,
    'username' 		=> $globalData->get( 'mail.address.from' ),
    'password' 		=> $globalData->get( 'mail.address.password' ),
    'encryption' 	=> $globalData->get( 'mail.encryption' ), 
    'tls_port' 		=> $globalData->get( 'mail.port.tls' ), 
    'ssl_port' 		=> $globalData->get( 'mail.port.ssl' ),     
    'from' 			=> [
        'address' 	=> $globalData->get( 'mail.address.from' ),
        'name' 		=> $globalData->get( 'mail.address.name' )
    ],
    'is_html' => true,
    'charset' => 'UTF-8'
];