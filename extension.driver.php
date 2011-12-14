<?php
	
	if (!defined('__IN_SYMPHONY__')) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');
	
	
	
	define_safe(MULTILINGUAL_ENTRY_URL_NAME, 'Field: Multilingual Entry URL');
	define_safe(MULTILINGUAL_ENTRY_URL_GROUP, 'multilingual_entry_url');
	
	
	
	class Extension_Multilingual_Entry_URL extends Extension_Entry_URL_Field {
		
		protected $assets_loaded = false;
		
		public function about() {
			return array(
				'name' => MULTILINGUAL_ENTRY_URL_NAME,
				'version' => '1.0beta',
				'release-date' => '2011-12-14',
				'author' => array(
					array(
						'name' => 'Xander Group',
						'email' => 'symphonycms@xandergroup.ro',
						'website' => 'www.xanderadvertising.com'
					),
					array(
						'name' => 'Vlad Ghita',
						'email' => 'vlad.ghita@xandergroup.ro',
					),
				),
				'description' => 'Add a hyperlink in the backend to view an entry page/URL in the frontend'
			);
		}
		
		public function uninstall() {
			Symphony::Database()->query("DROP TABLE `tbl_multilingual_entry_url`");
		}
		
		public function install() {
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
				)
			");
			
			return true;
		}
		
		
		public function getSubscribedDelegates() {
			$delegates = parent::getSubscribedDelegates();
			
			$delegates = array_merge($delegates, array(
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
			));
			
			return $delegates;
		}
		
		
		
		public function appendAssets() {
			if( $this->assets_loaded === false ){
				Administration::instance()->Page->addScriptToHead(URL . '/extensions/' . MULTILINGUAL_ENTRY_URL_GROUP . '/assets/multilingual_entry_url.content.js', 10251842, false);
				Administration::instance()->Page->addStylesheetToHead(URL . '/extensions/' . MULTILINGUAL_ENTRY_URL_GROUP . '/assets/multilingual_entry_url.content.css', "screen");
				
				$this->assets_loaded = true;
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
			$group->appendChild(new XMLElement('legend', MULTILINGUAL_ENTRY_URL_NAME));
		
		
			$label = Widget::Label(__('Consolidate entry data'));
			$label->appendChild(Widget::Input('settings['.MULTILINGUAL_ENTRY_URL_GROUP.'][consolidate]', 'yes', 'checkbox', array('checked' => 'checked')));
		
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
			$new_language_codes = FrontendLanguage::instance()->savedLanguages($context);
		
			$fields = Symphony::Database()->fetch('SELECT `field_id` FROM `tbl_fields_multilingual_entry_url`');
		
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
							if ( ($_POST['settings'][''.MULTILINGUAL_ENTRY_URL_GROUP.'']['consolidate'] !== 'yes') && !in_array($language_code, $new_language_codes)) {
								Symphony::Database()->query("ALTER TABLE  `{$entries_table}` DROP COLUMN `value-{$language_code}`");
							} else {
								$columns[] = $column['Field'];
							}
						}
					}
		
					// Add new fields
					foreach ($new_language_codes as $language_code) {
						// If columna language_code dosen't exist in the laguange drop columns
		
						if (!in_array('file-'.$language_code, $columns)) {
							Symphony::Database()->query("ALTER TABLE  `{$entries_table}` ADD COLUMN `value-{$language_code}` TEXT DEFAULT NULL");
						}
					}
				}
			}
		}
	}