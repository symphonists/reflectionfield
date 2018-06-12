<?php

/**
 * @package toolkit
 */
/**
 * Specialized EntryQueryFieldAdapter that facilitate creation of queries filtering/sorting data from
 * an reflection Field.
 * @see FieldReflection
 * @since Symphony 3.0.0
 */
class EntryQueryReflectionAdapter extends EntryQueryFieldAdapter
{
    public function isFilterBoolean($filter)
    {
        return preg_match('/^(not-)?boolean:\s*/', $filter);
    }

    public function createFilterBoolean($filter, array $columns)
    {
        $field_id = General::intval($this->field->get('id'));
        $filter = $this->field->cleanValue($filter);
        $op = 'boolean';

        $conditions = [];
        foreach ($columns as $key => $col) {
            $conditions[] = [$this->formatColumn($col, $field_id) => [$op => $filter]];
        }
        if (count($conditions) < 2) {
            return $conditions;
        }
        return ['or' => $conditions];
    }

    public function isFilterContains($filter)
    {
        return preg_match('/^(not-)?((starts|ends)-with|contains):\s*/', $filter);
    }

    public function createFilterContains($filter, array $columns)
    {
        $field_id = General::intval($this->field->get('id'));
        $matches = [];
        preg_match('/^(not-)?((starts|ends)-with|contains):\s*/', $filter, $matches);
        $op = empty($matches[1]) ? 'like' : 'not like';

        $filter = trim(array_pop(explode(':', $filter, 2)));
        $filter = $this->field->cleanValue($filter);

        if ($matches[2] == 'ends-with') {
            $filter = '%' . $filter;
        }
        if ($matches[2] == 'starts-with') {
            $filter = $filter . '%';
        }
        if ($matches[2] == 'contains') {
            $filter = '%' . $filter . '%';
        }

        $conditions = [];
        foreach ($columns as $key => $col) {
            $conditions[] = [$this->formatColumn($col, $field_id) => [$op => $filter]];
        }
        if (count($conditions) < 2) {
            return $conditions;
        }
        return ['or' => $conditions];
    }

    /**
     * @see EntryQueryFieldAdapter::filterSingle()
     *
     * @param EntryQuery $query
     * @param string $filter
     * @return array
     */
    protected function filterSingle(EntryQuery $query, $filter)
    {
        General::ensureType([
            'filter' => ['var' => $filter, 'type' => 'string'],
        ]);
        if ($this->isFilterBoolean($filter)) {
            return $this->createFilterBoolean($filter, ['value']);
        } elseif ($this->isFilterRegex($filter)) {
            return $this->createFilterRegexp($filter, ['value', 'handle']);
        } elseif ($this->isFilterContains($filter)) {
            return $this->createFilterContains($filter, ['value', 'handle']);
        }
        return $this->createFilterEquality($filter, ['value', 'handle']);
    }
}
