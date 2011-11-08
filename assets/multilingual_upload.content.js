jQuery(document).ready(function() {
	jQuery('.field-multilingualupload').each(function() {
		var field = new MultilingualField(jQuery(this));
	});
	
	// Upload fields
	jQuery('<em>' + Symphony.Language.get('Remove File') + '</em>').appendTo('div.file:has(a)').click(function(event) {
        var div = jQuery(this).parent(),
            name = div.find('input').attr('name');

        // Prevent clicktrough
        event.preventDefault();

        // Add new empty file input
        div.empty().append('<input name="' + name + '" type="file">');
    });
});

// make sure it doesn't overlap with Multilingual Text functions
if (typeof MultilingualField == "undefined") {
	
	function MultilingualField(field) {
		this.field = field;
		
		this.init();
	}

	MultilingualField.prototype.init = function() {
		var self = this,
		activeTab = this.field.find('ul.tabs li.active');

		// Fallback to first tab if no tab is set as active by default
		if (activeTab.length == 0) {
			activeTab = this.field.find('ul.tabs li:eq(0)');
		}

		// bind tab events
		this.field.find('ul.tabs li').bind('click', function(e) {
			e.preventDefault();
			self.setActiveTab(jQuery(this).attr('class').split(' ')[0]);
		});

		// Show the active tab
		this.setActiveTab(activeTab.attr('class').split(' ')[0]);
	};

	MultilingualField.prototype.setActiveTab = function(tab_name) {
		var self = this;

		// hide all tab panels
		this.field.find('.tab-panel').hide();

		// find the desired tab and activate the tab and its panel
		this.field.find('ul.tabs li').each(function() {
			var tab = jQuery(this);

			if (tab.hasClass(tab_name)) {
				tab.addClass('active');

				var panel = tab.parent().parent().find('.tab-' + tab_name);
				panel.show();

			} else {
				tab.removeClass('active');
			}
		});
	};
}


/*---------------------------------------------------------------------------*/