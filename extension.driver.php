<?php
	
	class Extension_ReflectionField extends Extension {
	/*-------------------------------------------------------------------------
		Definition:
	-------------------------------------------------------------------------*/
		
		protected static $fields = array();
		
		public function about() {
			return array(
				'name'			=> 'Field: Reflection',
				'version'		=> '1.001',
				'release-date'	=> '2008-12-05',
				'author'		=> array(
					'name'			=> 'Rowan Lewis',
					'website'		=> 'http://pixelcarnage.com/',
					'email'			=> 'rowan@pixelcarnage.com'
				),
				'description' => '
					Allows you to automatically combine multiple fields into one.
				'
			);
		}
		
		public function uninstall() {
			$this->_Parent->Database->query("DROP TABLE `tbl_fields_reflection`");
		}
		
		public function install() {
			$this->_Parent->Database->query("
				CREATE TABLE IF NOT EXISTS `tbl_fields_reflection` (
					`id` int(11) unsigned NOT NULL auto_increment,
					`field_id` int(11) unsigned NOT NULL,
					`expression` varchar(255) default NULL,
					`allow_override` enum('yes', 'no') default 'no' NOT NULL,
					PRIMARY KEY (`id`),
					KEY `field_id` (`field_id`)
				)
			");
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
		
		public function getXPath($entry) {
			$entry_xml = new XMLElement('entry');
			$section_id = $entry->_fields['section_id'];
			$data = $entry->getData(); $fields = array();
			
			$entry_xml->setAttribute('id', $entry->get('id'));
			
			$associated = $entry->fetchAllAssociatedEntryCounts();
			
			if (is_array($associated) and !empty($associated)) {
				foreach ($associated as $section => $count) {
					$handle = $this->_Parent->Database->fetchVar('handle', 0, "
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
				$field =& $entry->_Parent->fieldManager->fetch($field_id);
				$field->appendFormattedElement($entry_xml, $values, false);
			}
			
			$xml = new XMLElement('data');
			$xml->appendChild($entry_xml);
			
			$dom = new DOMDocument();
			$dom->loadXML($xml->generate(true));
			
			return new DOMXPath($dom);
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
