<?php

	class Extension_ReflectionField extends Extension {
	/*-------------------------------------------------------------------------
		Definition:
	-------------------------------------------------------------------------*/

		protected static $fields = array();

		public function about() {
			return array(
				'name'			=> 'Field: Reflection',
				'version'		=> '1.0.12',
				'release-date'	=> '2011-04-11',
				'author'		=> array(
					'name'			=> 'Rowan Lewis',
					'website'		=> 'http://nbsp.io/',
					'email'			=> 'me@nbsp.io'
				),
				'description' => '
					Create a new value from the current entry using XPath.
				'
			);
		}

		public function uninstall() {
			Symphony::Database()->query("DROP TABLE `tbl_fields_reflection`");
		}

		public function install() {
			Symphony::Database()->query("
				CREATE TABLE IF NOT EXISTS `tbl_fields_reflection` (
					`id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
					`field_id` INT(11) UNSIGNED NOT NULL,
					`expression` VARCHAR(255) DEFAULT NULL,
					`formatter` VARCHAR(255) DEFAULT NULL,
					`override` ENUM('yes', 'no') DEFAULT 'no',
					`hide` ENUM('yes', 'no') DEFAULT 'no',
					PRIMARY KEY (`id`),
					KEY `field_id` (`field_id`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8;
			");

			return true;
		}

		public function getSubscribedDelegates() {
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
					'page'		=> '/xmlimporter/importers/run/',
					'delegate'	=> 'XMLImporterEntryPostEdit',
					'callback'	=> 'compileBackendFields'
				),
				array(
					'page'		=> '/xmlimporter/importers/run/',
					'delegate'	=> 'XMLImporterEntryPostCreate',
					'callback'	=> 'compileBackendFields'
				)
			);
		}

	/*-------------------------------------------------------------------------
		Utilities:
	-------------------------------------------------------------------------*/

		public function getXPath($entry) {
			$fieldManager = new FieldManager(Symphony::Engine());
			$entry_xml = new XMLElement('entry');
			$section_id = $entry->get('section_id');
			$data = $entry->getData(); $fields = array();

			$entry_xml->setAttribute('id', $entry->get('id'));

			$associated = $entry->fetchAllAssociatedEntryCounts();

			if (is_array($associated) and !empty($associated)) {
				foreach ($associated as $section => $count) {
					$handle = Symphony::Database()->fetchVar('handle', 0, "
						SELECT
							s.handle
						FROM
							`tbl_sections` AS s
						WHERE
							s.id = '{$section}'
						LIMIT 1
					");

					$entry_xml->setAttribute($handle, (string)$count);
				}
			}

			// Add fields:
			foreach ($data as $field_id => $values) {
				if (empty($field_id)) continue;

				$field = $fieldManager->fetch($field_id);
				$field->appendFormattedElement($entry_xml, $values, false, null);
			}

			$xml = new XMLElement('data');
			$xml->appendChild($entry_xml);

			$dom = new DOMDocument();
			$dom->strictErrorChecking = false;
			$dom->loadXML($xml->generate(true));

			$xpath = new DOMXPath($dom);

			if (version_compare(phpversion(), '5.3', '>=')) {
				$xpath->registerPhpFunctions();
			}

			return $xpath;
		}

	/*-------------------------------------------------------------------------
		Fields:
	-------------------------------------------------------------------------*/

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

?>
