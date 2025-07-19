document.addEventListener( 'DOMContentLoaded', function () {
	var selectButton = document.getElementById( 'rdfi_select_images' );
	var previewDiv = document.getElementById( 'rdfi_preview' );
	var mediaIdsInput = document.getElementById( 'rdfi_media_ids' );
	
	var frame;
	var selectedIds = [];
	
	// Parse initial saved IDs if any
	try {
		selectedIds = JSON.parse( mediaIdsInput.value ) || [];
	} catch {
		selectedIds = [];
	}
	
	selectButton.addEventListener( 'click', function ( e ) {
		e.preventDefault();
		
		if ( frame ) {
			frame.open();
			return;
		}
		
		frame = wp.media( {
			title: 'Select Default Images',
			multiple: true,
			library: {type: 'image'},
			button: {text: 'Use these images'}
		} );
		
		frame.on( 'open', function () {
			const selection = frame.state().get( 'selection' );
			selectedIds.forEach( function ( id ) {
				const attachment = wp.media.attachment( id );
				attachment.fetch();
				selection.add( attachment ? [attachment] : [] );
			} );
		} );
		
		// When the user selects images
		frame.on( 'select', function () {
			const selection = frame.state().get( 'selection' );
			
			// Update selectedIds from scratch (do NOT replace if you want additive behavior)
			selectedIds = selection.map( function ( attachment ) {
				return attachment.id;
			} );
			
			// Save back to input
			mediaIdsInput.value = JSON.stringify( selectedIds );
			
			// Update preview
			previewDiv.innerHTML = '';
			selectedIds.forEach( function ( id ) {
				const attachment = wp.media.attachment( id );
				attachment.fetch().then( function () {
					const thumb = attachment.attributes.sizes?.thumbnail || attachment.attributes;
					const img = document.createElement( 'img' );
					img.src = thumb.url;
					img.style.marginRight = '10px';
					img.style.border = '1px solid #ccc';
					img.height = 150;
					previewDiv.appendChild( img );
				} );
			} );
		} );
		
		frame.open();
	} );
} );
