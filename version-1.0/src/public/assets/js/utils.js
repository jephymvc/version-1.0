const Utils = {};
Utils.colors = [ 
	'#5E50F9', '#6610f2', '#6a008a', '#E91E63', 
	'#f96868', '#f2a654', '#46c35f', '#58d8a3', 
	'#58d8a3', '#57c7d4', '#eeeeee' 
];

Utils.getRandomHexColor = () => {
	return Utils.colors[Math.floor(Math.random()*Utils.colors.length)];
};

Utils.getNameInitialsAvatar = ( firstname, lastname ) => {
	let initials 	= firstname.charAt( 0 ) + '' + lastname.charAt( 0 );
	let avatarBox	= document.createElement( "div" );
	avatarBox.style.width 			= '42px';
	avatarBox.style.height 			= '42px';
	avatarBox.style.borderRadius 	= '50%';
	avatarBox.style.padding 		= '.5rem';
	avatarBox.style.border 			= '2px solid #eee';
	avatarBox.style.paddingTop 		= '0.7rem';
	avatarBox.style.fontSize 		= '1rem';
	avatarBox.style.textAlign 		= 'center';
	avatarBox.style.cursor 			= 'pointer';
	
	avatarBox.style.backgroundColor = Utils.getRandomHexColor();
	avatarBox.textContent = initials ;
	return avatarBox;	
};



Utils.postData = async ( url = "", data = {}, req_headers = {} ) => {
	// Default options are marked with *;
	
	const headers = {};
	if( "Content-Type" in req_headers ){
		headers[ "Content-Type" ] = req_headers[ "Content-Type" ];
	}else{
		headers[ "Content-Type" ] = "application/json";
	}
	
	req_headers_keys = Object.keys(req_headers);
	for( var i = 0; i < req_headers_keys.length; i++ ){
		if( !req_headers_keys[i] in req_headers ){
			headers[ req_headers_keys[i] ] = req_headers[ req_headers_keys[i] ];
		}
	}	
	
	const response = await fetch( url, {
		method: "POST", // *GET, POST, PUT, DELETE, etc.
		mode: "no-cors", // no-cors, *cors, same-origin
		cache: "no-cache", // *default, no-cache, reload, force-cache, only-if-cached
		credentials: "same-origin", // include, *same-origin, omit
		headers:headers,
		redirect: "follow", // manual, *follow, error
		referrerPolicy: "no-referrer", // no-referrer, *no-referrer-when-downgrade, origin, origin-when-cross-origin, same-origin, strict-origin, strict-origin-when-cross-origin, unsafe-url
		body: JSON.stringify( data ), // body data type must match "Content-Type" header
	});
	
	return await response.json(); // parses JSON response into native JavaScript objects

}



Utils.postMultipartData = async ( url = "", data ) => {
	const response 		= await fetch( url, {
		method: "POST",
		body: data ,
	});	
	return await response.json();
}


Utils.uploadFile = async ( url, fieldId ) => {
	const fileField = document.getElementById( fieldId );
	const formData 	= new FormData();
	formData.append( fileField.getAttribute( "name" ), fileField.files[0] );	
	try {
		
		const response = await fetch( url, {
			method: "PUT",
			body: formData,
		});		
		return await response.json();
		
	} catch ( error ) {
		console.error( "Error:", error );
	}

}

Utils.submitForm =(  formId, callBack, processing  ) => {
	let formObj 	= document.querySelector( formId );
	let success 	= callBack || function( resp ){};
	let loading 	= processing || function(){};
	let response	= {};
	
	formObj.setAttribute( "enctype", "multipart/form-data" );			
	formObj.addEventListener( "submit",  async ( evt ) => {
		evt.preventDefault();
		let formFields 	= [ ...document.querySelector( formId ).querySelectorAll( "input, select, textarea" ) ];	
		let formdata 		= new FormData();	
		for( var i = 0; i < formFields.length; i++ ){
			if( formFields[i].hasAttribute( "name" ) ){				
				if( formFields[i].type == 'file' ){	
					if( formFields[i].hasAttribute( "multiple" ) ){
						
						for( let file of formFields[i].files ){
							formdata.append( formFields[i].name, file );
						}
						
					}else{
						formdata.append( formFields[i].name, formFields[i].files[0] );
					}
					
				}else{
					formdata.append( formFields[i].name, formFields[i].value );
					
				}			
			}
		}
		
		loading();
		
		//	console.log( formObj.getAttribute( 'action' ) );
		
		if( Utils.validateForm( formId ) ){
			response = await Utils.postMultipartData( formObj.getAttribute( 'action' ), formdata );
			success( response );
		}
		
	} );
}


