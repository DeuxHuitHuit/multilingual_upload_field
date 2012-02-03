<?php

	if (!defined('__IN_SYMPHONY__')) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');
	
	
	
	class extension_multilingual_upload_field extends Extension {

		public function about() {
			return array(
				'name'			=> 'Field: Multilingual File Upload',
				'version'		=> '1.2',
				'release-date'	=> '2012-02-01',
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
				 `unique` ENUM('yes','no') DEFAULT 'yes',
				 `use_def_lang_vals` ENUM('yes','no') DEFAULT 'yes',
				  PRIMARY KEY (`id`),
				  KEY `field_id` (`field_id`)
				) ENGINE=MyISAM;"
			);
		}
		
		public function uninstall() {
			Symphony::Database()->query("DROP TABLE `tbl_fields_multilingualupload`");
		}
		
		
		
		public function getSubscribedDelegates(){
			return array(
				array(
					'page' => '/backend/',
					'delegate' => 'InitaliseAdminPageHead',
					'callback' => 'dInitaliseAdminPageHead'
				),
				
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
		 * Add necessary assets to content pages head
		 */
		public function dInitaliseAdminPageHead() {
			$callback = Administration::instance()->getPageCallback();
			
			if (
				(
					($callback['driver'] == 'publish')
					&& ( $callback['context']['page'] == 'new' || $callback['context']['page'] == 'edit')
				)
				|| (
					strpos('/extension/custompreferences/preferences/', $callback['pageroot']) !== false
				)
			) {
				Administration::instance()->Page->addScriptToHead(URL . '/extensions/multilingual_upload_field/assets/multilingual_upload.content.js', 10251841, false);
				Administration::instance()->Page->addStylesheetToHead(URL . '/extensions/multilingual_upload_field/assets/multilingual_upload.content.css', "screen");
			}
		}
		
		/**
		 * Set options on Preferences page.
		 *
		 * @param array $context
		 */
		public function dAddCustomPreferenceFieldsets($context){
			$group = new XMLElement('fieldset');
			$group->setAttribute('class', 'settings');
			$group->appendChild(new XMLElement('legend', __('Multilingual Upload Field')));
	
	
			$label = Widget::Label(__('Consolidate entry data'));
			$label->appendChild(Widget::Input('settings[multilingual_upload][consolidate]', 'yes', 'checkbox', array('checked' => 'checked')));
	
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
	
					$show_columns = Symphony::Database()->fetch("SHOW COLUMNS FROM `{$entries_table}` LIKE 'file-%'");
					$columns = array();
	
					if ($show_columns) {
						foreach ($show_columns as $column) {
							$language_code = substr($column['Field'], strlen($column['Field'])-2);
	
							// If not consolidate option AND column language_code not in supported languages codes -> Drop Column
							if ( ($_POST['settings']['multilingual_upload']['consolidate'] !== 'yes') && !in_array($language_code, $new_language_codes)) {
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
						// If columna language_code dosen't exist in the laguange drop columns
	
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
