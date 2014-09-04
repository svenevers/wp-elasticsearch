(function($) {
	$(function() {
		var options = $.extend({
			'fieldName': 'input[name=s]',
			'maxRows': 10,
			'minLength': 3,
		}, SearchAutocomplete);
		$(options.fieldName).autocomplete({
			source: function( request, response ) {
			    $.ajax({
			        url: options.ajaxurl,
			        dataType: "json",
			        data: {
			        	action: 'autocomplete_callback',
			            query: $(options.fieldName).val()
			        },
			        success: function( data ) {
                        response( $.map( data.hits.hits, function( hit ) {
                            return {
                                label: hit.fields.title,
                                value: hit.fields.title
                            }
                        }));
			        },
			        error: function(jqXHR, textStatus, errorThrown) {
			        	console.log(jqXHR, textStatus, errorThrown);
			        }
			    });
			},
			minLength: options.minLength,
			search: function(event, ui) {
				$(event.currentTarget).addClass('sa_searching');
			},
			create: function() {
			},
			select: function( event, ui ) {
                window.location = "?s=" +  ui.item.value;
			},
			open: function(event, ui) {
				$(event.target).removeClass('sa_searching');
			},
			close: function() {
			}
		});
	$('#datefrom').datepicker({ dateFormat: 'yy-mm-dd' });
	$('#dateto').datepicker({ dateFormat: 'yy-mm-dd'});
	});
})(jQuery);