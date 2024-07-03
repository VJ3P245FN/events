
/*
	* KM-Tabs
	*
	* (c) 2019-2023 Kreatura Media, AgeraWeb, George K., John G.
	*
*/



jQuery( document ).ready( function( $ ){

	$( document ).on( 'click.km-tabs', '.km-tabs-list > *:not(.kmw-disabled, .kmw-unselectable, kmw-menutitle)', function(){

		var $clicked = $(this),
			$parent = $clicked.parent(),
			$modal = $parent.closest( '.kmw-modal' ),
			menuText = $clicked.find( 'kmw-menutext' ).text(),
			$target = $( $parent.data( 'target' ) ),
			disableAutoRename = $parent.data( 'disableAutoRename' ),
			modalSelector = '[data-kmw-uid="' + $(this).closest('.kmw-modal-container').data('kmwUid') + '"] ',
			selector = modalSelector + 'kmw-menuitem, ' + modalSelector + '.kmw-menuitem',
			index = $clicked.index( selector );

		var dataTabTarget = $clicked.data( 'tab-target' ) || '',
			$tabTarget = $target.find('[data-tab="' + dataTabTarget + '"]' );

		if( !$clicked.hasClass( 'kmw-active' ) ){

			$clicked.siblings().removeClass( 'kmw-active' );
			$clicked.addClass( 'kmw-active' );

			if( dataTabTarget && $tabTarget.length ) {
				$tabTarget.siblings().removeClass( 'kmw-active' );
				$tabTarget.addClass( 'kmw-active' );
			} else {
				$target.children().removeClass( 'kmw-active' );
				$target.children().eq(index).addClass( 'kmw-active' );
			}
		}

		if( typeof disableAutoRename === 'undefined' ){
			$modal.find( 'kmw-h1.kmw-modal-title' ).text( menuText );
		}
	});
});
