<?php

    class Extension_ReflectionField extends Extension
    {
        /*-------------------------------------------------------------------------
        Definition:
    -------------------------------------------------------------------------*/

        protected static $fields = array();

        public function uninstall()
        {
            return Symphony::Database()
                ->drop('tbl_fields_reflection')
                ->ifExists()
                ->execute()
                ->success();
        }

        public function install()
        {
            return Symphony::Database()
                ->create('tbl_fields_reflection')
                ->ifNotExists()
                ->charset('utf8')
                ->collate('utf8_unicode_ci')
                ->fields([
                    'id' => [
                        'type' => 'int(11)',
                        'auto' => true,
                    ],
                    'field_id' => 'int(11)',
                    'xsltfile' => [
                        'type' => 'varchar(255)',
                        'null' => true,
                    ],
                   'expression' => [
                        'type' => 'varchar(255)',
                        'null' => true,
                    ],
                    'formatter' => [
                        'type' => 'varchar(255)',
                        'null' => true,
                    ],
                    'override' => [
                        'type' => 'enum',
                        'values' => ['yes', 'no'],
                        'default' => 'no',
                    ],
                    'hide' => [
                        'type' => 'enum',
                        'values' => ['yes', 'no'],
                        'default' => 'no',
                    ],
                    'fetch_associated_counts' => [
                        'type' => 'enum',
                        'values' => ['yes', 'no'],
                        'default' => 'no',
                    ],
                ])
                ->keys([
                    'id' => 'primary',
                    'field_id' => 'key',
                ])
                ->execute()
                ->success();
        }

        public function update($previousVersion = false)
        {
            // Update 1.0 installations
            if (version_compare($previousVersion, '1.1', '<')) {
                Symphony::Database()
                    ->alter('tbl_fields_reflection')
                    ->add([
                        'xsltfile' => [
                            'type' => 'varchar(255)',
                            'null' => true,
                        ],
                    ])
                    ->execute()
                    ->success();
            }

            // Update 1.1 installations
            if (version_compare($previousVersion, '1.2', '<')) {
                Symphony::Database(tbl_fields_reflection)
                    ->alter('')
                    ->add([
                        'fetch_associated_counts' => [
                            'type' => 'enum',
                            'values' => ['yes', 'no'],
                            'default' => 'no',
                        ],
                    ])
                    ->execute()
                    ->success();
            }

            return true;
        }

        public function getSubscribedDelegates()
        {
            return array(
                array(
                    'page' => '/publish/new/',
                    'delegate' => 'EntryPostCreate',
                    'callback' => 'compileBackendFields',
                ),
                array(
                    'page' => '/publish/edit/',
                    'delegate' => 'EntryPostEdit',
                    'callback' => 'compileBackendFields',
                ),
                array(
                    'page' => '/xmlimporter/importers/run/',
                    'delegate' => 'XMLImporterEntryPostCreate',
                    'callback' => 'compileBackendFields',
                ),
                array(
                    'page' => '/xmlimporter/importers/run/',
                    'delegate' => 'XMLImporterEntryPostEdit',
                    'callback' => 'compileBackendFields',
                ),
                array(
                    'page' => '/frontend/',
                    'delegate' => 'EventPostSaveFilter',
                    'callback' => 'compileFrontendFields',
                ),
            );
        }

    /*-------------------------------------------------------------------------
        Utilities:
    -------------------------------------------------------------------------*/

        public function getXPath($entry, $template = null, $fetch_associated_counts = null, $handle = 'reflection-field')
        {
            $xml = $this->buildXML($handle, $entry);
            $dom = new DOMDocument();
            $dom->strictErrorChecking = false;
            $dom->loadXML($xml->generate(true));

            // Transform XML if template is provided
            if (!empty($template)) {
                $template = UTILITIES . '/' . preg_replace(array('%/+%', '%(^|/)../%'), '/', $template);

                if (file_exists($template)) {
                    $xslt = new DomDocument();
                    $xslt->load($template);

                    $xslp = new XsltProcessor();
                    $xslp->importStyleSheet($xslt);

                    $temp = $xslp->transformToDoc($dom);

                    if ($temp instanceof DOMDocument) {
                        $dom = $temp;
                    }
                }
            }

            // Create xPath object
            $xpath = new DOMXPath($dom);

            if (version_compare(phpversion(), '5.3', '>=')) {
                $xpath->registerPhpFunctions();
            }

            return $xpath;
        }

        private function buildXML($handle = 'reflection-field', $entry)
        {
            $xml = new XMLElement('data');

            $xml->appendChild($this->buildParams());
            $xml->appendChild($this->buildEntry($handle, $entry));

            return $xml;
        }

        private function buildParams()
        {
            $xml = new XMLElement('params');

            $upload_size_php = ini_size_to_bytes(ini_get('upload_max_filesize'));
            $upload_size_sym = Symphony::Configuration()->get('max_upload_size', 'admin');
            $date = new DateTime();

            $params = array(
                'today' => $date->format('Y-m-d'),
                'current-time' => $date->format('H:i'),
                'this-year' => $date->format('Y'),
                'this-month' => $date->format('m'),
                'this-day' => $date->format('d'),
                'timezone' => $date->format('P'),
                'website-name' => Symphony::Configuration()->get('sitename', 'general'),
                'root' => URL,
                'workspace' => URL . '/workspace',
                'http-host' => HTTP_HOST,
                'upload-limit' => min($upload_size_php, $upload_size_sym),
                'symphony-version' => Symphony::Configuration()->get('version', 'symphony'),
            );

            foreach($params as $name => $value) {
                $xml->appendChild(
                    new XMLElement($name, $value)
                );
            }

            return $xml;
        }

        private function buildEntry($handle = 'reflection-field', $entry)
        {
            $xml = new XMLElement($handle);
            $data = $entry->getData();

            // Section context
            $section_data = (new SectionManager)->select()->section($entry->get('section_id'))->execute()->next();
            $section = new XMLElement('section', $section_data->get('name'));
            $section->setAttribute('id', $entry->get('section_id'));
            $section->setAttribute('handle', $section_data->get('handle'));

            // Entry data
            $entry_xml = new XMLElement('entry');
            $entry_xml->setAttribute('id', $entry->get('id'));

            // Add associated entry counts
            if ($fetch_associated_counts == 'yes') {
                $associated = $entry->fetchAllAssociatedEntryCounts();

                if (is_array($associated) and !empty($associated)) {
                    foreach ($associated as $section_id => $count) {
                        $section_data = SectionManager::fetch($section_id);

                        if (($section_data instanceof Section) === false) {
                            continue;
                        }

                        $entry_xml->setAttribute($section_data->get('handle'), (string) $count);
                    }
                }
            }

            // Add field data
            foreach ($data as $field_id => $values) {
                if (empty($field_id)) {
                    continue;
                }

                $field = (new FieldManager)->select()->field($field_id)->execute()->next();
                $field->appendFormattedElement($entry_xml, $values, false, null, $entry->get('id'));
            }

            // Add entry system dates
            $entry_xml->appendChild($this->buildSystemDate($entry));

            // Append nodes
            $xml->appendChild($section);
            $xml->appendChild($entry_xml);

            return $xml;
        }

        private function buildSystemDate($entry)
        {
            $xml = new XMLElement('system-date');

            $created = General::createXMLDateObject(
                DateTimeObj::get('U', $entry->get('creation_date')),
                'created'
            );
            $modified = General::createXMLDateObject(
                DateTimeObj::get('U', $entry->get('modification_date')),
                'modified'
            );

            $xml->appendChild($created);
            $xml->appendChild($modified);

            return $xml;
        }

    /*-------------------------------------------------------------------------
        Fields:
    -------------------------------------------------------------------------*/

        public function registerField(Field $field)
        {
            self::$fields[$field->get('id')] = $field;
        }

        public function compileBackendFields($context)
        {
            if (empty(self::$fields)) {
                self::$fields = $context['section']->fetchFields('reflection');
            }

            foreach (self::$fields as $field) {
                $field->compile($context['entry']);
            }
        }

        public function compileFrontendFields($context)
        {
            foreach (self::$fields as $field) {
                $field->compile($context['entry']);
            }
        }
    }