Utils.submitForms = ( formClass, callBack, processing ) => {
	const forms =  document.querySelectorAll( formClass );
	[...forms].forEach( ( form ) => {
		Utils.submitForm( form, callBack, processing );
	} );
}


Utils.submitPlainFormData = (  formId, callBack, processing  ) => {
	let formObj 	= document.querySelector( formId );
	let success 	= callBack || function( resp ){};
	let loading 	= processing || function(){};
	let response	= {};
	
	formObj.setAttribute( "enctype", "multipart/form-data" );			
	formObj.addEventListener( "submit",  async ( evt ) => {
		evt.preventDefault();
		let formFields 	= [ ...document.querySelector( formId ).querySelectorAll( "input, select, textarea" ) ];	
		let urlEncodedData  = formFields.map( ( item ) => {
			return item.getAttribute( 'name' ) + '=' + item.value;
		}).join( '&' );	
		
		loading();	
		
		if( Utils.validateForm( formId ) ){
			response = await Utils.postMultipartData( formObj.getAttribute( 'action' ), urlEncodedData );
			success( response );
		}else{
			setTimeout( () => {
				hideSpinnerModal();
			}, 1000 );
			
		}		
	} );
}


Utils.submitPageData = async ( url = "", data = {}, callBack, processing  ) => {
	
	let dataObj 	= data || {};
	let success 	= callBack || function( resp ){};
	let loading 	= processing || function(){};
	let response	= {};
	
	let formdata 	= new FormData();
	const dataFields = Object.keys( dataObj );
	for( var i = 0; i < dataFields.length; i++ ){
		formdata.append( dataFields[ i ], dataObj[ dataFields[ i ] ]  );
	}
	
	response = await Utils.postMultipartData( url, formdata );
	success( response );
	
}

Utils.imageUploadPreview = function( fileField, imageHolder, successs, processing ){
	
	let successs_callback   = successs || function(){};
	let processing_callback = processing || function(){};
	
	document.querySelector(fileField).addEventListener('change', function () {	
		processing_callback();
		if (typeof (FileReader) != "undefined") {		 
			var image_holder = document.querySelector(imageHolder);
			image_holder.innerHTML = '';		 
			var reader = new FileReader();
			reader.onload = function (e) {						
				image_holder.innerHTML = '<img src="' + e.target.result + '" style="width:100%;" />';						
			}
			image_holder.style.display = "block" ;
			reader.readAsDataURL( document.querySelector(fileField).files[0] );
			successs_callback();
		} else {
			image_holder.innerHTML  = "This browser does not support FileReader.";					
		}
	});		
	
};

Utils.multipleImageUploadPreview = function( fileField, imageHolder, successs, processing ){
	
	let successs_callback   = successs || function(){};
	let processing_callback = processing || function(){};
	
	document.querySelector( fileField ).addEventListener('change',  async function () {	
		processing_callback();
		
		if (typeof (FileReader) != "undefined") {	
		
			var image_holder 					= document.querySelector( imageHolder );
			image_holder.style.display 			= "grid" ;	
			image_holder.style.gridTemplateColumns 			= "1fr 1fr 1fr 1fr" ;	
			image_holder.style.gridGap 			= "14px" ;	
			image_holder.innerHTML 				= '';			
			
			for( var file of document.querySelector( fileField ).files ){
				var reader 					= new FileReader();
				await reader.readAsDataURL( file );	
				reader.onload = await function (e) {	
					let thumb 				= document.createElement( "DIV" );
					thumb.style.width 		= "100%";
					thumb.style.height 		= "210px";
					thumb.style.borderRadius 		= "7px";
					thumb.style.border 				= "7px solid #eee";
					thumb.style.backgroundColor 	= "#eeeeee";
					thumb.style.padding 			= "14px";
					console.log( e.target.result );
					thumb.style.backgroundImage 	= "url(" + e.target.result + ")";
					thumb.style.backgroundSize 		= 'cover';
					thumb.style.backgroundPosition 	= 'center';
					
					//	let preview 			= document.createElement( "IMG" );
					//	preview.src 			= e.target.result;
					
					image_holder.appendChild( thumb );											
				}
				
				
			}
			
			successs_callback();
			
		} else {
			image_holder.innerHTML  = "This browser does not support FileReader.";					
		}
	});		
	
};


