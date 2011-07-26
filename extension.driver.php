<?php

	class Extension_ReflectionField extends Extension {
	/*-------------------------------------------------------------------------
		Definition:
	-------------------------------------------------------------------------*/

		protected static $fields = array();

		public function about() {
			return array(
				'name'			=> 'Field: Reflection',
				'version'		=> '1.2',
				'release-date'	=> '2011-07-26',
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
					`xsltfile` VARCHAR(255) DEFAULT NULL,
					`expression` VARCHAR(255) DEFAULT NULL,
					`formatter` VARCHAR(255) DEFAULT NULL,
					`override` ENUM('yes', 'no') DEFAULT 'no',
					`hide` ENUM('yes', 'no') DEFAULT 'no',
					`fetch_associated_counts` ENUM('yes','no') DEFAULT 'no',
					PRIMARY KEY (`id`),
					KEY `field_id` (`field_id`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8;
			");

			return true;
		}

		public function update($previousVersion) {
			// Update 1.0 installations
			if (version_compare($previousVersion, '1.1', '<')) {
				Symphony::Database()->query("ALTER TABLE `tbl_fields_reflection` ADD `xsltfile` VARCHAR(255) DEFAULT NULL");
			}

			// Update 1.1 installations
			if (version_compare($previousVersion, '1.2', '<')) {
				Symphony::Database()->query("ALTER TABLE `tbl_fields_reflection` ADD `fetch_associated_counts` ENUM('yes','no') DEFAULT 'no'");
			}

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
				)
			);
		}

	/*-------------------------------------------------------------------------
		Utilities:
	-------------------------------------------------------------------------*/

		public function getXPath($entry, $XSLTfilename = NULL, $fetch_associated_counts = NULL) {
			$fieldManager = new FieldManager(Symphony::Engine());
			$entry_xml = new XMLElement('entry');
			$section_id = $entry->get('section_id');
			$data = $entry->getData(); $fields = array();

			$entry_xml->setAttribute('id', $entry->get('id'));

			// Add associated entry counts
			if($fetch_associated_counts == 'yes') {
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
			}

			// Add fields:
			foreach ($data as $field_id => $values) {
				if (empty($field_id)) continue;

				$field = $fieldManager->fetch($field_id);
				$field->appendFormattedElement($entry_xml, $values, false, null);
			}

			$xml = new XMLElement('data');
			$xml->appendChild($entry_xml);

			// Build some context
			$params = new XMLElement('params');
			$section_handle = Symphony::Database()->fetchVar('handle', 0, sprintf('
				SELECT `handle` FROM tbl_sections WHERE id = %d
			', $section_id));
			$params->appendChild(
				new XMLElement('section-handle', $section_handle)
			);
			$params->appendChild(
				new XMLElement('entry-id', $entry->get('id'))
			);
			$xml->prependChild($params);

			$dom = new DOMDocument();
			$dom->strictErrorChecking = false;
			$dom->loadXML($xml->generate(true));

			if (!empty($XSLTfilename)) {
				$XSLTfilename = UTILITIES . '/'. preg_replace(array('%/+%', '%(^|/)../%'), '/', $XSLTfilename);
				if (file_exists($XSLTfilename)) {
					$XSLProc = new XsltProcessor;

					$xslt = new DomDocument;
					$xslt->load($XSLTfilename);

					$XSLProc->importStyleSheet($xslt);

					// Set some context
					$XSLProc->setParameter('', array(
						'section-handle' => $section_handle,
						'entry-id' => $entry->get('id')
					));

					$temp = $XSLProc->transformToDoc($dom);

					if ($temp instanceof DOMDocument) {
						$dom = $temp;
					}
				}
			}

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