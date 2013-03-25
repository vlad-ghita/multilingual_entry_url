<?php

	if( !defined('__IN_SYMPHONY__') ) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');



	require_once(EXTENSIONS.'/entry_url_field/extension.driver.php');



	define_safe(MEU_NAME, 'Field: Multilingual Entry URL');
	define_safe(MEU_GROUP, 'multilingual_entry_url');



	class Extension_Multilingual_Entry_URL extends Extension
	{
		const FIELD_TABLE = 'tbl_fields_multilingual_entry_url';

		protected static $assets_loaded = false;

		protected static $fields = array();



		/*------------------------------------------------------------------------------------------------*/
		/*  Installation  */
		/*------------------------------------------------------------------------------------------------*/

		public function install(){
			return Symphony::Database()->query(sprintf(
				"CREATE TABLE `%s` (
					`id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
					`field_id` INT(11) UNSIGNED NOT NULL,
					`anchor_label` VARCHAR(255) DEFAULT NULL,
					`expression` VARCHAR(255) DEFAULT NULL,
					`new_window` ENUM('yes', 'no') DEFAULT 'no',
					`hide` ENUM('yes', 'no') DEFAULT 'no',
					PRIMARY KEY (`id`),
					KEY `field_id` (`field_id`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;",
				self::FIELD_TABLE
			));
		}

		public function update($prev_version){
			if( version_compare($prev_version, '1.4', '<') ){
				$fields = Symphony::Database()->fetch(sprintf("SELECT `field_id`,`anchor_label` FROM `%s`", self::FIELD_TABLE));

				foreach( $fields as $field ){
					$entries_table = 'tbl_entries_data_'.$field["field_id"];

					$query = "ALTER TABLE `{$entries_table}` ADD COLUMN `label` TEXT DEFAULT NULL";
					foreach( FLang::getLangs() as $lc ){
						$query .= sprintf(", ADD COLUMN `label-%s` TEXT DEFAULT null", $lc);
					}
					Symphony::Database()->query($query);

					$values = array();
					foreach( FLang::getLangs() as $lc ){
						$values["label-{$lc}"] = $field['anchor_label'];
					}
					Symphony::Database()->update($values, $entries_table);
				}
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
		}



		/*------------------------------------------------------------------------------------------------*/
		/*  Delegates  */
		/*------------------------------------------------------------------------------------------------*/

		public function getSubscribedDelegates(){
			return array(
				array(
					'page'		=> '/publish/new/',
					'delegate'	=> 'EntryPostCreate',
					'callback'	=> 'compileBackendFields'
				),
				array(
					'page'		=> '/publish/edit/',
					'delegate'	=> 'EntryPostEdit',
					'callback'	=> 'compileBackendFields'
				),
				array(
					'page'		=> '/frontend/',
					'delegate'	=> 'EventPostSaveFilter',
					'callback'	=> 'compileFrontendFields'
				),

				array(
					'page' => '/system/preferences/',
					'delegate' => 'AddCustomPreferenceFieldsets',
					'callback' => 'dAddCustomPreferenceFieldsets'
				),
				array(
					'page' => '/extensions/frontend_localisation/',
					'delegate' => 'FLSavePreferences',
					'callback' => 'dFLSavePreferences'
				)
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
			$group->appendChild(new XMLElement('legend', MEU_NAME));


			$label = Widget::Label(__('Consolidate entry data'));
			$label->appendChild(Widget::Input('settings['.MEU_GROUP.'][consolidate]', 'yes', 'checkbox', array('checked' => 'checked')));

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
				$consolidate = $context['context']['settings'][MIU_GROUP]['consolidate'];

				// Foreach field check multilanguage values foreach language
				foreach( $fields as $field ){
					$entries_table = 'tbl_entries_data_'.$field["field_id"];

					try{
						$show_columns = Symphony::Database()->fetch(sprintf(
							"SHOW COLUMNS FROM `%s` LIKE 'value-%%'",
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
								Symphony::Database()->query(
									"ALTER TABLE `{$entries_table}`
										DROP COLUMN `value-{$lc}`,
										DROP COLUMN `label-{$lc}`;"
								);
							else
								$columns[] = $column['Field'];
						}

					// Add new fields
					foreach( $context['new_langs'] as $lc )

						if( !in_array('value-'.$lc, $columns) )
							Symphony::Database()->query(
								"ALTER TABLE `{$entries_table}`
									ADD COLUMN `value-{$lc}` varchar(255) default NULL,
									ADD COLUMN `label-{$lc}` TEXT DEFAULT null;
							");
				}
			}
		}



		/*------------------------------------------------------------------------------------------------*/
		/*  Utilities */
		/*------------------------------------------------------------------------------------------------*/

		public function getXPath($entry) {
			$entry_xml = new XMLElement('entry');
			$data = $entry->getData();

			$entry_xml->setAttribute('id', $entry->get('id'));

			$associated = $entry->fetchAllAssociatedEntryCounts();

			if (is_array($associated) and !empty($associated)) {
				foreach ($associated as $section => $count) {
					$related_section = SectionManager::fetch($section);
					$entry_xml->setAttribute($related_section->get('handle'), (string)$count);
				}
			}

			// Add fields:
			foreach ($data as $field_id => $values) {
				if (empty($field_id)) continue;

				$field = FieldManager::fetch($field_id);
				$field->appendFormattedElement($entry_xml, $values, false, 'all-languages');
			}

			$xml = new XMLElement('data');
			$xml->appendChild($entry_xml);

			$dom = new DOMDocument();
			$dom->loadXML($xml->generate(true));

			return new DOMXPath($dom);
		}



		/*------------------------------------------------------------------------------------------------*/
		/*  Fields */
		/*------------------------------------------------------------------------------------------------*/

		public function registerField($field) {
			self::$fields[] = $field;
		}

		public function compileBackendFields($context) {
			foreach (self::$fields as $field) {
				$field->compile($context['entry']);
			}
		}

		public function compileFrontendFields($context) {
			foreach (self::$fields as $field) {
				$field->compile($context['entry']);
			}
		}
	}