Utils.fetchData = async ( url = "", callBack, processing  ) => {
	
	let success 	= callBack || function( resp ){};
	let loading 	= processing || function(){};
	loading();
	const response 	= await fetch( url );	
	const data  	= await response.json();
	success( data );	
}

Utils.uploadFile = async function upload( formId, headers = {}, callBack, processing ) {
	
	const form 		= document.querySelector( formId );	
	const fields 	= form.querySelectorAll( 'input, select, textarea' );
	
	form.addEventListener( "submit", async ( evt ) => {
		evt.preventDefault();
		const url 		= form.getAttribute( 'action' );
		const formData 	= new FormData();
		
		for( var i = 0; i < fields.length; i++ ){
			const field = fields[i];
			if( field.type == 'file' ){
				formData.append( field.getAttribute( "name" ), field.files[ 0 ] );		
			}else{
				formData.append( field.getAttribute( "name" ), field.value );		
			}			
		}
		
		const callback 	= callBack || function( response ){};
		try {
			
			const controller 	= new AbortController();
			const signal 		= controller.signal;	
			const response 		= await fetch( url, {
				method: "PUT",
				headers: headers,
				body: formData,
				signal
			});
			
			processing();
			window.addEventListener( 'offline', () => {
				controller.abort();
			} );		
			
			const result = await response.json();
			console.log( "Success: ", result );
			callback( result );
			
		} catch (error) {
			console.error( "Error: ", error);
		}
	} );
	
	
}


Utils.uploadMultipleFiles = async function upload( formId, headers = {}, callBack, processing ) {
	
	const form 		= document.querySelector( formId );	
	const fields 	= form.querySelectorAll( 'input, select, textarea' );
	
	form.addEventListener( "submit", async ( evt ) => {
		evt.preventDefault();
		const url 		= form.getAttribute( 'action' );
		const formData 	= new FormData();
		
		for( var i = 0; i < fields.length; i++ ){
			const field = fields[i];
			if( field.type == 'file' ){
				//	formData.append( field.getAttribute( "name" ), field.files );	
				for( var j = 0; j < field.files.length; j++ ){					
					formData.append( field.getAttribute( "name" ) + '_' + j, field.files[ j ] );				
				}	
			}else{
				formData.append( field.getAttribute( "name" ), field.value );		
			}			
		}
				
		const callback 	= callBack || function( response ){};
		try {
			
			const controller 	= new AbortController();
			const signal 		= controller.signal;	
			const response 		= await fetch( url, {
				method: "PUT",
				headers: headers,
				body: formData,
				signal
			});
			
			processing();
			window.addEventListener( 'offline', () => {
				controller.abort();
			} );		
			
			const result = await response.json();
			console.log( "Success: ", result );
			callback( result );
			
		} catch (error) {
			console.error( "Error: ", error);
		}
	} );
	
	
}


