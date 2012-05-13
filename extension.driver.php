<?php

	if( !defined('__IN_SYMPHONY__') ) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');



	require_once(EXTENSIONS.'/entry_url_field/extension.driver.php');



	define_safe(MEU_NAME, 'Field: Multilingual Entry URL');
	define_safe(MEU_GROUP, 'multilingual_entry_url');



	class Extension_Multilingual_Entry_URL extends Extension_Entry_URL_Field
	{

		protected $assets_loaded = false;



		/*------------------------------------------------------------------------------------------------*/
		/*  Installation  */
		/*------------------------------------------------------------------------------------------------*/

		public function uninstall(){
			Symphony::Database()->query("DROP TABLE `tbl_multilingual_entry_url`");
		}

		public function install(){
			Symphony::Database()->query("
				CREATE TABLE IF NOT EXISTS `tbl_fields_multilingual_entry_url` (
					`id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
					`field_id` INT(11) UNSIGNED NOT NULL,
					`anchor_label` VARCHAR(255) DEFAULT NULL,
					`expression` VARCHAR(255) DEFAULT NULL,
					`new_window` ENUM('yes', 'no') DEFAULT 'no',
					`hide` ENUM('yes', 'no') DEFAULT 'no',
					PRIMARY KEY (`id`),
					KEY `field_id` (`field_id`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
			");

			return true;
		}



		/*------------------------------------------------------------------------------------------------*/
		/*  Delegates  */
		/*------------------------------------------------------------------------------------------------*/

		public function getSubscribedDelegates(){
			$delegates = parent::getSubscribedDelegates();

			$delegates = array_merge($delegates, array(
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
			));

			return $delegates;
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
			$fields = Symphony::Database()->fetch('SELECT `field_id` FROM `tbl_fields_multilingual_entry_url`');

			if( $fields ){
				// Foreach field check multilanguage values foreach language
				foreach( $fields as $field ){
					$entries_table = 'tbl_entries_data_'.$field["field_id"];

					$show_columns = Symphony::Database()->fetch("SHOW COLUMNS FROM `{$entries_table}` LIKE 'value-%'");
					$columns = array();

					if( $show_columns ){
						foreach( $show_columns as $column ){
							$lc = substr($column['Field'], strlen($column['Field']) - 2);

							// If not consolidate option AND column lang_code not in supported languages codes -> Drop Column
							if( ($_POST['settings'][MEU_GROUP]['consolidate'] !== 'yes') && !in_array($lc, $context['new_langs']) ){
								Symphony::Database()->query("ALTER TABLE  `{$entries_table}` DROP COLUMN `value-{$lc}`");
							} else{
								$columns[] = $column['Field'];
							}
						}
					}

					// Add new fields
					foreach( $context['new_langs'] as $lc ){
						// If column lang_code dosen't exist in the laguange drop columns

						if( !in_array('value-'.$lc, $columns) ){
							Symphony::Database()->query("ALTER TABLE  `{$entries_table}` ADD COLUMN `value-{$lc}` TEXT DEFAULT NULL");
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

				// multilingual stuff
				$fl_assets = URL.'/extensions/frontend_localisation/assets/frontend_localisation.multilingual_tabs';
				$page->addStylesheetToHead($fl_assets.'.css', 'screen', null, false);
				$page->addScriptToHead($fl_assets.'_init.js', null, false);
			}
		}
	}
