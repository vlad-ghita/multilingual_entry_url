<?php

	if( !defined('__IN_SYMPHONY__') ) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');



	require_once(EXTENSIONS.'/entry_url_field/fields/field.entry_url.php');
	require_once(EXTENSIONS.'/frontend_localisation/extension.driver.php');
	require_once(EXTENSIONS.'/frontend_localisation/lib/class.FLang.php');
	require_once(EXTENSIONS.'/page_lhandles/lib/class.PLHManagerURL.php');



	class FieldMultilingual_Entry_URL extends FieldEntry_URL
	{

		/*------------------------------------------------------------------------------------------------*/
		/*  Definition  */
		/*------------------------------------------------------------------------------------------------*/

		public function __construct(){
			parent::__construct();

			$this->_name = 'Multilingual Entry URL';
		}

		public function createTable(){
			$query = "
				CREATE TABLE IF NOT EXISTS `tbl_entries_data_{$this->get('id')}` (
					`id` INT(11) UNSIGNED NOT null AUTO_INCREMENT,
					`entry_id` INT(11) UNSIGNED NOT null,
					`value` TEXT DEFAULT null,";

			foreach( FLang::getLangs() as $lc ){
				$query .= sprintf("`value-%s` TEXT DEFAULT null,", $lc);
			}

			$query .= "
					PRIMARY KEY (`id`),
					KEY `entry_id` (`entry_id`),";

			foreach( FLang::getLangs() as $lc ){
				$query .= sprintf('FULLTEXT KEY `value-%1$s` (`value-%1$s`),', $lc);
			}

			$query .= "
					FULLTEXT KEY `value` (`value`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";

			return Symphony::Database()->query($query);
		}



		/*------------------------------------------------------------------------------------------------*/
		/*  Publish  */
		/*------------------------------------------------------------------------------------------------*/

		public function displayPublishPanel(&$wrapper, $data = null, $flagWithError = null, $prefix = null, $postfix = null){
			if( $this->get('hide') === 'yes' ) return;

			Extension_Frontend_Localisation::appendAssets();

			$main_lang = FLang::getMainLang();
			$all_langs = FLang::getAllLangs();
			$langs = FLang::getLangs();

			$wrapper->setAttribute('class', $wrapper->getAttribute('class').' field-multilingual_entry_url field-multilingual');
			$container = new XMLElement('div', null, array('class' => 'container'));


			/*------------------------------------------------------------------------------------------------*/
			/*  Label  */
			/*------------------------------------------------------------------------------------------------*/

			$label = Widget::Label($this->get('label'));
			if( $this->get('required') != 'yes' ) $label->appendChild(new XMLElement('i', __('Optional')));
			$container->appendChild($label);


			/*------------------------------------------------------------------------------------------------*/
			/*  Tabs  */
			/*------------------------------------------------------------------------------------------------*/

			$ul = new XMLElement('ul', null, array('class' => 'tabs'));
			foreach( $langs as $lc ){
				$li = new XMLElement('li', $all_langs[$lc], array('class' => $lc));
				$lc === $main_lang ? $ul->prependChild($li) : $ul->appendChild($li);
			}

			$container->appendChild($ul);


			/*------------------------------------------------------------------------------------------------*/
			/*  Panels  */
			/*------------------------------------------------------------------------------------------------*/

			$callback = Administration::instance()->getPageCallback();
			$entry_id = $callback['context']['entry_id'];

			foreach( $langs as $lc ){
				$div = new XMLElement('div', null, array('class' => 'tab-panel tab-'.$lc));

				$span = new XMLElement('span', null, array('class' => 'frame'));

				if( is_null($entry_id) ){
					$span->setValue(__('The link will be created after saving this entry'));
					$span->setAttribute('class', 'frame inactive');

				} else{
					$anchor = Widget::Anchor($this->get('anchor_label'), (string)$data['value-'.$lc]);

					if( $this->get('new_window') == 'yes' ){
						$anchor->setAttribute('target', '_blank');
					}

					$span->appendChild($anchor);
				}

				$div->appendChild($span);
				$container->appendChild($div);
			}


			/*------------------------------------------------------------------------------------------------*/
			/*  Error  */
			/*------------------------------------------------------------------------------------------------*/

			if( !is_null($flagWithError) ){
				$wrapper->appendChild(Widget::Error($container, $flagWithError));
			}
			else{
				$wrapper->appendChild($container);
			}
		}



		/*------------------------------------------------------------------------------------------------*/
		/*  Input  */
		/*------------------------------------------------------------------------------------------------*/

		public function processRawFieldData($data, &$status, &$message, $simulate = false, $entry_id = null){
			$result = parent::processRawFieldData($data, $status, $message, $simulate, $entry_id);

			foreach( FLang::getLangs() as $lc ){
				$result['value-'.$lc] = null;
			}

			return $result;
		}



		/*------------------------------------------------------------------------------------------------*/
		/*  Output  */
		/*------------------------------------------------------------------------------------------------*/

		public function appendFormattedElement(&$wrapper, $data, $encode = false){
			if( !self::$ready ) return;

			$lang_code = FLang::getLangCode();

			if( empty($lang_code) ){
				$lang_code = FLang::getMainLang();
			}

			$value = empty($lang_code) ? $data['value'] : $data['value-'.$lang_code];

			$element = new XMLElement($this->get('element_name'));
			$element->setValue(General::sanitize($value));
			$wrapper->appendChild($element);
		}

		public function prepareTableValue($data, XMLElement $link = null){
			if( empty($data) ) return;

			$lc = Lang::get();

			if( !FLang::validateLangCode($lc) )
				$lc = FLang::getLangCode();

			$value = empty($lc) ? $data['value'] : $data['value-'.$lc];

			if( is_null($value) ) return '';

			$anchor = Widget::Anchor($this->get('anchor_label'), $value);
			if( $this->get('new_window') == 'yes' ) $anchor->setAttribute('target', '_blank');
			return $anchor->generate();
		}



		/*------------------------------------------------------------------------------------------------*/
		/*  Utilities  */
		/*------------------------------------------------------------------------------------------------*/

		/**
		 * @param Entry $entry
		 */
		public function compile($entry){
			self::$ready = false;

			$xpath = $this->_driver->getXPath($entry);

			self::$ready = true;

			$entry_id = $entry->get('id');
			$field_id = $this->get('id');
			$expression = $this->get('expression');
			$values = array();
			$main_lang = FLang::getMainLang();

			foreach( FLang::getLangs() as $lc ){
				$replacements = array();

				// Find queries:
				preg_match_all('/\{[^\}]+\}/', $expression, $matches);

				// Find replacements:
				foreach( $matches[0] as $match ){
					$new_match = str_replace('$language_code', "'$lc'", $match);

					$results = @$xpath->query(trim($new_match, '{}'));

					if( $results->length ){
						$replacements[$match] = $results->item(0)->nodeValue;
					} else{
						$replacements[$match] = '';
					}
				}

				// Apply replacements:
				$url = str_replace(
					array_keys($replacements),
					array_values($replacements),
					$expression
				);

				$values['value-'.$lc] = ($main_lang !== $lc ? '/'.$lc : '').PLHManagerURL::sym2lang($url, $lc);
			}

			$values['value'] = $values['value-'.$main_lang];


			// Save:
			Symphony::Database()->update(
				$values,
				"tbl_entries_data_{$field_id}",
				"`entry_id` = '{$entry_id}'"
			);
		}



		/*------------------------------------------------------------------------------------------------*/
		/*  Field schema  */
		/*------------------------------------------------------------------------------------------------*/

		public function appendFieldSchema($f){}

	}
