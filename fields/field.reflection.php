<?php
	
	if (!defined('__IN_SYMPHONY__')) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');
	
	class FieldReflection extends Field {
		protected $_driver = null;
		
		protected static $ready = true;
		
	/*-------------------------------------------------------------------------
		Definition:
	-------------------------------------------------------------------------*/
		
		public function __construct(&$parent) {
			parent::__construct($parent);
			
			$this->_name = 'Reflection';
			$this->_driver = $this->_engine->ExtensionManager->create('reflectionfield');
			
			// Set defaults:
			$this->set('show_column', 'yes');
			$this->set('allow_override', 'no');
		}
		
		public function createTable() {
			$field_id = $this->get('id');
			
			return $this->_engine->Database->query("
				CREATE TABLE IF NOT EXISTS `tbl_entries_data_{$field_id}` (
					`id` int(11) unsigned NOT NULL auto_increment,
					`entry_id` int(11) unsigned NOT NULL,
					`handle` varchar(255) default NULL,
					`value` varchar(255) default NULL,
					`overridden` enum('yes', 'no') default 'no' NOT NULL,
					PRIMARY KEY  (`id`),
					KEY `entry_id` (`entry_id`),
					KEY `value` (`value`)
				) TYPE=MyISAM;
			");
		}

		public function allowDatasourceOutputGrouping() {
			return true;
		}
		
		public function allowDatasourceParamOutput() {
			return true;
		}
		
		public function canFilter() {
			return true;
		}
		
		public function canPrePopulate() {
			return true;
		}
		
		public function isSortable() {
			return true;
		}
		
	/*-------------------------------------------------------------------------
		Settings:
	-------------------------------------------------------------------------*/
		
		public function displaySettingsPanel(&$wrapper, $errors = null) {
			parent::displaySettingsPanel($wrapper, $errors);
			
			$order = $this->get('sortorder');
			
		/*---------------------------------------------------------------------
			Expression
		---------------------------------------------------------------------*/
			
			$label = Widget::Label('Expression');
			$label->appendChild(Widget::Input(
				"fields[{$order}][expression]",
				$this->get('expression')
			));
			
			$wrapper->appendChild($label);
			
			$help = new XMLElement('p');
			$help->setAttribute('class', 'help');
			
			$help->setValue('To access the other fields, use XPath: <code>{entry/field-one} static text {entry/field-two}</code>.');
			
			$wrapper->appendChild($help);
			
		/*---------------------------------------------------------------------
			Allow Override
		---------------------------------------------------------------------*/
			
			/*
			$label = Widget::Label();
			$input = Widget::Input("fields[{$order}][allow_override]", 'yes', 'checkbox');
			
			if ($this->get('allow_override') == 'yes') {
				$input->setAttribute('checked', 'checked');
			}
			
			$label->setValue($input->generate() . ' Allow value to be manually overridden');
			$wrapper->appendChild($label);
			*/
			
			$this->appendShowColumnCheckbox($wrapper);
		}
		
		public function commit() {
			if (!parent::commit()) return false;
			
			$id = $this->get('id');
			$handle = $this->handle();
			
			if ($id === false) return false;
			
			$fields = array(
				'field_id'			=> $id,
				'expression'		=> $this->get('expression'),
				'allow_override'	=> $this->get('allow_override')
			);
			
			$this->Database->query("
				DELETE FROM
					`tbl_fields_{$handle}`
				WHERE
					`field_id` = '{$id}'
				LIMIT 1
			");
			
			return $this->Database->insert($fields, "tbl_fields_{$handle}");
		}
		
	/*-------------------------------------------------------------------------
		Publish:
	-------------------------------------------------------------------------*/
		
		public function displayPublishPanel(&$wrapper, $data = null, $flagWithError = null, $prefix = null, $postfix = null) {
			$sortorder = $this->get('sortorder');
			$element_name = $this->get('element_name');
			$allow_override = null;
			
			if ($this->get('allow_override') == 'no') {
				$allow_override = array(
					'disabled'	=> 'disabled'
				);
			}
			
			$label = Widget::Label($this->get('label'));
			$label->appendChild(
				Widget::Input(
					"fields{$prefix}[$element_name]{$postfix}",
					@$data['value'], 'text', $allow_override
				)
			);
			$wrapper->appendChild($label);
		}
		
	/*-------------------------------------------------------------------------
		Input:
	-------------------------------------------------------------------------*/
		
		public function checkPostFieldData($data, &$message, $entry_id = null) {
			$this->_driver->registerField($this);
			
			return self::__OK__;
		}
		
		public function processRawFieldData($data, &$status, $simulate = false, $entry_id = null) {
			$status = self::__OK__;
			
			return array(
				'handle'	=> Lang::createHandle($data),
				'value'		=> $data
			);
		}
		
	/*-------------------------------------------------------------------------
		Output:
	-------------------------------------------------------------------------*/
		
		public function appendFormattedElement(&$wrapper, $data, $encode = false) {
			if (!self::$ready) return;
			
			$element = new XMLElement($this->get('element_name'));
			$element->setAttribute('handle', $data['handle']);
			$element->setValue($data['value']);
			
			$wrapper->appendChild($element);
		}
		
		public function prepareTableValue($data, XMLElement $link = null) {
			if (empty($data)) return;
			
			return parent::prepareTableValue($data, $link);
		}
		
	/*-------------------------------------------------------------------------
		Compile:
	-------------------------------------------------------------------------*/
		
		public function compile($entry) {
			self::$ready = false;
			
			$xpath = $this->_driver->getXPath($entry);
			
			self::$ready = true;
			
			$entry_id = $entry->get('id');
			$field_id = $this->get('id');
			$expression = $this->get('expression');
			$replacements = array();
			
			// Find queries:
			preg_match_all('/\{[^\}]+\}/', $expression, $matches);
			
			// Find replacements:
			foreach ($matches[0] as $match) {
				$results = @$xpath->query(trim($match, '{}'));
				
				if ($results->length) {
					$replacements[$match] = $results->item(0)->nodeValue;
				} else {
					$replacements[$match] = '';
				}
			}
			
			// Apply replacements:
			$expression = str_replace(
				array_keys($replacements),
				array_values($replacements),
				$expression
			);
			
			// Save:
			$result = $this->Database->update(array(
				'handle'	=> Lang::createHandle($expression),
				'value'		=> $expression
			), "tbl_entries_data_{$field_id}", "
				`entry_id` = '{$entry_id}'
			");
		}
		
	/*-------------------------------------------------------------------------
		Sorting:
	-------------------------------------------------------------------------*/
		
		public function buildSortingSQL(&$joins, &$where, &$sort, $order = 'ASC') {
			$field_id = $this->get('id');
			
			$joins .= "INNER JOIN `tbl_entries_data_{$field_id}` AS ed ON (e.id = ed.entry_id) ";
			$sort = 'ORDER BY ' . (strtolower($order) == 'random' ? 'RAND()' : "ed.value {$order}");
		}
		
	/*-------------------------------------------------------------------------
		Grouping:
	-------------------------------------------------------------------------*/
		
		public function groupRecords($records) {
			if (!is_array($records) or empty($records)) return;
			
			$groups = array(
				$this->get('element_name') => array()
			);
			
			foreach ($records as $record) {
				$data = $record->getData($this->get('id'));
				
				$value = $data['value'];
				$handle = $data['handle'];
				$element = $this->get('element_name');
				
				if (!isset($groups[$element][$handle])) {
					$groups[$element][$handle] = array(
						'attr'		=> array(
							'handle'	=> $handle,
							'value'		=> $value
						),
						'records'	=> array(),
						'groups'	=> array()
					);
				}
				
				$groups[$element][$handle]['records'][] = $record;
			}
			
			return $groups;
		}
	}
	
?>