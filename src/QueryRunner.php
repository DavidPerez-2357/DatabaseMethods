<?php

/**
 * QueryRunner.php
 *
 * Executes Query instances against a Database connection.
 *
 * @author DavidPerez-2357
 * @link https://github.com/DavidPerez-2357/DatabaseMethods
 */
class QueryRunner
{
    /**
     * @var Database
     */
    private $database;

    /**
     * @param Database $database
     */
    public function __construct(Database $database)
    {
        $this->database = $database;
    }

    /**
     * Executes a SELECT query and returns all result rows.
     *
     * @param Query $query The linked query to execute.
     * @param array $data Named or positional parameter bindings.
     * @return array|string
     */
    public function runSelect(Query $query, array $data = array())
    {
        return $this->database->plainSelect((string) $query, $data);
    }

    /**
     * Executes an INSERT query and returns the last insert ID (single row)
     * or 0 for multi-row batches.
     *
     * @param Query $query The linked INSERT query.
     * @param array $data Row data (single associative array or list of associative arrays).
     * @return int Last insert ID for single rows; 0 for multi-row batches.
     * @throws InvalidArgumentException If the query has no table set.
     */
    public function runInsert(Query $query, array $data = array())
    {
        if (empty($query->getTable())) {
            throw new InvalidArgumentException('INSERT query requires a table.');
        }

        // Detect multi-row: sequential numeric-keyed array whose every element is an array.
        $isList = !empty($data) && (array_keys($data) === range(0, count($data) - 1));
        $allArrays = true;
        foreach ($data as $item) {
            if (!is_array($item)) {
                $allArrays = false;
                break;
            }
        }
        $isMultiRow = $isList && $allArrays;

        if ($isMultiRow) {
            $rows = $data;
            $existingFields = $query->getFields();
            $fields = !empty($existingFields) ? $existingFields : array_keys($rows[0]);
            $query->fields($fields)->valuesCount(count($rows));
            $this->database->runPlainQuery((string) $query, PdoParameterBuilder::buildInsertParams($rows));
            return 0;
        }

        // Single row
        $existingFields = $query->getFields();
        $fields = !empty($existingFields) ? $existingFields : array_keys($data);
        $query->fields($fields)->valuesCount(1);
        $this->database->runPlainQuery((string) $query, PdoParameterBuilder::buildInsertParams(array($data)));
        return $this->database->getLastInsertId();
    }

    /**
     * Executes an UPDATE query and returns the number of affected rows.
     *
     * @param Query $query The linked UPDATE query.
     * @param array $data Combined SET + WHERE bindings.
     * @return int Number of affected rows.
     */
    public function runUpdate(Query $query, array $data = array())
    {
        $fields = $query->getFields();

        // Derive the un-quoted key for each field so we can split $data correctly.
        $fieldKeys = array();
        foreach ($fields as $field) {
            $fieldKeys[] = preg_replace('/^(["\'`])(.*)\1$/', '$2', $field);
        }

        $fieldsToUpdate = array_intersect_key($data, array_flip($fieldKeys));
        $whereData = array_diff_key($data, array_flip($fieldKeys));

        $placeholders = PdoParameterBuilder::buildNamedParams($fieldsToUpdate);
        $placeholders = array_merge(
            $placeholders,
            PdoParameterBuilder::normalizeNamedWhereBindings($whereData, $placeholders)
        );

        return (int) $this->database->runPlainQuery((string) $query, $placeholders);
    }

    /**
     * Executes a DELETE query and returns the number of affected rows.
     *
     * @param Query $query The linked DELETE query.
     * @param array $data WHERE clause bindings (named or positional).
     * @return int Number of affected rows.
     */
    public function runDelete(Query $query, array $data = array())
    {
        return (int) $this->database->runPlainQuery((string) $query, $data);
    }
}
