(function( $ ) {
	'use strict';

	/**
	 * All of the code for your public-facing JavaScript source
	 * should reside in this file.
	 *
	 * Note: It has been assumed you will write jQuery code here, so the
	 * $ function reference has been prepared for usage within the scope
	 * of this function.
	 *
	 * This enables you to define handlers, for when the DOM is ready:
	 *
	 * $(function() {
	 *
	 * });
	 *
	 * When the window is loaded:
	 *
	 * $( window ).load(function() {
	 *
	 * });
	 *
	 * ...and/or other possibilities.
	 *
	 * Ideally, it is not considered best practise to attach more than a
	 * single DOM-ready or window-load handler for a particular page.
	 * Although scripts in the WordPress core, Plugins and Themes may be
	 * practising this, we should strive to set a better example in our own work.
	 */

	let app = {
		init : function() {
			this.setup();
			this.bindEvents();
			this.showHideRelevantFields();
			this.showHideMpStatusFileds();
		},

		setup : function() {
			this.document 			= $( document );
			this.bank_type  		= '.mangopay-type';
			this.dependent_class	= '.bank-type';
			this.user_mp_status 	= '#mangopay_user_mp_status';
			this.user_business_type = '#mangopay_user_business_type';
		},

		bindEvents : function() {
			this.document.on( 'change', this.bank_type, this.showHideRelevantFields.bind( this ) );
			this.document.on( 'change', this.user_mp_status, this.showHideMpStatusFileds.bind( this ) );
		},

		showHideRelevantFields : function( event ) {
			let type;

			if( undefined == event ) {
				if( ! $( this.bank_type ).length ) return;
				type = $( this.bank_type ).val();
			} else {
				type = $( event.currentTarget ).val();
			}

			let dependent_field = {
				show : this.dependent_class + '.bank-type-' + type.toLowerCase(),
				hide : this.dependent_class + ':not(.bank-type-' + type.toLowerCase() + ')',
			};

			$( dependent_field.show ).each( function( key, value ) {
				$( value ).show()
				.prev( 'label' ).show()
				.prev( 'p' ).show(); 
			} );

			$( dependent_field.hide ).each( function( key, value ) {
				$( value ).hide()
				.prev( 'label' ).hide()
				.prev( 'p' ).hide();
			} );
		},

		showHideMpStatusFileds : function( event ) {
			let user_type;

			if( undefined == event ) {
				if( ! $( this.user_mp_status ).length ) return;
				user_type = $( this.user_mp_status ).val();
			} else {
				user_type = $( event.currentTarget ).val();
			}

			if( 'individual' === user_type ) {
				$( this.user_business_type ).hide()
				.prev( 'label' ).hide()
				.prev( 'p' ).hide();
			} else {
				$( this.user_business_type ).show()
				.prev( 'label' ).show()
				.prev( 'p' ).show();
			}
		},
	};

	$( app.init.bind( app ) );

})( jQuery );
