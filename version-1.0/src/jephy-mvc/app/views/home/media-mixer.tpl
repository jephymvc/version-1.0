<!DOCTYPE HTML>
<html>
<head>
	<title>Test Page</title>
	<style>
		#wrapper{
			display: block;
			margin:7rem 10rem;
			border: 1px solid #eee;
			padding: 3rem;
		}
		
		#wrapper form div{
			display: block;	
			margin-bottom: 1rem;	
		}
		
		#wrapper form div input, #wrapper form div textarea, #wrapper form div select{
			display: block;
			width: 100%;
			border: 1px solid #eee;
			padding: 1rem;			
		}
		
		#submit-btn{
			display: inline-block;
			border: 1px solid #eee;
			padding: 1rem 2rem;	
			background-color: #333;
			color: #fff	
		}
		
		.hide{
			display: none !important;
		}
		
		.show{
			display: block !important;
		}
		.horizontal-flex{
			display:flex; justify-content: space-between, align-items: center 
		}
	</style>
</head>
<body>
	<div id="wrapper">
	
		<!--
		<form id="test-form" action="http://localhost:3770/create/media" enctype="multipart/form-data">		
		-->	
		
		<form id="test-form" action="https://media.vibbers.com/create/media" enctype="multipart/form-data">		
			
			<div>
				<input type="text" id="audio-slug" name="audio-slug" placeholder="Audio slug" />
			</div>
			
			
			
			<small>Upload video</small>
			<div>
				<input id="video" type="file" name="video" multiple />
				
			</div>
			
			<div>
				
				<input type="range" id="volume" name="volume" min="0" max="1" step="0.1" value="0.7" list="volume-markers" placeholder="Volume" />
				<datalist id="volume-markers">
				  <option value="0">0</option>
				  <option value="0.1"></option>
				  <option value="0.2">2</option>
				  <option value="0.3"></option>
				  <option value="0.4">4</option>
				  <option value="0.5"></option>
				  <option value="0.6">6</option>
				  <option value="0.7"></option>
				  <option value="0.8">8</option>
				  <option value="0.9"></option>
				  <option value="1.0">10</option>
				</datalist>
			</div>
			
			<div>
				<select type="text" id="action_type" name="action_type">
					<option value="">Select Mix Action</option>
					<option value="mix">Mix</option>
					<option value="duck">Duck</option>
					<option value="mute">Mute</option>
					<option value="replace">Replace</option>
				</select>
			</div>
			
			<div id="loading-status" class="hide">
				<em>Posting ...</em>
			</div>
			
			<button id="submit-btn" type="submit">
				POST/MIX
			</button>
			
		</form>
	
	</div>
	
	
<script>


const postMultipartData = async ( url = "", data = {}, token ="", method = "POST" ) => {
	let responseData = {
		loading: true,
		data: null
	};
	
	document.querySelector( "#loading-status" ).classList.add( "show" );
	document.querySelector( "#loading-status" ).classList.remove( "hide" );	
	
	//	let response 		= await fetch( url, {
	//		headers: {
	//			"Authorization": "Bearer " + token
	//		},
	//		method: method,
	//		body: data ,
	//	});	
	
	let response 		= await fetch( url, {
		headers: {
			"Authorization": "Bearer " + token
		},
		method: method,
		body: data ,
	});	
	
	response = await response.json();
	responseData = {
		loading: response != null ? false: true,
		data: response
	};
	
	
	if ( responseData.loading === false ){
		document.querySelector( "#loading-status" ).classList.remove( "show" );
		document.querySelector( "#loading-status" ).classList.add( "hide" );
	}
	return responseData;
}


const testForm 	= document.querySelector( "#test-form" );
let businessId 	= 'fbc71306-54ef-4cea-8bc6-331012afb03d';



testForm.addEventListener( "submit", async ( evt ) => {

	evt.preventDefault();
	const files 			= document.querySelector( "#video" ).files;
	const audioSlug 	= document.querySelector( "#audio-slug" ).value;
	const volume 		= document.querySelector( "#volume" ).value;
	const action_type 	= document.querySelector( "#action_type" ).value;
	let formData 		= new FormData();
	let response;

	console.log( files );	
	console.log( "Request started ..." );
	
	formData.append( 'audio_slug', audioSlug );
	formData.append( 'action_type', action_type );
	formData.append( 'volume', volume );
	
	for( var i = 0; i < files.length; i++ ){
		const file = files[i];
		formData.append( i+'__slide', file  );
	}	
	
	let token 	= "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VySWQiOiIwYWQxMjlhMy0wMTI2LTQwNzQtYTFhNC0wMWJjZTIzZDc5Y2MiLCJ1c2VyUGVybWlzc2lvbiI6InJlYWQ6YWRtaW4iLCJ1c2VyUm9sZUlkIjoiQURNSU4iLCJmaXJzdG5hbWUiOiJBZm9sYWJpIiwibGFzdG5hbWUiOiJPbG9ydW50b2xhIiwidXNlcm5hbWUiOiJhZm9sYWJpb2xvcnVudG9sYTEyMSIsImVtYWlsIjoiYWZvbGFiaS5vbG9ydW50b2xhQGdtYWlsLmNvbSIsInBlcm1pc3Npb24iOiJyZWFkOmFkbWluIiwiaWF0IjoxNzc0NTAyNzg5LCJleHAiOjE3NzU3MTIzODl9.oY7XBVhtk6nYQBV-K8U76r_Dol2vS4RqDULz3We9yaY";
	response 	= await postMultipartData( testForm.getAttribute( "action" ), formData, token );
	console.log( response );
	
} );

</script>
	
</body>
</html>