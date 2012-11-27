<?php

	class Extension_ReflectionField extends Extension {
	/*-------------------------------------------------------------------------
		Definition:
	-------------------------------------------------------------------------*/

		protected static $fields = array();

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
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
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
			$entry_xml = new XMLElement('entry');
			$data = $entry->getData();
			$fields = array();

			$entry_xml->setAttribute('id', $entry->get('id'));

			// Add associated entry counts
			if($fetch_associated_counts == 'yes') {
				$associated = $entry->fetchAllAssociatedEntryCounts();

				if (is_array($associated) and !empty($associated)) {
					foreach ($associated as $section_id => $count) {
						$section = SectionManager::fetch($section_id);

						if(($section instanceof Section) === false) continue;
						$entry_xml->setAttribute($section->get('handle'), (string)$count);
					}
				}
			}

			// Add fields:
			foreach ($data as $field_id => $values) {
				if (empty($field_id)) continue;

				$field = FieldManager::fetch($field_id);
				$field->appendFormattedElement($entry_xml, $values, false, null, $entry->get('id'));
			}

			$xml = new XMLElement('data');
			$xml->appendChild($entry_xml);

			// Build some context
			$section = SectionManager::fetch($entry->get('section_id'));
			$params = new XMLElement('params');
			$params->appendChild(
				new XMLElement('section-handle', $section->get('handle'))
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
						'section-handle' => $section->get('handle'),
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
			if ( empty(self::$fields) ) {
				self::$fields = $context['section']->fetchFields('reflection');
			}

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
