( function ( wp ) {
	var el = wp.element.createElement;
	var SSR = wp.serverSideRender;
	var names = [ 'condoclock/records', 'condoclock/notices', 'condoclock/request-access' ];
	names.forEach( function ( name ) {
		if ( ! wp.blocks.getBlockType( name ) && window.condoclockBlocks && window.condoclockBlocks.indexOf( name ) > -1 ) {
			wp.blocks.registerBlockType( name, {
				edit: function () {
					return el( 'div', wp.blockEditor.useBlockProps(), el( SSR, { block: name } ) );
				},
				save: function () {
					return null;
				}
			} );
		}
	} );
} )( window.wp );
