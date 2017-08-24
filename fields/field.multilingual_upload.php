<?php

	if(!defined('__IN_SYMPHONY__')) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');

	require_once(TOOLKIT.'/fields/field.upload.php');
	require_once(EXTENSIONS.'/frontend_localisation/extension.driver.php');
	require_once(EXTENSIONS.'/frontend_localisation/lib/class.FLang.php');

	final class fieldMultilingual_Upload extends fieldUpload
	{

		/*------------------------------------------------------------------------------------------------*/
		/*  Definition  */
		/*------------------------------------------------------------------------------------------------*/

		public function __construct(){
			parent::__construct();

			$this->_name = __('Multilingual File Upload');
		}

		public function createTable(){
			$query = "
				CREATE TABLE IF NOT EXISTS `tbl_entries_data_{$this->get('id')}` (
					`id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
					`entry_id` INT(11) UNSIGNED NOT NULL,
					`file` VARCHAR(255) DEFAULT NULL,
					`size` INT(11) UNSIGNED NULL,
					`mimetype` VARCHAR(50) DEFAULT NULL,
					`meta` VARCHAR(255) DEFAULT NULL,";

			foreach( FLang::getLangs() as $lc ){
				$query .= sprintf('
					`file-%1$s` VARCHAR(255) DEFAULT NULL,
					`size-%1$s` INT(11) UNSIGNED NULL,
					`mimetype-%1$s` VARCHAR(50) DEFAULT NULL,
					`meta-%1$s` VARCHAR(255) DEFAULT NULL,',
					$lc
				);
			}

			$query .= "
					PRIMARY KEY (`id`),
					UNIQUE KEY `entry_id` (`entry_id`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";

			return Symphony::Database()->query($query);
		}


		/*------------------------------------------------------------------------------------------------*/
		/*  Settings  */
		/*------------------------------------------------------------------------------------------------*/

		public function findDefaults(array &$settings)
		{
			if ($settings['unique'] != 'no') {
				$settings['unique'] = 'yes';
			}

			if ($settings['default_main_lang'] != 'yes') {
				$settings['default_main_lang'] = 'no';
			}

			return parent::findDefaults($settings);
		}

		public function set($field, $value)
		{
			if ($field == 'required_languages' && !is_array($value)) {
				$value = array_filter(explode(',', $value));
			}

			$this->_settings[$field] = $value;
		}

		public function get($field = null)
		{
			if ($field == 'required_languages') {
				return (array) parent::get($field);
			}

			return parent::get($field);
		}

		public function displaySettingsPanel(XMLElement &$wrapper, $errors = null)
		{
			Extension_Multilingual_Upload_Field::appendSettingsAssets();

			parent::displaySettingsPanel($wrapper, $errors);

			$last_div_pos = $wrapper->getNumberOfChildren() - 1;

			$two_columns = new XMLElement('div', null, array('class' => 'two columns'));

			$this->appendShowColumnCheckbox($two_columns);
			$this->appendUniqueCheckbox($two_columns);
			$this->appendDefLangValCheckbox($two_columns);
			$wrapper->replaceChildAt($last_div_pos, $two_columns);

			$two_columns = new XMLElement('div', null, array('class' => 'two columns'));
			$this->appendRequiredLanguages($two_columns);
			$wrapper->appendChild($two_columns);
		}

		protected function appendUniqueCheckbox(XMLElement &$wrapper)
		{
			$label = Widget::Label(null, null, 'column');
			$input = Widget::Input("fields[{$this->get('sortorder')}][unique]", 'yes', 'checkbox');
			if ($this->get('unique') == 'yes') {
				$input->setAttribute('checked', 'checked');
			}
			$label->setValue(__('%s Create unique filenames.', array($input->generate())));

			$wrapper->appendChild($label);
		}

		protected function appendDefLangValCheckbox(XMLElement &$wrapper)
		{
			$label = Widget::Label(null, null, 'column');
			$input = Widget::Input("fields[{$this->get('sortorder')}][default_main_lang]", 'yes', 'checkbox');
			if ($this->get('default_main_lang') == 'yes') {
				$input->setAttribute('checked', 'checked');
			}
			$label->setValue(__('%s Use value from main language if selected language has empty value.', array($input->generate())));

			$wrapper->appendChild($label);
		}

		protected function appendRequiredLanguages(XMLElement &$wrapper)
		{
			$name = "fields[{$this->get('sortorder')}][required_languages][]";

			$required_languages = $this->get('required_languages');

			$displayed_languages = FLang::getLangs();

			if (($key = array_search(FLang::getMainLang(), $displayed_languages)) !== false) {
				unset($displayed_languages[$key]);
			}

			$options = Extension_Languages::findOptions($required_languages, $displayed_languages);

			array_unshift(
				$options,
				array('all', $this->get('required') == 'yes', __('All')),
				array('main', in_array('main', $required_languages), __('Main language'))
			);

			$label = Widget::Label(__('Required languages'));
			$label->setAttribute('class', 'column');
			$label->appendChild(
				Widget::Select($name, $options, array('multiple' => 'multiple'))
			);

			$wrapper->appendChild($label);
		}

		public function commit()
		{
			$required_languages = $this->get('required_languages');

			// all are required
			if (in_array('all', $required_languages)) {
				$this->set('required', 'yes');
				$required_languages = array('all');
			}
			else {
				$this->set('required', 'no');
			}

			// if main is required, remove the actual language code
			if (in_array('main', $required_languages)) {
				if (($key = array_search(FLang::getMainLang(), $required_languages)) !== false) {
					unset($required_languages[$key]);
				}
			}

			$this->set('required_languages', $required_languages);

			if (!parent::commit()) {
				return false;
			}

			return Symphony::Database()->query(sprintf("
				UPDATE
					`tbl_fields_%s`
				SET
					`default_main_lang` = '%s',
					`required_languages` = '%s',
					`unique` = '%s'
				WHERE
					`field_id` = '%s';",
				$this->handle(),
				$this->get('default_main_lang') === 'yes' ? 'yes' : 'no',
				implode(',', $this->get('required_languages')),
				$this->get('unique'),
				$this->get('id')
			));
		}



		/*------------------------------------------------------------------------------------------------*/
		/*  Publish  */
		/*------------------------------------------------------------------------------------------------*/

		public function displayPublishPanel(XMLElement &$wrapper, $data = null, $flagWithError = null, $fieldnamePrefix = null, $fieldnamePostfix = null, $entry_id = null){
			Extension_Frontend_Localisation::appendAssets();
			Extension_Multilingual_Upload_Field::appendAssets();

			$main_lang = FLang::getMainLang();
			$all_langs = FLang::getAllLangs();
			$langs = FLang::getLangs();

			$wrapper->setAttribute('class', $wrapper->getAttribute('class').' field-multilingual');
			$container = new XMLElement('div', null, array('class' => 'container'));


			/*------------------------------------------------------------------------------------------------*/
			/*  Label  */
			/*------------------------------------------------------------------------------------------------*/

			$label = Widget::Label($this->get('label'), null, 'file');
			$optional = '';
			$title = '';
			$required_languages = $this->getRequiredLanguages();

			$required = in_array('all', $required_languages) || count($langs) == count($required_languages);

			if (!$required) {
				if (empty($required_languages)) {
					$optional .= __('All languages are optional');
				} else {
					$optional_langs = array();
					foreach ($langs as $lang) {
						if (!in_array($lang, $required_languages)) {
							$optional_langs[] = $all_langs[$lang];
						}
					}

					foreach ($optional_langs as $idx => $lang) {
						$optional .= ' ' . __($lang);
						if ($idx < count($optional_langs) - 2) {
							$optional .= ',';
						} else if ($idx < count($optional_langs) - 1) {
							$optional .= ' ' . __('and');
						}
					}
					if (count($optional_langs) > 1) {
						$optional .= __(' are optional');
					} else {
						$optional .= __(' is optional');
					}
				}
				if ($this->get('default_main_lang') == 'yes') {
					$title .= __('Empty values defaults to %s', array($all_langs[$main_lang]));
				}
			}

			if ($optional !== '') {
				foreach ($langs as $lc) {
					$label->appendChild(new XMLElement('i', $optional, array(
						'class'          => "tab-element tab-$lc",
						'data-lang_code' => $lc,
						'title'          => $title,
					)));
				}
			}

			$container->appendChild($label);

			/*------------------------------------------------------------------------------------------------*/
			/*  Tabs  */
			/*------------------------------------------------------------------------------------------------*/

			$ul = new XMLElement('ul', null, array('class' => 'tabs'));

			foreach ($langs as $lc) {
				$li = new XMLElement('li', $lc, array('class' => $lc));
				$lc === $main_lang ? $ul->prependChild($li) : $ul->appendChild($li);
			}
			$container->appendChild($ul);

			/*------------------------------------------------------------------------------------------------*/
			/*  Panels  */
			/*------------------------------------------------------------------------------------------------*/

			foreach ($langs as $lc) {
				$div = new XMLElement('div', null, array('class' => 'file tab-panel tab-'.$lc));
				$frame = new XMLElement('div', null, array('class' => 'frame'));

				$file = 'file-'.$lc;

				if( $data[$file] ){
					$filePath = $this->get('destination').'/'.$data[$file];

					$frame->appendChild(
						Widget::Anchor($filePath, URL.$filePath)
					);
				}

				$frame->appendChild(
					Widget::Input(
						'fields'.$fieldnamePrefix.'['.$this->get('element_name').']['.$lc.']'.$fieldnamePostfix,
						$data[$file],
						$data[$file] ? 'hidden' : 'file'
					)
				);

				$div->appendChild($frame);
				$container->appendChild($div);
			}


			/*------------------------------------------------------------------------------------------------*/
			/*  Errors  */
			/*------------------------------------------------------------------------------------------------*/

			if (!is_dir(DOCROOT.$this->get('destination').'/')) {
				$flagWithError = __('The destination directory, <code>%s</code>, does not exist.', array($this->get('destination')));
			}
			else if (!$flagWithError && !is_writable(DOCROOT.$this->get('destination').'/')) {
				$flagWithError = __('Destination folder, <code>%s</code>, is not writable. Please check permissions.', array($this->get('destination')));
			}

			if ($flagWithError != null ) {
				$wrapper->appendChild(Widget::Error($container, $flagWithError));
			}
			else {
				$wrapper->appendChild($container);
			}
		}



		/*------------------------------------------------------------------------------------------------*/
		/*  Input  */
		/*------------------------------------------------------------------------------------------------*/

		public function checkPostFieldData($data, &$message, $entry_id = null){
			$error = self::__OK__;
			$field_data = $data;
			$all_langs = FLang::getAllLangs();
			$required_languages = $this->getRequiredLanguages();
			$original_required  = $this->get('required');

			foreach (FLang::getLangs() as $lc) {
				$this->set('required', in_array($lc, $required_languages) ? 'yes' : 'no');

				$file_message = '';
				$data = $this->_getData($field_data[$lc]);

				if (is_array($data) && isset($data['name'])) {
					$data['name'] = $this->getUniqueFilename($data['name'], $lc, true);
				}

				$status = parent::checkPostFieldData($data, $file_message, $entry_id);

				// if one language fails, all fail
				if ($status != self::__OK__) {
					$local_msg = "<br />[$lc] {$all_langs[$lc]}: {$file_message}";

					if ($lc === $main_lang) {
						$message = $local_msg . $message;
					}
					else {
						$message = $message . $local_msg;
					}

					$error = self::__ERROR__;
				}
			}

			$this->set('required', $original_required);

			return $error;
		}

		public function processRawFieldData($data, &$status, &$message = null, $simulate = false, $entry_id = null){
			if(!is_array($data) || empty($data)) {
				return parent::processRawFieldData($data, $status, $message, $simulate, $entry_id);
			}

			$status = self::__OK__;
			$result = array();
			$field_data = $data;
			$main_lang = FLang::getMainLang();
			$missing_langs = array();

			foreach (FLang::getLangs() as $lc) {
				if (!isset($field_data[$lc])) {
					$missing_langs[] = $lc;
					continue;
				}

				$data = $this->_getData($field_data[$lc]);

				if (is_array($data) && isset($data['name'])) {
					$data['name'] = $this->getUniqueFilename($data['name'], $lc);
				}

				// Make this language the default for now
				// parent::processRawFieldData needs this.
				if ($entry_id) {
					Symphony::Database()->query(sprintf(
						"UPDATE `tbl_entries_data_%d`
							SET
							`file` = `file-$lc`,
							`mimetype` = `mimetype-$lc`,
							`size` = `size-$lc`,
							`meta` = `meta-$lc`
							WHERE `entry_id` = %d",
						$this->get('id'),
						$entry_id
					));
				}

				$local_status = self::__OK__;
				$local_messsage = '';
				$file_result = parent::processRawFieldData($data, $local_status, $local_messsage, $simulate, $entry_id);
				if ($local_status != self::__OK__) {
					$message .= $local_messsage;
					$status = $local_status;
				}

				if (is_array($file_result)) {
					foreach ($file_result as $key => $value) {
						$result["$key-$lc"] = $value;
					}
				}
			}

			if (!empty($missing_langs) && $entry_id) {
				$crt_data = $this->getCurrentData($entry_id);

				foreach ($missing_langs as $lc) {
					$result["file-$lc"]     = $crt_data["file-$lc"];
					$result["size-$lc"]     = $crt_data["size-$lc"];
					$result["meta-$lc"]     = $crt_data["meta-$lc"];
					$result["mimetype-$lc"] = $crt_data["mimetype-$lc"];
				}
			}

			// Update main lang
			$result['file']     = $result["file-$main_lang"];
			$result['size']     = $result["size-$main_lang"];
			$result['meta']     = $result["meta-$main_lang"];
			$result['mimetype'] = $result["mimetype-$main_lang"];

			return $result;
		}

		protected function getCurrentData($entry_id) {
			$query = sprintf(
				'SELECT * FROM `tbl_entries_data_%d`
				WHERE `entry_id` = %d',
				$this->get('id'),
				$entry_id
			);

			return Symphony::Database()->fetchRow(0, $query);
		}

		/*------------------------------------------------------------------------------------------------*/
		/*  Output  */
		/*------------------------------------------------------------------------------------------------*/

		public function appendFormattedElement(XMLElement &$wrapper, $data, $encode = false, $mode = null, $entry_id = null){
			$lang_code = $this->getLang($data);
			$data['file'] = $data["file-$lang_code"];
			$data['size'] = $data["size-$lang_code"];
			$data['meta'] = $data["meta-$lang_code"];
			$data['mimetype'] = $data["mimetype-$lang_code"];
			parent::appendFormattedElement($wrapper, $data);
		}

		// @todo: remove and fallback to default (Symphony 2.5 only?)
		public function prepareTableValue($data, XMLElement $link = null, $entry_id = null){
			$lang_code = $this->getLang($data);
			$data['file'] = $data["file-$lang_code"];
			$data['size'] = $data["size-$lang_code"];
			$data['meta'] = $data["meta-$lang_code"];
			$data['mimetype'] = $data["mimetype-$lang_code"];
			return parent::prepareTableValue($data, $link, $entry_id);
		}

		public function prepareTextValue($data, $entry_id = null) {
			$lc = $this->getLang($data);
			return strip_tags($data["file-$lc"]);
		}

		public function getParameterPoolValue(array $data, $entry_id = null) {
			$lc = $this->getLang();
			return $data["file-$lc"];
		}

		public function getExampleFormMarkup(){
			$element_name = $this->get('element_name');

			$label = Widget::Label($element_name.'
					<!-- '.__('Modify just current language value').' -->
					<input name="fields['.$this->get('element_name').'][value-{$url-fl-language}]" type="text" />

					<!-- '.__('Modify all values').' -->');

			foreach( FLang::getLangs() as $lc ){
				$label->appendChild(Widget::Input("fields[{$element_name}][value-{$lc}]"));
			}

			return $label;
		}



		/*------------------------------------------------------------------------------------------------*/
		/*  Utilities  */
		/*------------------------------------------------------------------------------------------------*/

		public function entryDataCleanup($entry_id, $data = null)
		{
			foreach( FLang::getLangs() as $lc ){
				$file_location = WORKSPACE.'/'.ltrim($data['file-'.$lc], '/');

				if( is_file($file_location) ){
					General::deleteFile($file_location);
				}
			}

			parent::entryDataCleanup($entry_id, $data);

			return true;
		}

		/**
		 * Returns required languages for this field.
		 */
		public function getRequiredLanguages()
		{
			$required = $this->get('required_languages');

			$languages = FLang::getLangs();

			if (in_array('all', $required)) {
				return $languages;
			}

			if (($key = array_search('main', $required)) !== false) {
				unset($required[$key]);

				$required[] = FLang::getMainLang();
				$required   = array_unique($required);
			}

			return $required;
		}

		protected function getLang($data = null)
		{
			$required_languages = $this->getRequiredLanguages();
			// Get Lang from Frontend Localisation
			$lc = FLang::getLangCode();

			if (!FLang::validateLangCode($lc)) {
				// Revert to backend language
				$lc = Lang::get();
			}

			// If value is empty for this language, load value from main language
			if (is_array($data) && $this->get('default_main_lang') == 'yes') {
				// If value is empty
				if (empty($data["file-$lc"])) {
					$lc = FLang::getMainLang();
				}
				// If value if still empty try to use the value from the first
				// required language
				if (empty($data["file-$lc"]) && count($required_languages) > 0) {
					$lc = $required_languages[0];
				}
			}
			return $lc;
		}


		/*------------------------------------------------------------------------------------------------*/
		/*  Field schema  */
		/*------------------------------------------------------------------------------------------------*/

		public function appendFieldSchema(XMLElement $f)
		{
			$required_languages = $this->getRequiredLanguages();
			$required = new XMLElement('required-languages');

			foreach ($required_languages as $lc) {
				$required->appendChild(new XMLElement('item', $lc));
			}

			$f->appendChild($required);
		}



		/*------------------------------------------------------------------------------------------------*/
		/*  In-house  */
		/*------------------------------------------------------------------------------------------------*/

		protected function getUniqueFilename($filename, $lang_code = null, $enable = false){
			if( $enable ){
				if( empty($lang_code) || !is_string($lang_code) ){
					$lang_code = FLang::getMainLang();
				}

				$crop = '150';
				$replace = $lang_code;

				if( $this->get('unique') == 'yes' ) $replace .= ".'-'.time()";

				return preg_replace_callback(
					"/(.*)(\.[^\.]+)/",
					function($matches){
						return substr($matches[1], 0, $crop).'-'.$replace.$matches[2];
					},
					$filename
				);
			}

			return $filename;
		}

		/**
		 * It is possible that data from Symphony won't come as expected associative array.
		 *
		 * @param array $data
		 *
		 * @return array
		 */
		private function _getData($data){
			if( is_string($data) ) return $data;

			if( !is_array($data) ) return null;

			if( array_key_exists('name', $data) ){
				return $data;
			}

			return array(
				'name' => $data[0],
				'type' => $data[1],
				'tmp_name' => $data[2],
				'error' => $data[3],
				'size' => $data[4]
			);
		}

	}
