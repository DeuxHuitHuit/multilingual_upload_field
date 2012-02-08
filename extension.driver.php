<?php

	if (!defined('__IN_SYMPHONY__')) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');
	
	
	
	define_safe(MULTILINGUAL_UPLOAD_FIELD_NAME, 'Field: Multilingual File Upload');
	define_safe(MULTILINGUAL_UPLOAD_FIELD_GROUP, 'multilingual_upload_field');
	
	
	
	class extension_multilingual_upload_field extends Extension {

		public function about() {
			return array(
				'name' => MULTILINGUAL_UPLOAD_FIELD_NAME,
				'version' => '1.2',
				'release-date' => '2012-02-08',
				'author' => array(
					array(
						'name' => 'Xander Group',
						'email' => 'symphonycms@xandergroup.ro',
						'website' => 'http://www.xanderadvertising.com'
					),
					array(
						'name' => 'Vlad Ghita',
						'email' => 'vlad.ghita@xandergroup.ro',
					),
				),
				'description'	=> 'Upload files on multilingual basis.'
			);
		}

		
		
		public function install() {
			return Symphony::Database()->query(
				"CREATE TABLE `tbl_fields_multilingualupload` (
					`id` int(11) unsigned NOT NULL auto_increment,
					`field_id` int(11) unsigned NOT NULL,
					`destination` varchar(255) NOT NULL,
					`validator` varchar(50),
					`unique` enum('yes','no') default 'yes',
					`def_ref_lang` enum('yes','no') default 'yes',
					PRIMARY KEY (`id`),
					KEY `field_id` (`field_id`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;"
			);
		}
		
		public function update($previous_version){
			if( version_compare($previous_version, '1.2', '<') ){
				Symphony::Database()->query("ALTER TABLE `tbl_fields_multilingualupload` ADD COLUMN `def_ref_lang` ENUM('yes','no') DEFAULT 'yes'");
				Symphony::Database()->query("UPDATE `tbl_fields_multilingualupload` SET `def_ref_lang` = 'no'");
			}
		
			return true;
		}
		
		public function uninstall() {
			Symphony::Database()->query("DROP TABLE `tbl_fields_multilingualupload`");
		}
		
		
		
		public function getSubscribedDelegates(){
			return array(
				array(
					'page' => '/system/preferences/',
					'delegate' => 'AddCustomPreferenceFieldsets',
					'callback' => 'dAddCustomPreferenceFieldsets'
				),

				array(
					'page' => '/system/preferences/',
					'delegate' => 'Save',
					'callback' => 'dSave'
				)
			);
		}

		
		/**
		 * Set options on Preferences page.
		 *
		 * @param array $context
		 */
		public function dAddCustomPreferenceFieldsets($context){
			$group = new XMLElement('fieldset');
			$group->setAttribute('class', 'settings');
			$group->appendChild(new XMLElement('legend', __(MULTILINGUAL_UPLOAD_FIELD_NAME)));
	
	
			$label = Widget::Label(__('Consolidate entry data'));
			$label->appendChild(Widget::Input('settings['.MULTILINGUAL_UPLOAD_FIELD_GROUP.'][consolidate]', 'yes', 'checkbox', array('checked' => 'checked')));
	
			$group->appendChild($label);
	
			$group->appendChild(new XMLElement('p', __('Check this field if you want to consolidate database by <b>keeping</b> entry values of removed/old Language Driver language codes. Entry values of current language codes will not be affected.'), array('class' => 'help')));
	
	
			$context['wrapper']->appendChild($group);
		}
		
		/**
		 * Save options from Preferences page
		 *
		 * @param array $context
		 */
		public function dSave($context){
			
			$new_language_codes = FLang::instance()->ld()->getSavedLanguages($context);
			
			$fields = Symphony::Database()->fetch('SELECT `field_id` FROM `tbl_fields_multilingualupload`');
	
			if ($fields) {
				// Foreach field check multilanguage values foreach language
				foreach ($fields as $field) {
					$entries_table = 'tbl_entries_data_'.$field["field_id"];
	
					try{
						$show_columns = Symphony::Database()->fetch("SHOW COLUMNS FROM `{$entries_table}` LIKE 'file-%'");
					}
					catch(DatabaseException $dbe){
						// Field doesn't exist. Better remove it's settings
						Symphony::Database()->query("DELETE FROM `tbl_fields_multilingualupload` WHERE `field_id` = ".$field["field_id"].";");
						continue;
					}
					
					$columns = array();
	
					if ($show_columns) {
						foreach ($show_columns as $column) {
							$language_code = substr($column['Field'], strlen($column['Field'])-2);
	
							// If not consolidate option AND column language_code not in supported languages codes -> Drop Column
							if ( ($_POST['settings'][MULTILINGUAL_UPLOAD_FIELD_GROUP]['consolidate'] !== 'yes') && !in_array($language_code, $new_language_codes)) {
								Symphony::Database()->query("ALTER TABLE  `{$entries_table}` DROP COLUMN `file-{$language_code}`");
								Symphony::Database()->query("ALTER TABLE  `{$entries_table}` DROP COLUMN `size-{$language_code}`");
								Symphony::Database()->query("ALTER TABLE  `{$entries_table}` DROP COLUMN `mimetype-{$language_code}`");
								Symphony::Database()->query("ALTER TABLE  `{$entries_table}` DROP COLUMN `meta-{$language_code}`");
							} else {
								$columns[] = $column['Field'];
							}
						}
					}
	
					// Add new fields
					foreach ($new_language_codes as $language_code) {
						// If columna language_code dosen't exist in the language drop columns
	
						if (!in_array('file-'.$language_code, $columns)) {
							Symphony::Database()->query("ALTER TABLE  `{$entries_table}` ADD COLUMN `file-{$language_code}` varchar(255) default NULL");
							Symphony::Database()->query("ALTER TABLE  `{$entries_table}` ADD COLUMN `size-{$language_code}` int(11) unsigned NULL");
							Symphony::Database()->query("ALTER TABLE  `{$entries_table}` ADD COLUMN `mimetype-{$language_code}` varchar(50) default NULL");
							Symphony::Database()->query("ALTER TABLE  `{$entries_table}` ADD COLUMN `meta-{$language_code}` varchar(255) default NULL");
						}
					}
	
				}
			}
		}
		
	}
