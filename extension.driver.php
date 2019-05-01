<?php

	if( !defined('__IN_SYMPHONY__') ) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');



	define_safe('MUF_NAME', 'Field: Multilingual Upload');
	define_safe('MUF_GROUP', 'multilingual_upload_field');



	class Extension_Multilingual_Upload_Field extends Extension
	{
		const FIELD_TABLE = 'tbl_fields_multilingual_upload';

		protected static $assets_loaded = false;
		protected static $assets_settings_loaded = false;

		/*------------------------------------------------------------------------------------------------*/
		/*  Installation  */
		/*------------------------------------------------------------------------------------------------*/

		public function install()
		{
			return Symphony::Database()
				->create(self::FIELD_TABLE)
				->ifNotExists()
				->fields([
					'id' => [
						'type' => 'int(11)',
						'auto' => true,
					],
					'field_id' => 'int(11)',
					'destination' => 'varchar(255)',
					'validator' => 'varchar(255)',
					'unique' => [
						'type' => 'enum',
						'values' => ['yes','no'],
						'default' => 'yes',
					],
					'default_main_lang' => [
						'type' => 'enum',
						'values' => ['yes','no'],
						'default' => 'no',
					],
					'required_languages' => [
						'type' => 'varchar(255)',
						'null' => true,
					],
				])
				->keys([
					'id' => 'primary',
					'field_id' => 'key',
				])
				->execute()
				->success();
		}

		public function update($previous_version = false)
		{
			if(version_compare($previous_version, '1.2', '<')) {
				Symphony::Database()
					->alter(self::FIELD_TABLE)
					->add([
						'def_ref_lang' => [
							'type' => 'enum',
							'values' => ['yes','no'],
							'default' => 'yes',
						],
					])
					->execute()
					->success();

				Symphony::Database()
					->update(self::FIELD_TABLE)
					->set([
						'def_ref_lang' => 'no',
					])
					->execute()
					->success();
			}

			if(version_compare($previous_version, '1.6', '<')) {
				Symphony::Database()
					->rename('tbl_fields_multilingualupload')
					->to(self::FIELD_TABLE)
					->execute()
					->success();
			}

			if(version_compare($previous_version, '1.6.1', '<')) {
				Symphony::Database()
					->alter(self::FIELD_TABLE)
					->modify([
						'validator' => 'varchar(255)'
					])
					->execute()
					->success();
			}

			if (version_compare($previous_version, '2.0.0', '<')) {
				Symphony::Database()
					->alter(self::FIELD_TABLE)
					->change('def_ref_lang', [
						'default_main_lang' => [
							'type' => 'enum',
							'values' => ['yes', 'no'],
							'default' => 'no',
						],
					])
					->add([
						'required_languages' => [
							'type' => 'varchar(255)',
							'null' => true,
						],
					])
					->execute()
					->success();
			}

			return true;
		}

		public function uninstall()
		{
			return Symphony::Database()
				->drop(self::FIELD_TABLE)
				->ifExists()
				->execute()
				->success();
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
					'page'     => '/system/preferences/',
					'delegate' => 'Save',
					'callback' => 'dSave'
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
			$label->prependChild(Widget::Input('settings['.MUF_GROUP.'][consolidate]', 'yes', 'checkbox', array('checked' => 'checked')));
			$group->appendChild($label);
			$group->appendChild(new XMLElement('p', __('Check this field if you want to consolidate database by <b>keeping</b> entry values of removed/old Language Driver language codes. Entry values of current language codes will not be affected.'), array('class' => 'help')));

			$context['wrapper']->appendChild($group);
		}

		/**
		 * Edits the preferences to be saved
		 *
		 * @param array $context
		 */
		public function dSave($context) {
			// prevent the saving of the values
			unset($context['settings'][MUF_GROUP]);
		}

		/**
		 * Save options from Preferences page
		 *
		 * @param array $context
		 */
		public function dFLSavePreferences($context){
			$fields = Symphony::Database()
				->select(['field_id'])
				->from(self::FIELD_TABLE)
				->execute()
				->rows();

			if( is_array($fields) && !empty($fields) ){
				$consolidate = $context['context']['settings'][MUF_GROUP]['consolidate'];

				// Foreach field check multilanguage values foreach language
				foreach( $fields as $field ){
					$entries_table = 'tbl_entries_data_'.$field["field_id"];

					try{
						$show_columns = Symphony::Database()
							->showColumns()
							->from($entries_table)
							->like('file-%%')
							->execute()
							->rows();
					}
					catch( DatabaseException $dbe ){
						Symphony::Database()
							->delete(self::FIELD_TABLE)
							->where(['field_id' => $field['field_id']])
							->execute()
							->success();

						continue;
					}

					$columns = array();

					// Remove obsolete fields
					if( is_array($show_columns) && !empty($show_columns) )

						foreach( $show_columns as $column ){
							$lc = substr($column['Field'], strlen($column['Field']) - 2);

							// If not consolidate option AND column lang_code not in supported languages codes -> Drop Column
							if( ($consolidate !== 'yes') && !in_array($lc, $context['new_langs']) )
								Symphony::Database()
									->alter($entries_table)
									->drop([
										'file-' . $lc,
										'size-' . $lc,
										'mimetype-' . $lc,
										'meta-' . $lc,
									])
									->execute()
									->success();
							else
								$columns[] = $column['Field'];
						}

					// Add new fields
					foreach( $context['new_langs'] as $lc ) {
						if( !in_array('file-'.$lc, $columns) ) {
							Symphony::Database()
								->alter($entries_table)
								->add([
									'file-' . $lc => [
										'type' => 'varchar(255)',
										'null' => true,
									],
									'size-' . $lc => [
										'type' => 'int(11)',
										'null' => true,
									],
									'mimetype-' . $lc => [
										'type' => 'varchar(50)',
										'null' => true,
									],
									'meta-' . $lc => [
										'type' => 'varchar(255)',
										'null' => true,
									],
								])
								->execute()
								->success();
						}
					}
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

		public static function appendSettingsAssets(){
			if( self::$assets_settings_loaded === false
				&& class_exists('Administration')
				&& Administration::instance() instanceof Administration
				&& Administration::instance()->Page instanceof HTMLPage ){

				self::$assets_settings_loaded = true;

				$page = Administration::instance()->Page;

				$page->addScriptToHead(URL.'/extensions/'.MUF_GROUP.'/assets/'.MUF_GROUP.'.settings.js', null, false);
			}
		}
	}
