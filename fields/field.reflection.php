<?php

	if (!defined('__IN_SYMPHONY__')) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');

	class FieldReflection extends Field {
		protected static $compiling = 0;

	/*-------------------------------------------------------------------------
		Definition:
	-------------------------------------------------------------------------*/

		public function __construct(&$parent) {
			parent::__construct($parent);

			$this->_name = 'Reflection';

			// Set defaults:
			$this->set('show_column', 'yes');
			$this->set('allow_override', 'no');
			$this->set('fetch_associated_counts', 'no');
			$this->set('hide', 'no');
		}

		public function createTable() {
			$field_id = $this->get('id');

			return Symphony::Database()->query("
				CREATE TABLE IF NOT EXISTS `tbl_entries_data_{$field_id}` (
					`id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
					`entry_id` INT(11) UNSIGNED NOT NULL,
					`handle` VARCHAR(255) DEFAULT NULL,
					`value` TEXT DEFAULT NULL,
					`value_formatted` TEXT DEFAULT NULL,
					PRIMARY KEY (`id`),
					KEY `entry_id` (`entry_id`),
					FULLTEXT KEY `value` (`value`),
					FULLTEXT KEY `value_formatted` (`value_formatted`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8;
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
			Text Formatter
		---------------------------------------------------------------------*/

			$group = new XMLElement('div');
			$group->setAttribute('class', 'group');

			$group->appendChild($this->buildFormatterSelect(
				$this->get('formatter'),
				"fields[{$order}][formatter]",
				'Text Formatter'
			));

		/*---------------------------------------------------------------------
			XSLT
		---------------------------------------------------------------------*/

			$div = new XMLElement('div');
			$label = Widget::Label('XSLT Utility');

			$utilities = General::listStructure(UTILITIES, array('xsl'), false, 'asc', UTILITIES);
			$utilities = $utilities['filelist'];

			$xsltfile = $this->get('xsltfile');
			$options = array();
			$options[] = array('', empty($xsltfile), __('Disabled'));

			foreach ($utilities as $utility) {
				$options[] = array($utility, ($xsltfile == $utility), $utility);
			}

			$label->appendChild(Widget::Select(
				"fields[{$order}][xsltfile]",
				$options
			));

			$help = new XMLElement('p');
			$help->setAttribute('class', 'help');

			$help->setValue(__('XSLT will be applied to <code>entry</code> XML before <code>Expression</code> is evaluated.'));

			$div->appendChild($label);
			$div->appendChild($help);
			$group->appendChild($div);
			$wrapper->appendChild($group);

		/*---------------------------------------------------------------------
			Expression
		---------------------------------------------------------------------*/

			$div = new XMLElement('div');
			$label = Widget::Label('Expression');

			$label->appendChild(Widget::Input(
				"fields[{$order}][expression]",
				$this->get('expression')
			));

			$help = new XMLElement('p');
			$help->setAttribute('class', 'help');

			$help->setValue('
				To access the other fields, use XPath: <code>{entry/field-one} static text {entry/field-two}</code>.
			');

			$div->appendChild($label);
			$div->appendChild($help);
			$wrapper->appendChild($div);

		/*---------------------------------------------------------------------
			Fetch Associated Entry Counts
		---------------------------------------------------------------------*/

			$compact = new XMLElement('div');
			$compact->setAttribute('class', 'compact');

			$label = Widget::Label();
			$input = Widget::Input("fields[{$order}][fetch_associated_counts]", 'yes', 'checkbox');

			if ($this->get('fetch_associated_counts') == 'yes') {
				$input->setAttribute('checked', 'checked');
			}

			$label->setValue($input->generate() . ' Fetch associated entry counts for XPath');
			$compact->appendChild($label);

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

		/*---------------------------------------------------------------------
			Hide input
		---------------------------------------------------------------------*/

			$label = Widget::Label();
			$input = Widget::Input("fields[{$order}][hide]", 'yes', 'checkbox');

			if ($this->get('hide') == 'yes') {
				$input->setAttribute('checked', 'checked');
			}

			$label->setValue($input->generate() . ' Hide this field on publish page');
			$compact->appendChild($label);

			$this->appendShowColumnCheckbox($compact);

			$wrapper->appendChild($compact);
		}

		public function commit() {
			if (!parent::commit()) return false;

			$id = $this->get('id');
			$handle = $this->handle();

			if ($id === false) return false;

			$fields = array(
				'field_id'			=> $id,
				'xsltfile'			=> $this->get('xsltfile'),
				'expression'		=> $this->get('expression'),
				'formatter'			=> $this->get('formatter'),
				'override'			=> $this->get('override'),
				'fetch_associated_counts' => $this->get('fetch_associated_counts'),
				'hide'				=> $this->get('hide')
			);

			Symphony::Database()->query("
				DELETE FROM
					`tbl_fields_{$handle}`
				WHERE
					`field_id` = '{$id}'
				LIMIT 1
			");

			return Symphony::Database()->insert($fields, "tbl_fields_{$handle}");
		}

	/*-------------------------------------------------------------------------
		Publish:
	-------------------------------------------------------------------------*/

		public function displayPublishPanel(&$wrapper, $data = null, $flagWithError = null, $prefix = null, $postfix = null) {
			$sortorder = $this->get('sortorder');
			$element_name = $this->get('element_name');
			$allow_override = null;

			if ($this->get('override') != 'yes') {
				$allow_override = array(
					'disabled'	=> 'disabled'
				);
			}

			if ($this->get('hide') != 'yes') {
				$value = isset($data['value_formatted'])
					? $data['value_formatted']
					: null;
				$label = Widget::Label($this->get('label'));
				$label->appendChild(
					Widget::Input(
						"fields{$prefix}[$element_name]{$postfix}",
						$value, 'text', $allow_override
					)
				);
				$wrapper->appendChild($label);
			}
		}

	/*-------------------------------------------------------------------------
		Input:
	-------------------------------------------------------------------------*/

		public function checkPostFieldData($data, &$message, $entry_id = null) {
			$driver = Symphony::ExtensionManager()->create('reflectionfield');
			$driver->registerField($this);

			return self::__OK__;
		}

		public function processRawFieldData($data, &$status, $simulate = false, $entry_id = null) {
			$status = self::__OK__;

			return array(
				'handle'			=> null,
				'value'				=> null,
				'value_formatted'	=> null
			);
		}

	/*-------------------------------------------------------------------------
		Output:
	-------------------------------------------------------------------------*/

		public function appendFormattedElement(&$wrapper, $data, $encode = false) {
			if (self::$compiling == $this->get('id')) return;

			$element = new XMLElement($this->get('element_name'));
			$element->setAttribute('handle', $data['handle']);
			$element->setValue($data['value_formatted']);

			$wrapper->appendChild($element);
		}

		public function prepareTableValue($data, XMLElement $link = null) {
			if (empty($data)) return;

			return parent::prepareTableValue(
				array(
					'value'		=> $data['value_formatted']
				), $link
			);
		}

	/*-------------------------------------------------------------------------
		Compile:
	-------------------------------------------------------------------------*/

		public function applyFormatting($data) {
			if ($this->get('formatter') != 'none') {
				$tfm = new TextformatterManager(Symphony::Engine());

				$formatter = $tfm->create($this->get('formatter'));
				$formatted = $formatter->run($data);

			 	return preg_replace('/&(?![a-z]{0,4}\w{2,3};|#[x0-9a-f]{2,6};)/i', '&amp;', $formatted);
			}

			return null;
		}

		public function compile(&$entry) {
			self::$compiling = $this->get('id');

			$driver = Symphony::ExtensionManager()->create('reflectionfield');
			$xpath = $driver->getXPath($entry, $this->get('xsltfile'), $this->get('fetch_associated_counts'));

			self::$compiling = 0;

			$entry_id = $entry->get('id');
			$field_id = $this->get('id');
			$expression = $this->get('expression');
			$replacements = array();

			// Find queries:
			preg_match_all('/\{[^\}]+\}/', $expression, $matches);

			// Find replacements:
			foreach ($matches[0] as $match) {
				$result = @$xpath->evaluate('string(' . trim($match, '{}') . ')');

				if (!is_null($result)) {
					$replacements[$match] = trim($result);
				}

				else {
					$replacements[$match] = '';
				}
			}

			// Apply replacements:
			$value = str_replace(
				array_keys($replacements),
				array_values($replacements),
				$expression
			);

			// Apply formatting:
			if (!$value_formatted = $this->applyFormatting($value)) {
				$value_formatted = General::sanitize($value);
			}

			$data = array(
				'handle'			=> Lang::createHandle($value),
				'value'				=> $value,
				'value_formatted'	=> $value_formatted
			);

			// Save:
			$result = Symphony::Database()->update(
				array(
					'handle'			=> Lang::createHandle($value),
					'value'				=> $value,
					'value_formatted'	=> $value_formatted
				),
				"tbl_entries_data_{$field_id}",
				"`entry_id` = '{$entry_id}'"
			);

			$entry->setData($field_id, $data);
		}

	/*-------------------------------------------------------------------------
		Filtering:
	-------------------------------------------------------------------------*/

		public function buildDSRetrivalSQL($data, &$joins, &$where, $andOperation = false) {
			$field_id = $this->get('id');

			if (self::isFilterRegex($data[0])) {
				$this->_key++;

				if (preg_match('/^regexp:/i', $data[0])) {
					$pattern = preg_replace('/regexp:/i', null, $this->cleanValue($data[0]));
					$regex = 'REGEXP';
				} else {
					$pattern = preg_replace('/not-?regexp:/i', null, $this->cleanValue($data[0]));
					$regex = 'NOT REGEXP';
				}

				$joins .= "
					LEFT JOIN
						`tbl_entries_data_{$field_id}` AS t{$field_id}_{$this->_key}
						ON (e.id = t{$field_id}_{$this->_key}.entry_id)
				";
				$where .= "
					AND (
						t{$field_id}_{$this->_key}.handle {$regex} '{$pattern}'
						OR t{$field_id}_{$this->_key}.value {$regex} '{$pattern}'
					)
				";
			}

			else if (preg_match('/^(not-)?(boolean|search):\s*/', $data[0], $matches)) {
				$data = trim(array_pop(explode(':', implode(' + ', $data), 2)));
				$negate = ($matches[1] == '' ? '' : 'NOT');

				if ($data == '') return true;

				// Negative match?
				if (preg_match('/^not(\W)/i', $data)) {
					$mode = '-';

				} else {
					$mode = '+';
				}

				// Replace ' and ' with ' +':
				$data = preg_replace('/(\W)and(\W)/i', '\\1+\\2', $data);
				$data = preg_replace('/(^)and(\W)|(\W)and($)/i', '\\2\\3', $data);
				$data = preg_replace('/(\W)not(\W)/i', '\\1-\\2', $data);
				$data = preg_replace('/(^)not(\W)|(\W)not($)/i', '\\2\\3', $data);
				$data = preg_replace('/([\+\-])\s*/', '\\1', $mode . $data);

				$data = $this->cleanValue($data);
				$this->_key++;
				$joins .= "
					LEFT JOIN
						`tbl_entries_data_{$field_id}` AS t{$field_id}_{$this->_key}
						ON (e.id = t{$field_id}_{$this->_key}.entry_id)
				";
				$where .= "
					AND {$negate}(MATCH (t{$field_id}_{$this->_key}.value) AGAINST ('{$data}' IN BOOLEAN MODE))
				";
			}

			else if (preg_match('/^(not-)?((starts|ends)-with|contains):\s*/', $data[0], $matches)) {
				$data = trim(array_pop(explode(':', $data[0], 2)));
				$negate = ($matches[1] == '' ? '' : 'NOT');
				$data = $this->cleanValue($data);

				if ($matches[2] == 'ends-with') $data = "%{$data}";
				if ($matches[2] == 'starts-with') $data = "{$data}%";
				if ($matches[2] == 'contains') $data = "%{$data}%";

				$this->_key++;
				$joins .= "
					LEFT JOIN
						`tbl_entries_data_{$field_id}` AS t{$field_id}_{$this->_key}
						ON (e.id = t{$field_id}_{$this->_key}.entry_id)
				";
				$where .= "
					AND {$negate}(
						t{$field_id}_{$this->_key}.handle LIKE '{$data}'
						OR t{$field_id}_{$this->_key}.value LIKE '{$data}'
					)
				";
			}

			else if (preg_match('/^(?:equal to or )?(?:less than|more than|equal to) -?\d+(?:\.\d+)?$/i', $data[0])) {

				$comparisons = array();
				foreach ($data as $string) {
					if (preg_match('/^(equal to or )?(less than|more than|equal to) (-?\d+(?:\.\d+)?)$/i', $string, $matches)) {
						$number = trim($matches[3]);
						if (!is_numeric($number) || $number === '') continue;
						$number = floatval($number);

						$operator = '<';
						switch ($matches[2]) {
							case 'more than': $operator = '>'; break;
							case 'less than': $operator = '<'; break;
							case 'equal to': $operator = '='; break;
						}

						if ($matches[1] == 'equal to or ' && $operator != '=') {
							$operator .= '=';
						}

						$comparisons[] = "{$operator} {$number}";
					}
				}

				if (!empty($comparisons)) {
					$this->_key++;
					$joins .= "
						LEFT JOIN
							`tbl_entries_data_{$field_id}` AS t{$field_id}_{$this->_key}
							ON (e.id = t{$field_id}_{$this->_key}.entry_id)
					";

					$value = " t{$field_id}_{$this->_key}.value ";
					$comparisons = $value . implode(' '.($andOperation ? 'AND' : 'OR').$value, $comparisons);

					$where .= "
						AND (
							{$comparisons}
						)
					";
				}
			}

			else if ($andOperation) {
				foreach ($data as $value) {
					$this->_key++;
					$value = $this->cleanValue($value);
					$joins .= "
						LEFT JOIN
							`tbl_entries_data_{$field_id}` AS t{$field_id}_{$this->_key}
							ON (e.id = t{$field_id}_{$this->_key}.entry_id)
					";
					$where .= "
						AND (
							t{$field_id}_{$this->_key}.handle = '{$value}'
							OR t{$field_id}_{$this->_key}.value = '{$value}'
						)
					";
				}
			}

			else {
				if (!is_array($data)) $data = array($data);

				foreach ($data as &$value) {
					$value = $this->cleanValue($value);
				}

				$this->_key++;
				$data = implode("', '", $data);
				$joins .= "
					LEFT JOIN
						`tbl_entries_data_{$field_id}` AS t{$field_id}_{$this->_key}
						ON (e.id = t{$field_id}_{$this->_key}.entry_id)
				";
				$where .= "
					AND (
						t{$field_id}_{$this->_key}.handle IN ('{$data}')
						OR t{$field_id}_{$this->_key}.value IN ('{$data}')
					)
				";
			}

			return true;
		}

	/*-------------------------------------------------------------------------
		Sorting:
	-------------------------------------------------------------------------*/

		public function buildSortingSQL(&$joins, &$where, &$sort, $order = 'ASC') {
			$field_id = $this->get('id');

			$joins .= "LEFT OUTER JOIN `tbl_entries_data_{$field_id}` AS ed ON (e.id = ed.entry_id) ";
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

				$value = $data['value_formatted'];
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