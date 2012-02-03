jQuery(document).ready(function() {
	
	function init() {
		jQuery('.field-multilingualupload').each(function() {
			var field = new MultilingualField(jQuery(this));
		});
		
		if( jQuery('div.file:has(a):has(em)').length == 0){
			// Upload fields
			jQuery('<em>' + Symphony.Language.get('Remove File') + '</em>').appendTo('div.file:has(a)').click(function(event) {
				var div = jQuery(this).parent(),
					name = div.find('input').attr('name');
				
				// Prevent clicktrough
				event.preventDefault();
				
				// Add new empty file input
				div.empty().append('<input name="' + name + '" type="file">');
			});
		}
	}
	
	/**
	 avoid conflicts between different multilangual fields
	 see http://symphony-cms.com/discuss/thread/81854/#position-2
	 */
	var base = Symphony.WEBSITE + '/extensions/multilingual_upload_field/assets/';
		if (typeof this.MultilingualField !== 'function') {
			jQuery.getScript(base + 'multilingual-field.js', function () {
				init();
			});
		} else {
			init();
		}
	
});



/*---------------------------------------------------------------------------*/