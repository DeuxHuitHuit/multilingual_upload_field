<?php

	if( !defined('__IN_SYMPHONY__') ) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');



	define_safe(MUF_NAME, 'Field: Multilingual File Upload');
	define_safe(MUF_GROUP, 'multilingual_upload_field');



	class extension_multilingual_upload_field extends Extension
	{

		protected $assets_loaded = false;



		/*------------------------------------------------------------------------------------------------*/
		/*  Installation  */
		/*------------------------------------------------------------------------------------------------*/

		public function install(){
			return Symphony::Database()->query("
				CREATE TABLE `tbl_fields_multilingualupload` (
					`id` int(11) unsigned NOT NULL auto_increment,
					`field_id` int(11) unsigned NOT NULL,
					`destination` varchar(255) NOT NULL,
					`validator` varchar(50),
					`unique` enum('yes','no') default 'yes',
					`def_ref_lang` enum('yes','no') default 'yes',
					PRIMARY KEY (`id`),
					KEY `field_id` (`field_id`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
			");
		}

		public function update($previous_version){
			if( version_compare($previous_version, '1.2', '<') ){
				Symphony::Database()->query("ALTER TABLE `tbl_fields_multilingualupload` ADD COLUMN `def_ref_lang` ENUM('yes','no') DEFAULT 'yes'");
				Symphony::Database()->query("UPDATE `tbl_fields_multilingualupload` SET `def_ref_lang` = 'no'");
			}

			return true;
		}

		public function uninstall(){
			Symphony::Database()->query("DROP TABLE `tbl_fields_multilingualupload`");
		}



		/*------------------------------------------------------------------------------------------------*/
		/*  Delegates  */
		/*------------------------------------------------------------------------------------------------*/

		public function getSubscribedDelegates(){
			return array(
				array(
					'page' => '/system/preferences/',
					'delegate' => 'AddCustomPreferenceFieldsets',
					'callback' => 'dAddCustomPreferenceFieldsets'
				),
				array(
					'page' => '/extensions/frontend_localisation/',
					'delegate' => 'FLSavePreferences',
					'callback' => 'dFLSavePreferences'
				),
			);
		}



		/*------------------------------------------------------------------------------------------------*/
		/*  System preferences  */
		/*------------------------------------------------------------------------------------------------*/

		/**
		 * Display options on Preferences page.
		 *
		 * @param array $context
		 */
		public function dAddCustomPreferenceFieldsets($context){
			$group = new XMLElement('fieldset');
			$group->setAttribute('class', 'settings');
			$group->appendChild(new XMLElement('legend', __(MUF_NAME)));

			$label = Widget::Label(__('Consolidate entry data'));
			$label->appendChild(Widget::Input('settings['.MUF_GROUP.'][consolidate]', 'yes', 'checkbox', array('checked' => 'checked')));
			$group->appendChild($label);
			$group->appendChild(new XMLElement('p', __('Check this field if you want to consolidate database by <b>keeping</b> entry values of removed/old Language Driver language codes. Entry values of current language codes will not be affected.'), array('class' => 'help')));

			$context['wrapper']->appendChild($group);
		}

		/**
		 * Save options from Preferences page
		 *
		 * @param array $context
		 */
		public function dFLSavePreferences($context){
			$fields = Symphony::Database()->fetch('SELECT `field_id` FROM `tbl_fields_multilingualupload`');

			if( $fields ){
				// Foreach field check multilanguage values foreach language
				foreach( $fields as $field ){
					$entries_table = 'tbl_entries_data_'.$field["field_id"];

					try{
						$show_columns = Symphony::Database()->fetch("SHOW COLUMNS FROM `{$entries_table}` LIKE 'file-%';");
					}
					catch( DatabaseException $dbe ){
						// Field doesn't exist. Better remove it's settings
						Symphony::Database()->query("DELETE FROM `tbl_fields_multilingualupload` WHERE `field_id` = {$field["field_id"]};");
						continue;
					}

					$columns = array();

					if( $show_columns ){
						foreach( $show_columns as $column ){
							$lc = substr($column['Field'], strlen($column['Field']) - 2);

							// If not consolidate option AND column lang_code not in supported languages codes -> Drop Column
							if( ($_POST['settings'][MUF_GROUP]['consolidate'] !== 'yes') && !in_array($lc, $context['new_langs']) ){
								Symphony::Database()->query("ALTER TABLE  `{$entries_table}` DROP COLUMN `file-{$lc}`");
								Symphony::Database()->query("ALTER TABLE  `{$entries_table}` DROP COLUMN `size-{$lc}`");
								Symphony::Database()->query("ALTER TABLE  `{$entries_table}` DROP COLUMN `mimetype-{$lc}`");
								Symphony::Database()->query("ALTER TABLE  `{$entries_table}` DROP COLUMN `meta-{$lc}`");
							} else{
								$columns[] = $column['Field'];
							}
						}
					}

					// Add new fields
					foreach( $context['new_langs'] as $lc ){
						// If column lang_code dosen't exist in the laguange drop columns

						if( !in_array('file-'.$lc, $columns) ){
							Symphony::Database()->query("ALTER TABLE  `{$entries_table}` ADD COLUMN `file-{$lc}` varchar(255) default NULL");
							Symphony::Database()->query("ALTER TABLE  `{$entries_table}` ADD COLUMN `size-{$lc}` int(11) unsigned NULL");
							Symphony::Database()->query("ALTER TABLE  `{$entries_table}` ADD COLUMN `mimetype-{$lc}` varchar(50) default NULL");
							Symphony::Database()->query("ALTER TABLE  `{$entries_table}` ADD COLUMN `meta-{$lc}` varchar(255) default NULL");
						}
					}

				}
			}
		}



		/*------------------------------------------------------------------------------------------------*/
		/*  Utilities  */
		/*------------------------------------------------------------------------------------------------*/

		public function appendAssets(){
			if( $this->assets_loaded === false ){
				$this->assets_loaded = true;

				$page = Administration::instance()->Page;

				$page->addScriptToHead(URL.'/extensions/'.MUF_GROUP.'/assets/'.MUF_GROUP.'.publish.js', null, false);

				// multilingual stuff
				$fl_assets = URL.'/extensions/frontend_localisation/assets/frontend_localisation.multilingual_tabs';
				$page->addStylesheetToHead($fl_assets.'.css', 'screen', null, false);
				$page->addScriptToHead($fl_assets.'_init.js', null, false);
			}
		}
	}
