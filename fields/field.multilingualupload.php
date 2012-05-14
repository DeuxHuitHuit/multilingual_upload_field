<?php

	if( !defined('__IN_SYMPHONY__') ) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');



	require_once(TOOLKIT.'/fields/field.upload.php');
	require_once(EXTENSIONS.'/frontend_localisation/lib/class.FLang.php');



	final class fieldMultilingualUpload extends fieldUpload
	{

		/*------------------------------------------------------------------------------------------------*/
		/*  Definition  */
		/*------------------------------------------------------------------------------------------------*/

		protected $_driver;

		public function __construct(){
			parent::__construct();

			$this->_name = __('Multilingual File Upload');
			$this->_driver = Symphony::ExtensionManager()->create('multilingual_upload_field');
		}

		public function createTable(){
			$query = "CREATE TABLE IF NOT EXISTS `tbl_entries_data_{$this->get('id')}` (
	      		`id` int(11) unsigned NOT NULL auto_increment,
	    		`entry_id` int(11) unsigned NOT NULL,
	    		`file` varchar(255) default NULL,
				`size` int(11) unsigned NULL,
				`mimetype` varchar(50) default NULL,
				`meta` varchar(255) default NULL,";

			foreach( FLang::getLangs() as $lc ){
				$query .= "`file-{$lc}` varchar(255) default NULL,
					`size-{$lc}` int(11) unsigned NULL,
					`mimetype-{$lc}` varchar(50) default NULL,
					`meta-{$lc}` varchar(255) default NULL,";
			}

			$query .= "PRIMARY KEY (`id`),
				UNIQUE KEY `entry_id` (`entry_id`)
	    		) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";

			return Symphony::Database()->query($query);
		}



		/*------------------------------------------------------------------------------------------------*/
		/*  Settings  */
		/*------------------------------------------------------------------------------------------------*/

		public function findDefaults(&$settings){
			if( !isset($settings['unique']) ){
				$settings['unique'] = 'yes';
			}

			if( $settings['def_ref_lang'] != 'yes' ){
				$settings['def_ref_lang'] = 'no';
			}

			return parent::findDefaults($settings);
		}

		public function displaySettingsPanel(XMLElement &$wrapper, $errors = null){
			parent::displaySettingsPanel($wrapper, $errors);

			$div = New XMLElement('div', null, array('class' => 'two columns'));

			$this->_appendUniqueCheckbox($div);
			$this->_appendDefLangValCheckbox($div);

			$wrapper->appendChild($div);
		}

		private function _appendUniqueCheckbox(XMLElement &$wrapper){
			$label = Widget::Label(null, null, 'column');
			$input = Widget::Input("fields[{$this->get('sortorder')}][unique]", 'yes', 'checkbox');
			if( $this->get('unique') == 'yes' ) $input->setAttribute('checked', 'checked');
			$label->setValue(__('%s Create unique filenames.', array($input->generate())));

			$wrapper->appendChild($label);
		}

		private function _appendDefLangValCheckbox(XMLElement &$wrapper){
			$label = Widget::Label(null, null, 'column');
			$input = Widget::Input("fields[{$this->get('sortorder')}][def_ref_lang]", 'yes', 'checkbox');
			if( $this->get('def_ref_lang') == 'yes' ) $input->setAttribute('checked', 'checked');
			$label->setValue(__('%s Use value from main language if selected language has empty value.', array($input->generate())));

			$wrapper->appendChild($label);
		}

		public function commit(){
			if( !Field::commit() ) return false;

			$id = $this->get('id');

			if( $id === false ) return false;

			$settings = array();

			$settings['field_id'] = $id;
			$settings['destination'] = $this->get('destination');
			$settings['validator'] = ($settings['validator'] == 'custom' ? NULL : $this->get('validator'));
			$settings['unique'] = $this->get('unique');
			$settings['def_ref_lang'] = $this->get('def_ref_lang');

			Symphony::Database()->query("DELETE FROM `tbl_fields_".$this->handle()."` WHERE `field_id` = '$id' LIMIT 1");
			return Symphony::Database()->insert($settings, 'tbl_fields_'.$this->handle());
		}



		/*------------------------------------------------------------------------------------------------*/
		/*  Input  */
		/*------------------------------------------------------------------------------------------------*/

		public function displayPublishPanel(XMLElement &$wrapper, $data = NULL, $flagWithError = NULL, $fieldnamePrefix = NULL, $fieldnamePostfix = NULL){
			$this->_driver->appendAssets();

			$wrapper->setAttribute('class', $wrapper->getAttribute('class').' field-multilingual_upload field-multilingual');

			$container = new XMLElement('div', null, array('class' => 'container'));


			/* Label */

			$label = Widget::Label($this->get('label'));
			$class = 'file';
			$label->setAttribute('class', $class);
			if( $this->get('required') != 'yes' ) $label->appendChild(new XMLElement('i', __('Optional')));

			$container->appendChild($label);


			$main_lang = FLang::getMainLang();
			$all_langs = FLang::getAllLangs();
			$langs = FLang::getLangs();


			/* Tabs */

			$ul = new XMLElement('ul', null, array('class' => 'tabs'));

			foreach( $langs as $lc ){
				$li = new XMLElement(
					'li',
					($all_langs[$lc] ? $all_langs[$lc] : __('Unknown language')),
					array('class' => $lc.($lc == $main_lang ? ' active' : ''))
				);

				$lc === $main_lang ? $ul->prependChild($li) : $ul->appendChild($li);
			}

			$container->appendChild($ul);


			/* Inputs */

			foreach( $langs as $lc ){
				$div = new XMLElement('div', NULL, array('class' => 'file tab-panel tab-'.$lc));

				$file = 'file-'.$lc;

				if( $data[$file] ){
					$div->appendChild(
						Widget::Anchor('/workspace'.$data[$file], URL.'/workspace'.$data[$file])
					);
				}

				$div->appendChild(
					Widget::Input(
						'fields'.$fieldnamePrefix.'['.$this->get('element_name').']['.$lc.']'.$fieldnamePostfix,
						$data[$file],
						$data[$file] ? 'hidden' : 'file'
					)
				);

				$container->appendChild($div);
			}


			/* Directory check */

			if( !is_dir(DOCROOT.$this->get('destination').'/') ){
				$flagWithError = __('The destination directory, <code>%s</code>, does not exist.', array($this->get('destination')));
			}
			elseif( !$flagWithError && !is_writable(DOCROOT.$this->get('destination').'/') ){
				$flagWithError = __('Destination folder, <code>%s</code>, is not writable. Please check permissions.', array($this->get('destination')));
			}

			if( $flagWithError != NULL ){
				$wrapper->appendChild(Widget::Error($container, $flagWithError));
			}
			else{
				$wrapper->appendChild($container);
			}
		}

		public function checkPostFieldData($data, &$message, $entry_id = NULL){
			$error = self::__OK__;
			$field_data = $data;

			foreach( FLang::getLangs() as $lc ){

				$file_message = '';
				$data = $this->_getData($field_data[$lc]);

				if( is_array($data) && isset($data['name']) ){
					$data['name'] = $this->getUniqueFilename($data['name'], $lc, true);
				}

				$status = parent::checkPostFieldData($data, $file_message, $entry_id);

				// if one language fails, all fail
				if( $status != self::__OK__ ){
					$message .= "<br />{$lc}: {$file_message}";
					$error = self::__ERROR__;
				}
			}

			return $error;
		}

		public function processRawFieldData($data, &$status, &$message, $simulate = false, $entry_id = NULL){
			if( !is_array($data) || empty($data) ) return parent::processRawFieldData($data, $status, $message, $simulate, $entry_id);

			$result = array();
			$field_data = $data;

			foreach( FLang::getLangs() as $lc ){

				$data = $this->_getData($field_data[$lc]);

				if( is_array($data) && isset($data['name']) ){
					$data['name'] = $this->getUniqueFilename($data['name'], $lc);
				}

				$this->_fakeDefaultFile($lc, $entry_id);

				$file_result = parent::processRawFieldData($data, $status, $message, $simulate, $entry_id, $lc);

				if( is_array($file_result) ){
					foreach( $file_result as $key => $value ){
						$result[$key.'-'.$lc] = $value;
					}
				}
			}

			return $result;
		}



		/*------------------------------------------------------------------------------------------------*/
		/*  Output  */
		/*------------------------------------------------------------------------------------------------*/

		public function appendFormattedElement(XMLElement &$wrapper, $data){
			$lang_code = FLang::getLangCode();

			// If value is empty for this language, load value from reference language
			if( $this->get('def_ref_lang') == 'yes' && $data['file-'.$lang_code] == '' ){
				$lang_code = FLang::getMainLang();
			}

			$data['file'] = $data['file-'.$lang_code];
			$data['meta'] = $data['meta-'.$lang_code];
			$data['mimetype'] = $data['mimetype-'.$lang_code];

			parent::appendFormattedElement($wrapper, $data);
		}

		public function prepareTableValue($data, XMLElement $link = NULL, $entry_id = null){
			// default to backend language
			$lang_code = Lang::get();

			if(
				!FLang::validateLangCode($lang_code) // language not supported
				|| ($this->get('def_ref_lang') === 'yes' && $data['file-'.$lang_code] === '') // or value is empty for this language
			){
				$lang_code = FLang::getMainLang();
			}

			$data['file'] = $data['file-'.$lang_code];

			return parent::prepareTableValue($data, $link, $entry_id);
		}

		public function getParameterPoolValue($data){
			$lang_code = FLang::getLangCode();

			// If value is empty for this language, load value from reference language
			if( $this->get('def_ref_lang') == 'yes' && $data['file-'.$lang_code] == '' ){
				$lang_code = FLang::getMainLang();
			}

			return $data['file-'.$lang_code];
		}

		public function getExampleFormMarkup(){

			$fieldname = 'fields['.$this->get('element_name').'][value-{$url-language}]';

			$label = Widget::Label($this->get('label').'
			<!-- '.__('Modify just current language value').' -->
			<input name="fields['.$this->get('element_name').'][value-{$url-language}]" type="text" />
			
			<!-- '.__('Modify all values').' -->');

			foreach( FLang::getLangs() as $lc ){
				$fieldname = 'fields['.$this->get('element_name').'][value-'.$lc.']';
				$label->appendChild(Widget::Input($fieldname));
			}

			return $label;
		}



		/*------------------------------------------------------------------------------------------------*/
		/*  Utilities  */
		/*------------------------------------------------------------------------------------------------*/

		public function entryDataCleanup($entry_id, $data){

			foreach( FLang::getLangs() as $lc ){
				$file_location = WORKSPACE.'/'.ltrim($data['file-'.$lc], '/');

				if( is_file($file_location) ){
					General::deleteFile($file_location);
				}
			}

			parent::entryDataCleanup($entry_id, $data);

			return true;
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

				return preg_replace("/(.*)(\.[^\.]+)/e", "substr('$1', 0, $crop).'-'.$replace.'$2'", $filename);
			}

			return $filename;
		}


		/**
		 * It is possible that data from Symphony won't come as expected associative array.
		 *
		 * @param array $data
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

		/**
		 * Set default columns (file, mimetype, size and meta) in database table to given language reference values.
		 *
		 * @param string  $lang_code
		 * @param integer $entry_id
		 */
		private function _fakeDefaultFile($lang_code, $entry_id){

			try{
				$row = Symphony::Database()->fetchRow(0, sprintf(
					"SELECT `file-{$lang_code}`, `mimetype-{$lang_code}`, `size-{$lang_code}`, `meta-{$lang_code}` FROM `tbl_entries_data_%d` WHERE `entry_id` = %d",
					$this->get('id'),
					$entry_id
				));
			} catch( Exception $e ){
			}

			$fields['file'] = $row['file-'.$lang_code];
			$fields['size'] = $row['size-'.$lang_code];
			$fields['meta'] = $row['meta-'.$lang_code];
			$fields['mimetype'] = $row['mimetype-'.$lang_code];

			try{
				Symphony::Database()->update($fields, "tbl_entries_data_{$this->get('id')}", "`entry_id` = {$entry_id}");
			}
			catch( Exception $e ){
			}
		}

	}
