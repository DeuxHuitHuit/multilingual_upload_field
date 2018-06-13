(function ($, Symphony, window, undefined) {

	$(document).ready(function(){
		$('div.field-multilingual_upload .file').each(function () {
			var t = $(this);

			if (t.find('a').length) {
				$('<em>' + Symphony.Language.get('Remove File') + '</em>').appendTo($('.frame', t)).click(function (event) {
					event.preventDefault();

					var div = $(this).parent(),
						name = div.find('input').attr('name');

					div.empty().append('<input name="' + name + '" type="file">');
				});
			}
		});
	});

}(this.jQuery, this.Symphony, this));
