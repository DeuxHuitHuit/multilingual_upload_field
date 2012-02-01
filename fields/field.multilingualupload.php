<?php

	if (!defined('__IN_SYMPHONY__')) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');
	
	require_once(TOOLKIT . '/fields/field.upload.php');
	
	
	
	final class fieldMultilingualUpload extends fieldUpload {
		
		public function __construct(&$parent){
			parent::__construct($parent);
			
			$this->_name = __('Multilingual File Upload');
		}
		
		
		
	/*-------------------------------------------------------------------------
		Setup:
	-------------------------------------------------------------------------*/
		
		public function createTable(){
			$query = "CREATE TABLE IF NOT EXISTS `tbl_entries_data_{$this->get('id')}` (
	      		`id` int(11) unsigned NOT NULL auto_increment,
	    		`entry_id` int(11) unsigned NOT NULL,
	    		`file` varchar(255) default NULL,
				`size` int(11) unsigned NULL,
				`mimetype` varchar(50) default NULL,
				`meta` varchar(255) default NULL,";

			foreach( FLang::instance()->ld()->languageCodes() as $language_code ){
				$query .= "`file-{$language_code}` varchar(255) default NULL,
				  `size-{$language_code}` int(11) unsigned NULL,
				  `mimetype-{$language_code}` varchar(50) default NULL,
				  `meta-{$language_code}` varchar(255) default NULL,";
			}

			$query .= "PRIMARY KEY (`id`),
				UNIQUE KEY `entry_id` (`entry_id`)
	    		) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;";

			return Symphony::Database()->query($query);
		}

	/*-------------------------------------------------------------------------
		Utilities:
	-------------------------------------------------------------------------*/

		public function entryDataCleanup($entry_id, $data){
			
			foreach( FLang::instance()->ld()->languageCodes() as $language_code ){
				$file_location = WORKSPACE . '/' . ltrim($data['file-'.$language_code], '/');
	
				if(is_file($file_location)){
					General::deleteFile($file_location);
				}
			}

			parent::entryDataCleanup($entry_id);

			return true;
		}
		
	/*-------------------------------------------------------------------------
		Settings:
	-------------------------------------------------------------------------*/
		
		public function findDefaults(&$settings){
			if( !isset($settings['unique']) ) $settings['unique'] = 'yes';
		}
		
		public function displaySettingsPanel(XMLElement &$wrapper, $errors = null) {
			parent::displaySettingsPanel($wrapper, $errors);
			
			foreach( $wrapper->getChildrenByName('div') as $div ){
				
				if( $div->getAttribute('class') == 'compact' ){
					$this->_appendUniqueCheckbox($div);
					break;
				}
			}
		}
		
		private function _appendUniqueCheckbox(XMLElement &$wrapper) {
			if (!$this->_required) return;

			$order = $this->get('sortorder');
			$name = "fields[{$order}][unique]";

			$label = Widget::Label();
			$input = Widget::Input($name, 'yes', 'checkbox');

			if ($this->get('unique') == 'yes') $input->setAttribute('checked', 'checked');

			$label->setValue(__('%s Create unique filenames.', array($input->generate())));

			$wrapper->appendChild($label);
		}
		
		public function commit(){
			if(!Field::commit()) return false;

			$id = $this->get('id');

			if($id === false) return false;

			$settings = array();

			$settings['field_id'] = $id;
			$settings['destination'] = $this->get('destination');
			$settings['validator'] = ($settings['validator'] == 'custom' ? NULL : $this->get('validator'));
			$settings['unique'] = $this->get('unique');

			Symphony::Database()->query("DELETE FROM `tbl_fields_".$this->handle()."` WHERE `field_id` = '$id' LIMIT 1");
			return Symphony::Database()->insert($settings, 'tbl_fields_' . $this->handle());
		}
	
	/*-------------------------------------------------------------------------
		Publish:
	-------------------------------------------------------------------------*/
		
		public function displayPublishPanel(XMLElement &$wrapper, $data=NULL, $flagWithError=NULL, $fieldnamePrefix=NULL, $fieldnamePostfix=NULL){
			
			$wrapper->setAttribute('class', $wrapper->getAttribute('class').' field-multilingual');
			
			$container = new XMLElement('div', null, array('class' => 'container'));
			
			
			/* Label */
			
			$label = Widget::Label($this->get('label'));
			$class = 'file';
			$label->setAttribute('class', $class);
			if($this->get('required') != 'yes') $label->appendChild(new XMLElement('i', __('Optional')));
			
			$container->appendChild($label);
			
			
			$reference_language = FLang::instance()->referenceLanguage();
			$all_languages = FLang::instance()->ld()->allLanguages();
			$language_codes = FLang::instance()->ld()->languageCodes();
			
			
			/* Tabs */
			
			$ul = new XMLElement('ul');
			$ul->setAttribute('class', 'tabs');
			
			foreach( $language_codes as $language_code ){
				$class = $language_code . ($language_code == $reference_language ? ' active' : '');
				$li = new XMLElement('li',($all_languages[$language_code] ? $all_languages[$language_code] : __('Unknown language')));
				$li->setAttribute('class', $class);
				
				// to use this, Multilingual Text must depend on Frontend Localisation so UX is consistent regarding Language Tabs
//				if( $language_code == $reference_language ){
//					$ul->prependChild($li);
//				}
//				else{
					$ul->appendChild($li);
//				}
			}
			
			$container->appendChild($ul);
			
			
			/* Inputs */
			
			foreach( $language_codes as $language_code ){
				$div = new XMLElement('div', NULL, array('class' => 'file tab-panel tab-'.$language_code));
				
				$file = 'file-'.$language_code;
				
				if( $data[$file] ){
					$div->appendChild(
						Widget::Anchor(
							'/workspace' . $data[$file],
							URL . '/workspace' . $data[$file]
						)
					);
				}
				
				$div->appendChild(
					Widget::Input(
						'fields'.$fieldnamePrefix.'['.$this->get('element_name').']['.$language_code.']'.$fieldnamePostfix,
						$data[$file],
						($data[$file] ? 'hidden' : 'file')
					)
				);
	
				$container->appendChild($div);
			}
			
			
			/* Directory check */
			
			if(!is_dir(DOCROOT . $this->get('destination') . '/')){
				$flagWithError = __('The destination directory, <code>%s</code>, does not exist.', array($this->get('destination')));
			}
			elseif(!$flagWithError && !is_writable(DOCROOT . $this->get('destination') . '/')){
				$flagWithError = __('Destination folder, <code>%s</code>, is not writable. Please check permissions.', array($this->get('destination')));
			}
			
			if($flagWithError != NULL){
				$wrapper->appendChild(Widget::wrapFormElementWithError($container, $flagWithError));
			}
			else{
				$wrapper->appendChild($container);
			}
		}
		
		public function checkPostFieldData($data, &$message, $entry_id = NULL) {
			$error = self::__OK__;
			$field_data = $data;
			
			foreach( FLang::instance()->ld()->languageCodes() as $language_code ){
				
				$file_message = '';
				$data = $this->_getData($field_data[$language_code]);
				
				if( is_array($data) && isset($data['name']) ){
					$data['name'] = $this->_getUniqueFilename($data['name'], $language_code);
				}
				
				$status = parent::checkPostFieldData($data, $file_message, $entry_id);
				
				if( $status != self::__OK__ ){
					$message .= "<br />{$language_code}: {$file_message}";
					$error = self::__ERROR__;
				}
			}
			
			return $error;
		}
		
		public function processRawFieldData($data, &$status, $simulate = false, $entry_id = NULL) {
			if( !is_array($data) || empty($data) ) return parent::processRawFieldData($data, $status, $simulate, $entry_id, $language_code);
			
			$result = array();
			$field_data = $data;
			
			foreach( FLang::instance()->ld()->languageCodes() as $language_code ){
				
				$data = $this->_getData($field_data[$language_code]);
				
				if( is_array($data) && isset($data['name']) ){
					$data['name'] = $this->_getUniqueFilename($data['name'], $language_code);
				}
				
				$this->_fakeDefaultFile($language_code, $entry_id);
				
				$file_result = parent::processRawFieldData($data, $status, $simulate, $entry_id, $language_code);
				
				foreach( $file_result as $key => $value ){
					$result[$key.'-'.$language_code] = $value;
				}
			}
			
			return (array) $result;
		}
	
	/*-------------------------------------------------------------------------
		Output:
	-------------------------------------------------------------------------*/

		public function appendFormattedElement(XMLElement &$wrapper, $data){
			$language_code = FLang::instance()->ld()->languageCode();
			
			$data['file'] = $data['file-'.$language_code];
			$data['meta'] = $data['meta-'.$language_code];
			$data['mimetype'] = $data['mimetype-'.$language_code];
			
			parent::appendFormattedElement($wrapper, $data);
		}
		
		public function prepareTableValue($data, XMLElement $link=NULL, $entry_id = null){
			// default to backend language
			$language_code = Lang::get();
			
			if( !in_array($language_code, FLang::instance()->ld()->languageCodes()) ){
				$language_code = FLang::instance()->referenceLanguage();
			}
			
			$data['file'] = $data['file-'.$language_code];
			
			return parent::prepareTableValue($data, $link, $entry_id);
		}
		
		public function getParameterPoolValue($data) {
			return $data['file-'.FLang::instance()->ld()->languageCode()];
		}
		
		public function getExampleFormMarkup(){
	
			$fieldname = 'fields['.$this->get('element_name').'][value-{$url-language}]';
	
			$label = Widget::Label($this->get('label').'
			<!-- '.__('Modify just current language value').' -->
			<input name="fields['.$this->get('element_name').'][value-{$url-language}]" type="text" />
			
			<!-- '.__('Modify all values').' -->');
	
			foreach( FLang::instance()->ld()->languageCodes() as $language_code ){
				$fieldname = 'fields['.$this->get('element_name').'][value-'.$language_code.']';
				$label->appendChild(Widget::Input($fieldname));
			}
	
			return $label;
		}
		
	/*-------------------------------------------------------------------------
		In-house utilities:
	-------------------------------------------------------------------------*/
		
		private function _getUniqueFilename($filename, $language_code = null) {
			if( empty($language_code) || !is_string($language_code) ){
				$language_code = FLang::instance()->referenceLanguage();
			}
			
			// since unix timestamp is 10 digits, the unique filename will be limited to ($crop+1+10) characters;
			$crop  = '150';
			return preg_replace("/(.*)(\.[^\.]+)/e", "substr('$1', 0, $crop).'-'.$language_code.'-'.time().'$2'", $filename);
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
		
				if( empty($data['name']) ) return null;
		
				return $data;
			}
		
			if( empty($data[0]) ) return null;
		
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
		 * @param string $language_code
		 * @param integer $entry_id
		 */
		private function _fakeDefaultFile($language_code, $entry_id){
			
			try {
				$row = Symphony::Database()->fetchRow(0, sprintf(
					"SELECT `file-{$language_code}`, `mimetype-{$language_code}`, `size-{$language_code}`, `meta-{$language_code}` FROM `tbl_entries_data_%d` WHERE `entry_id` = %d",
					$this->get('id'),
					$entry_id
				));
			} catch (Exception $e) {}
			
			$fields['file'] = $row['file-'.$language_code];
			$fields['size'] = $row['size-'.$language_code];
			$fields['meta'] = $row['meta-'.$language_code];
			$fields['mimetype'] = $row['mimetype-'.$language_code];
			
			try {
				Symphony::Database()->update($fields, "tbl_entries_data_{$this->get('id')}", "`entry_id` = {$entry_id}");
			}
			catch (Exception $e) {}
		}
		
	}
