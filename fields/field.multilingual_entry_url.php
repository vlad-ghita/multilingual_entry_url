<?php
	
	if (!defined('__IN_SYMPHONY__')) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');
	
	require_once(EXTENSIONS . '/entry_url_field/fields/field.entry_url.php');
	require_once(EXTENSIONS . '/page_lhandles/lib/class.PLHManagerURL.php');
	require_once(EXTENSIONS . '/frontend_localisation/lib/class.FLang.php');
	
	
	
	class FieldMultilingual_Entry_URL extends FieldEntry_URL {
		protected $_driver;
		
		public function __construct(&$parent) {
			parent::__construct($parent);
			
			$this->_name = 'Multilingual Entry URL';
			$this->_driver = Symphony::ExtensionManager()->create('multilingual_entry_url');
		}
		
		public function createTable() {
			$query = "CREATE TABLE IF NOT EXISTS `tbl_entries_data_{$this->get('id')}` (
				`id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
				`entry_id` INT(11) UNSIGNED NOT NULL,
				`value` TEXT DEFAULT NULL,";
			
			foreach( FLang::instance()->ld()->languageCodes() as $language_code ){
				$query .= "`value-{$language_code}` TEXT DEFAULT NULL,";
			}
			
			$query .= "PRIMARY KEY (`id`),
				KEY `entry_id` (`entry_id`),
				FULLTEXT KEY `value` (`value`)
				) ENGINE=MyISAM;";
			
			return Symphony::Database()->query($query);
		}
		
	/*-------------------------------------------------------------------------
		Publish:
	-------------------------------------------------------------------------*/
		
		public function displayPublishPanel(&$wrapper, $data = null, $flagWithError = null, $prefix = null, $postfix = null) {
			if ($this->get('hide') == 'yes') return;
			
			$this->_driver->appendAssets();
			
			$wrapper->setAttribute('class', $wrapper->getAttribute('class').' field-multilingual_entry_url field-multilingual');
			$container = new XMLElement('div', null, array('class' => 'container'));
			
			
			/* Label */
			
			$label = Widget::Label($this->get('label'));
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
			
			
			/* Links */
			
			$callback = Administration::instance()->getPageCallback();
			
			foreach( $language_codes as $language_code ){
				$div = new XMLElement('div', NULL, array('class' => 'tab-panel tab-'.$language_code));
			
				$span = new XMLElement('span', NULL, array('class' => 'frame'));
				
				$anchor = Widget::Anchor(
					$this->get('anchor_label'),
					$data['value-'.$language_code]
				);
				
				if ($this->get('new_window') == 'yes') {
					$anchor->setAttribute('target', '_blank');
				}
				
				if (is_null($callback['context']['entry_id'])) {
					$span->setValue(__('The link will be created after saving this entry'));
					$span->setAttribute('class', 'inactive');
				} else {
					$span->appendChild($anchor);
				}
			
				$div->appendChild($span);
				$container->appendChild($div);
			}
			
			
			if($flagWithError != NULL){
				$wrapper->appendChild(Widget::wrapFormElementWithError($container, $flagWithError));
			}
			else{
				$wrapper->appendChild($container);
			}
		}
		
	/*-------------------------------------------------------------------------
		Input:
	-------------------------------------------------------------------------*/
		
		public function processRawFieldData($data, &$status, $simulate = false, $entry_id = null) {
			$result = parent::processRawFieldData($data, $status, $simulate, $entry_id);
			
			foreach( FLang::instance()->ld()->languageCodes() as $language_code ){
				$result['value-'.$language_code] = null;
			}
			
			return $result;
		}
		
	/*-------------------------------------------------------------------------
		Output:
	-------------------------------------------------------------------------*/
		
		public function appendFormattedElement(&$wrapper, $data, $encode = false) {
			if (!self::$ready) return;
			
			$language_code = FLang::instance()->ld()->languageCode();
			
			if( empty($language_code) ){
				$language_code = FLang::instance()->referenceLanguage();
			}
			
			$value = empty($language_code) ? $data['value'] : $data['value-'.$language_code];
			
			$element = new XMLElement($this->get('element_name'));
			$element->setValue(General::sanitize($value));
			$wrapper->appendChild($element);
		}
		
		public function prepareTableValue($data, XMLElement $link = null) {
			if (empty($data)) return;
			
			$language_code = Lang::get();
			
			$value = empty($language_code) ? $data['value'] : $data['value-'.$language_code];
			
			$anchor =  Widget::Anchor($this->get('anchor_label'), $value);
			if ($this->get('new_window') == 'yes') $anchor->setAttribute('target', '_blank');
			return $anchor->generate();
		}
		
	/*-------------------------------------------------------------------------
		Compile:
	-------------------------------------------------------------------------*/
		
		/**
		 * @param Entry $entry
		 */
		public function compile($entry) {
			self::$ready = false;
			
			$xpath = $this->_driver->getXPath($entry);
			
			self::$ready = true;
			
			$entry_id = $entry->get('id');
			$field_id = $this->get('id');
			$expression = $this->get('expression');
			$values = array();
			
			foreach( FLang::instance()->ld()->languageCodes() as $language_code ){
				$replacements = array();
				$mathces = array();
				
				// Find queries:
				preg_match_all('/\{[^\}]+\}/', $expression, $matches);
				
				// Find replacements:
				foreach ($matches[0] as $match) {
					$new_match = str_replace('$language_code', "'$language_code'", $match);
					
					$results = @$xpath->query(trim($new_match, '{}'));
				
					if ($results->length) {
						$replacements[$match] = $results->item(0)->nodeValue;
					} else {
						$replacements[$match] = '';
					}
				}
				
				// Apply replacements:
				$url = str_replace(
						array_keys($replacements),
						array_values($replacements),
						$expression
				);
				
				$values['value-'.$language_code] = '/' . $language_code . PLHManagerURL::instance()->sym2lang($url, $language_code);
			}
			
			$values['value'] = $values['value-'.FLang::instance()->referenceLanguage()];
			
			
			// Save:
			$this->Database->update(
				$values,
				"tbl_entries_data_{$field_id}",
				"`entry_id` = '{$entry_id}'"
			);
		}
		
	}