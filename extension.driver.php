<?php

	if( !defined('__IN_SYMPHONY__') ) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');



	define_safe(MUF_NAME, 'Field: Multilingual Upload');
	define_safe(MUF_GROUP, 'multilingual_upload_field');



	class Extension_Multilingual_Upload_Field extends Extension
	{
		const FIELD_TABLE = 'tbl_fields_multilingual_upload';

		protected static $assets_loaded = false;



		/*------------------------------------------------------------------------------------------------*/
		/*  Installation  */
		/*------------------------------------------------------------------------------------------------*/

		public function install(){
			return Symphony::Database()->query(sprintf(
				"CREATE TABLE `%s` (
					`id` int(11) unsigned NOT NULL auto_increment,
					`field_id` int(11) unsigned NOT NULL,
					`destination` varchar(255) NOT NULL,
					`validator` varchar(50),
					`unique` enum('yes','no') default 'yes',
					`def_ref_lang` enum('yes','no') default 'yes',
					PRIMARY KEY (`id`),
					KEY `field_id` (`field_id`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;",
				self::FIELD_TABLE
			));
		}

		public function update($previous_version){
			if( version_compare($previous_version, '1.2', '<') ){
				Symphony::Database()->query("ALTER TABLE `tbl_fields_multilingualupload` ADD COLUMN `def_ref_lang` ENUM('yes','no') DEFAULT 'yes'");
				Symphony::Database()->query("UPDATE `tbl_fields_multilingualupload` SET `def_ref_lang` = 'no'");
			}

			if( version_compare($previous_version, '1.6', '<') ){
				Symphony::Database()->query(sprintf(
					"RENAME TABLE `tbl_fields_multilingualupload` TO `%s`;",
					self::FIELD_TABLE
				));
			}

			return true;
		}

		public function uninstall(){
			try{
				Symphony::Database()->query(sprintf(
					"DROP TABLE `%s`",
					self::FIELD_TABLE
				));
			}
			catch( DatabaseException $dbe ){
				// table deosn't exist
			}

			return true;
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
			$fields = Symphony::Database()->fetch(sprintf(
				'SELECT `field_id` FROM `%s`',
				self::FIELD_TABLE
			));

			if( is_array($fields) && !empty($fields) ){
				$consolidate = $context['context']['settings'][MUF_GROUP]['consolidate'];

				// Foreach field check multilanguage values foreach language
				foreach( $fields as $field ){
					$entries_table = 'tbl_entries_data_'.$field["field_id"];

					try{
						$show_columns = Symphony::Database()->fetch(sprintf(
							"SHOW COLUMNS FROM `%s` LIKE 'file-%%'",
							$entries_table
						));
					}
					catch( DatabaseException $dbe ){
						// Field doesn't exist. Better remove it's settings
						Symphony::Database()->query(sprintf(
							"DELETE FROM `%s` WHERE `field_id` = '%s';",
							self::FIELD_TABLE, $field["field_id"]
						));
						continue;
					}

					$columns = array();

					// Remove obsolete fields
					if( is_array($show_columns) && !empty($show_columns) )

						foreach( $show_columns as $column ){
							$lc = substr($column['Field'], strlen($column['Field']) - 2);

							// If not consolidate option AND column lang_code not in supported languages codes -> Drop Column
							if( ($consolidate !== 'yes') && !in_array($lc, $context['new_langs']) )
								Symphony::Database()->query(sprintf(
									'ALTER TABLE `%1$s`
										DROP COLUMN `file-%2$s`,
										DROP COLUMN `size-%2$s`,
										DROP COLUMN `mimetype-%2$s`,
										DROP COLUMN `meta-%2$s`;',
									$entries_table, $lc
								));
							else
								$columns[] = $column['Field'];
						}

					// Add new fields
					foreach( $context['new_langs'] as $lc )

						if( !in_array('file-'.$lc, $columns) )
							Symphony::Database()->query(sprintf(
								'ALTER TABLE `%1$s`
									ADD COLUMN `file-%2$s` varchar(255) default NULL,
									ADD COLUMN `size-%2$s` int(11) unsigned NULL,
									ADD COLUMN `mimetype-%2$s` varchar(50) default NULL,
									ADD COLUMN `meta-%2$s` varchar(255) default NULL;',
								$entries_table, $lc
							));
				}
			}
		}



		/*------------------------------------------------------------------------------------------------*/
		/*  Public utilities  */
		/*------------------------------------------------------------------------------------------------*/

		public static function appendAssets(){
			if( self::$assets_loaded === false
				&& class_exists('Administration')
				&& Administration::instance() instanceof Administration
				&& Administration::instance()->Page instanceof HTMLPage ){

				self::$assets_loaded = true;

				$page = Administration::instance()->Page;

				$page->addScriptToHead(URL.'/extensions/'.MUF_GROUP.'/assets/'.MUF_GROUP.'.publish.js', null, false);
			}
		}
	}