Utils.validateForm = ( formId ) => {
	let errorCount 	= 0;
	let form 		= document.querySelector( formId );
	let fields 		= form.querySelectorAll( "input, select, textarea" );
	for( var i = 0; i < fields.length; i++ )
	{
		let field 		= fields[i];
		let errorSmall 	= document.createElement( "small" );
		if( field.type != 'hidden' ){
			let fieldParent = field.parentNode;			
			if( field.hasAttribute( "data-required" ) && field.value == "" ){
				fieldParent.style.position = "relative";
				
				errorSmall.style.display 	= "inline-block";
				errorSmall.style.padding 	= "0.2rem 1rem";
				errorSmall.style.borderRadius 	= "7px";
				errorSmall.style.color 		= "#ffffff";
				errorSmall.style.backgroundColor 	= "#ff0000";
				errorSmall.style.position 	= "absolute";
				errorSmall.style.top 		= ".7rem";
				errorSmall.style.right 		= "1rem";
				errorSmall.style.fontSize 	= "9px";
				errorSmall.style.zIndex 	= "100";
				let errorMsg = field.hasAttribute( "data-error" ) ? field.getAttribute( "data-error" ) : "Required";
				errorSmall.appendChild( document.createTextNode( errorMsg ) );
				field.parentNode.appendChild( errorSmall );
				field.style.border 			= "1px solid #f00";
				errorCount++;
				
			}
			
			
			field.addEventListener( "focus", ( evt ) => {
				if( fieldParent.contains( errorSmall ) ){
					fieldParent.removeChild( errorSmall );
				}			
				field.style.border 			= "1px solid #eee";			
			} );
			
		}else{
			if( field.hasAttribute( "data-required" ) && field.hasAttribute( "data-target" ) && field.value == "" ){
				let targetField 		= document.querySelector( field.getAttribute( "data-target" ) );
				let targetFieldParent 	= targetField.parentNode;
				targetFieldParent.style.position = "relative";				
				errorSmall.style.display 	= "inline-block";
				errorSmall.style.padding 	= "0.2rem 1rem";
				errorSmall.style.borderRadius 	= "7px";
				errorSmall.style.color 		= "#ffffff";
				errorSmall.style.backgroundColor 	= "#ff0000";
				errorSmall.style.position 	= "absolute";
				errorSmall.style.top 		= ".7rem";
				errorSmall.style.right 		= "1rem";
				errorSmall.style.fontSize 	= "9px";
				errorSmall.style.zIndex 	= "100";
				let errorMsg = field.hasAttribute( "data-error" ) ? field.getAttribute( "data-error" ) : "Required";
				errorSmall.appendChild( document.createTextNode( errorMsg ) );
				targetFieldParent.appendChild( errorSmall );
				targetField.style.border 	= "1px solid #f00";
				errorCount++;	

				
				targetField.addEventListener( "focus", ( evt ) => {
					if( targetFieldParent.contains( errorSmall ) ){
						targetFieldParent.removeChild( errorSmall );
					}			
					targetField.style.border 			= "1px solid #eee";			
				} );

				
			}
		}
		
		
		
	}
	
	return errorCount < 1;
	
};


Utils.convertToSlug = function(Text) {
  return Text.toLowerCase()
    .replace(/[^\w ]+/g, "")
    .replace(/ +/g, "-");
}

Utils.postPageMultipartData = async ( url = "", data  ) => {
	let response;	
	try {
		response = await fetch( url, {
			method: "POST",
			headers: {
				"Content-Type": "application/x-www-form-urlencoded",
			},
			body: data,
		});
		response = await response.json();
		
	} catch ( error ) {
		console.error( "Error:", error );
	}
	return response;
}


Utils.postPageData = async ( url = "", data = {}, callBack, processing  ) => {
	
	let pagedata 	= "";
	let objectKeys 	= Object.keys( data );
	
	for( item of objectKeys ){
		if( pagedata == "" ){
			pagedata = item + '=' + data[item];
		}else{
			pagedata += "&" + item + '=' + data[item];
		}		
	}
	
	let success 	= callBack || function( resp ){};
	let loading 	= processing || function(){};
	let response	= {};	
	loading();	
	response 		= await Utils.postPageMultipartData( url, pagedata );
	await success( response );
	
	
}


Date.prototype.getWeek = function() {
	var date = new Date(this.getTime());
	date.setHours(0, 0, 0, 0);
	// Thursday in current week decides the year.
	date.setDate(date.getDate() + 3 - (date.getDay() + 6) % 7);
	// January 4 is always in week 1.
	var week1 = new Date(date.getFullYear(), 0, 4);
	// Adjust to Thursday in week 1 and count number of weeks from date to week1.
	return 1 + Math.round(((date.getTime() - week1.getTime()) / 86400000
						- 3 + (week1.getDay() + 6) % 7) / 7);
}

Date.prototype.getWeekYear = function() {
	var date = new Date(this.getTime());
	date.setDate(date.getDate() + 3 - (date.getDay() + 6) % 7);
	return date.getFullYear();
}

Date.prototype.addMinutes = function( minutes, start_date ) {
    var date = new Date( start_date || this.valueOf() );
    date.setMinutes(date.getMinutes() + minutes );
    return date;
}

Date.prototype.addHours = function( hours, start_date ) {
    var date = new Date( start_date || this.valueOf() );
    date.setHours(date.getHours() + hours );
    return date;
}

Date.prototype.addDays = function( days, start_date ) {
    var date = new Date( start_date || this.valueOf() );
    date.setDate(date.getDate() + days );
    return date;
}


Date.prototype.addWeeks = function( weeks, start_date ) {
    let date 		= new Date( start_date || this.valueOf() );
    let currWeek 	= date.getWeek();
    return currWeek + weeks;
}


Date.prototype.addMonths = function( months, start_date ) {
    var date = new Date( start_date || this.valueOf() );
    date.setMonth( date.getMonth() + months );
    return date;
}




