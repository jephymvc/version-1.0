<?php
use App\Core\Router;

Router::get( '/', 'HomeController@index');
#	Router::get( '/home', 'HomeController@index');
Router::get( '/documentation', 'HomeController@documentation');
Router::get( '/documentation/{target}', 'HomeController@documentation');

Router::get( '/home', function(){
	return view( 'home/index.tpl' );
});

Router::post( '/contact', 'HomeController@handleContactMessage' );

Router::get( '/media-mixer', 'HomeController@mediaMixer' );


// GraphQL Routes
Router::get( '/graphql/test', 'GraphQLController@test');
Router::get( '/graphql/debug', 'GraphQLController@debug');
Router::post( '/graphql/debug', 'GraphQLController@debug');

Router::post( '/graphql', 'GraphQLController@handle');
Router::get( '/graphql/playground', 'GraphQLController@playground');

// In your routes configuration
Router::get( '/graphql', 'GraphQLController@handle' );
Router::post( '/graphql/save', 'App\Controllers\GraphQLController@saveQuery' );



?>