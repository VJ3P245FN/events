if ( typeof ( window.UM ) !== 'object' ) {
	window.UM = {};
}

if ( typeof ( UM.common ) !== 'object' ) {
	UM.common = {};
}

UM.common = {
	tipsy: {
		init: function () {
			if ( 'function' === typeof( jQuery.fn.tipsy ) ) {
				jQuery('.um-tip-n').tipsy({gravity: 'n', opacity: 1, live: 'a.live', offset: 3 });
				jQuery('.um-tip-w').tipsy({gravity: 'w', opacity: 1, live: 'a.live', offset: 3 });
				jQuery('.um-tip-e').tipsy({gravity: 'e', opacity: 1, live: 'a.live', offset: 3 });
				jQuery('.um-tip-s').tipsy({gravity: 's', opacity: 1, live: 'a.live', offset: 3 });
			}
		},
		hide: function () {
			if ( 'function' === typeof( jQuery.fn.tipsy ) ) {
				jQuery('.um-tip-n').tipsy('hide');
				jQuery('.um-tip-w').tipsy('hide');
				jQuery('.um-tip-e').tipsy('hide');
				jQuery('.um-tip-s').tipsy('hide');
				jQuery('.um .tipsy').remove();
			}
		}
	},
	datetimePicker: {
		init: function () {
			jQuery('.um-datepicker:not(.picker__input)').each(function(){
				elem = jQuery(this);

				if ( typeof elem.attr('data-disabled_weekdays') != 'undefined' && elem.attr('data-disabled_weekdays') != '' ) {
					var disable = JSON.parse( elem.attr('data-disabled_weekdays') );
				} else {
					var disable = false;
				}

				var years_n = null;
				if ( typeof elem.attr('data-years') != 'undefined' ) {
					years_n = elem.attr('data-years');
				}

				var minRange = elem.attr('data-date_min');
				var maxRange = elem.attr('data-date_max');

				var minSplit = [], maxSplit = [];
				if ( typeof minRange != 'undefined' ) {
					minSplit = minRange.split(",");
				}
				if ( typeof maxRange != 'undefined' ) {
					maxSplit = maxRange.split(",");
				}

				var min = minSplit.length ? new Date(minSplit) : null;
				var max = minSplit.length ? new Date(maxSplit) : null;

				// fix min date for safari
				if ( min && min.toString() == 'Invalid Date' && minSplit.length == 3 ) {
					var minDateString = minSplit[1] + '/' + minSplit[2] + '/' + minSplit[0];
					min = new Date(Date.parse(minDateString));
				}

				// fix max date for safari
				if ( max && max.toString() == 'Invalid Date' && maxSplit.length == 3 ) {
					var maxDateString = maxSplit[1] + '/' + maxSplit[2] + '/' + maxSplit[0];
					max = new Date(Date.parse(maxDateString));
				}

				var data = {
					disable: disable,
					format: elem.attr( 'data-format' ),
					formatSubmit: 'yyyy/mm/dd',
					hiddenName: true,
					onOpen: function() { elem.blur(); },
					onClose: function() { elem.blur(); }
				};

				if ( years_n !== null ) {
					data.selectYears = years_n;
				}

				if ( min !== null ) {
					data.min = min;
				}

				if ( max !== null ) {
					data.max = max;
				}

				elem.pickadate( data );
			});

			jQuery('.um-timepicker:not(.picker__input)').each(function(){
				elem = jQuery(this);

				elem.pickatime({
					format: elem.attr('data-format'),
					interval: parseInt( elem.attr('data-intervals') ),
					formatSubmit: 'HH:i',
					hiddenName: true,
					onOpen: function() { elem.blur(); },
					onClose: function() { elem.blur(); }
				});
			});
		}
	},
	select: {
		isSelected: function( selected, current ){
			if ( selected === current ) {
				return ' selected="selected"';
			}
			return "";
		}
	}
}

jQuery.ajaxSetup({
	beforeSend: function( jqXHR, settings ) {
		if ( settings.processData ) {
			if ( settings.data !== '' ) {
				settings.data += '&um_current_locale=' + um_common_variables.locale;
			} else {
				settings.data = 'um_current_locale=' + um_common_variables.locale;
			}
		} else {
			settings.data = jQuery.extend(
				settings.data,
				{
					um_current_locale: um_common_variables.locale
				}
			);
		}

		return true;
	}
});

jQuery(document).on( 'ajaxStart', function() {
	UM.common.tipsy.hide();
});

jQuery(document).on( 'ajaxSuccess', function() {
	UM.common.tipsy.init();
});

jQuery(document).ready(function() {
	UM.common.tipsy.init();
	UM.common.datetimePicker.init();
});