Utils.number_format = ( number, decimals, dec_point, thousands_sep ) => {	
	var n = !isFinite(+number) ? 0 : +number, 
		prec = !isFinite(+decimals) ? 0 : Math.abs(decimals),
		sep = (typeof thousands_sep === 'undefined') ? ',' : thousands_sep,
		dec = (typeof dec_point === 'undefined') ? '.' : dec_point,
		toFixedFix = function (n, prec) {
			var k = Math.pow(10, prec);
			return Math.round(n * k) / k;
		},
		s = (prec ? toFixedFix(n, prec) : Math.round(n)).toString().split('.');
	if (s[0].length > 3) {
		s[0] = s[0].replace(/\B(?=(?:\d{3})+(?!\d))/g, sep);
	}
	if ((s[1] || '').length < prec) {
		s[1] = s[1] || '';
		s[1] += new Array(prec - s[1].length + 1).join('0');
	}
	return s.join(dec);
};

Utils.setCookie = function (cname, cvalue, expDays) {
	var d 		= new Date(), exdays = expDays || 4;
	d.setTime(d.getTime() + (exdays*24*60*60*1000));
	var expires = "expires="+ d.toUTCString();
	document.cookie = cname + "=" + cvalue + ";" + expires + ";path=/";
};

Utils.clearCookie = (cname) => {
	Utils.setCookie( cname, "", "Thu, 09 May 1979 00:00:00" );
};

Utils.getCookie = (cname) => {
	var name 	= cname + "=";
	var decodedCookie = decodeURIComponent(document.cookie);
	var ca = decodedCookie.split(';');
	for(var i = 0; i <ca.length; i++) {
		var c = ca[i];
		while (c.charAt(0) == ' ') {
			c = c.substring(1);
		}
		if (c.indexOf(name) == 0) {
			return c.substring(name.length, c.length);
		}
	}
	return "";
};

Utils.cookieExists = ( targetCookie ) => {
	var cookieName = Utils.getCookie(targetCookie);
	if (cookieName != "") {
		return true;
	} else {
		return false;
	}
};

Utils.spyPageBottom 	= ( parentElement, callback ) => {
	let max_scroll 		= parseInt( document.querySelector( parentElement ).scrollHeight ) - 210;
	let callbackFunc 	= callback || function(){};	
	document.addEventListener( "scroll", ( evt ) => {
		let scrollHeight = window.pageYOffset;	
		if( parseInt( scrollHeight ) > max_scroll ){
			callbackFunc();
		}		
	});	
}

const getElementOffsets = (el) => {		
	var rect = el.getBoundingClientRect(),
	scrollLeft = window.pageXOffset || document.documentElement.scrollLeft,
	scrollTop = window.pageYOffset || document.documentElement.scrollTop;
	return { 
		top: rect.top + scrollTop, 
		left: rect.left + scrollLeft, 
		width: rect.width, 
		height: rect.height, 
	};	
}

const easeScroll = ( targetElement, attribute_name ) => {
	let clickableBtn = document.querySelector( targetElement );
	clickableBtn.addEventListener("click", ( evt ) => {
		evt.preventDefault();
		let targetSection 	= document.querySelector("#" + clickableBtn.getAttribute(attribute_name));
		let sectionOffsets 	= getElementOffsets(targetSection);
		window.scroll({
			top: sectionOffsets.top,
			left: 0,
			behavior: 'smooth'
		});
	});
}


Utils.easeScrolls = ( targetElementClassName, attribute_name ) => {
	let clickableBtns = document.querySelectorAll( targetElementClassName );
	[...clickableBtns].forEach( ( clickableBtn, index ) => {
		clickableBtn.addEventListener("click", ( evt ) => {
			evt.preventDefault();
			let targetSection 	= document.querySelector("#" + clickableBtn.getAttribute(attribute_name));
			let sectionOffsets 	= getElementOffsets(targetSection);
			window.scroll({
				top: sectionOffsets.top,
				left: 0,
				behavior: 'smooth'
			});
		});
	} );	
}

Utils.scrollSection = () => {
	const pathData = location.pathname.split( "/" ).filter( ( it ) => it !== "" );
	if( pathData.length > 0 ){
		let targetSection 	= document.querySelector( "#" + pathData[ pathData.length - 1 ] );
		let sectionOffsets 	= getElementOffsets( targetSection );
		window.scroll( {
			top: sectionOffsets.top,
			left: 0,
			behavior: 'smooth'
		} );
	}
	
}




