(function ($, Symphony, window, undefined) {

	$(document).ready(function(){
		if ($('div.file:has(a):has(em)').length === 0) {
			$('<em>' + Symphony.Language.get('Remove File') + '</em>').appendTo('div.file:has(a)').click(function (event) {
				event.preventDefault();

				var div = $(this).parent(),
					name = div.find('input').attr('name');

				div.empty().append('<input name="' + name + '" type="file">');
			});
		}
	});

}(this.jQuery, this.Symphony, this));
