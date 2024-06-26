<?php

namespace Sanovskiy\SimpleObject\ModelsWriter\Parsers;

use PDO;
use Sanovskiy\SimpleObject\ModelsWriter\Schemas\ColumnSchema;
use Sanovskiy\SimpleObject\ModelsWriter\Schemas\TableSchema;

class ParserPostgreSQL extends ParserAbstract
{

    public function getDatabaseTables(): array
    {
        $statement = $this->connection->prepare('SELECT table_name FROM information_schema.tables WHERE table_catalog = :database AND table_schema=\'public\'');
        $statement->execute(['database' => $this->database]);
        $tableList = $statement->fetchAll(PDO::FETCH_COLUMN);
        return array_combine($tableList, array_map(fn ($tableName) => new TableSchema($tableName, $this), $tableList));
    }

    public function getTableColumns(string $tableName): array
    {
        if(!$this->isTableExist($tableName)){
            throw new \InvalidArgumentException('Table '.$tableName.' doesn\'t exist in database');
        }
        $statement = $this->connection->prepare('SELECT column_name, data_type, is_nullable, column_default, ordinal_position, udt_name
            FROM information_schema.columns
            WHERE table_name = :tableName
            AND table_catalog = :database');
        $statement->execute(['tableName' => $tableName, 'database' => $this->database]);
        $columnsData = $statement->fetchAll(PDO::FETCH_ASSOC);

        $columns = [];
        foreach ($columnsData as $columnData) {
            $name = $columnData['column_name'];
            $dataType = $columnData['udt_name'];
            $nullable = $columnData['is_nullable'] === 'YES';
            $default = $columnData['column_default'];
            $primaryKey = false; // PostgreSQL doesn't directly specify primary keys in this table

            // Check if column is part of a primary key constraint
            $statement = $this->connection->prepare('SELECT constraint_name
                FROM information_schema.constraint_column_usage
                WHERE table_name = :tableName
                AND column_name = :columnName
                AND table_catalog = :database');
            $statement->execute(['tableName' => $tableName, 'columnName' => $name, 'database' => $this->database]);
            $constraintData = $statement->fetch(PDO::FETCH_ASSOC);
            if ($constraintData && str_contains($constraintData['constraint_name'], 'pkey')) {
                $primaryKey = true;
            }

            // Fetch foreign key information
            $foreignKey = null;
            $references = null;
            // Fetching foreign key constraints
            $statement = $this->connection->prepare('SELECT
                tc.constraint_name,
                kcu.column_name,
                ccu.table_name AS foreign_table_name,
                ccu.column_name AS foreign_column_name
            FROM
                information_schema.table_constraints AS tc
            JOIN
                information_schema.key_column_usage AS kcu
                    ON tc.constraint_name = kcu.constraint_name
            JOIN
                information_schema.constraint_column_usage AS ccu
                    ON ccu.constraint_name = tc.constraint_name
            WHERE
                tc.constraint_type = \'FOREIGN KEY\'
                AND tc.table_name = :tableName
                AND kcu.column_name = :columnName
                AND tc.constraint_catalog = :database');
            $statement->execute(['tableName' => $tableName, 'columnName' => $name, 'database' => $this->database]);
            $foreignKeyData = $statement->fetch(PDO::FETCH_ASSOC);
            if ($foreignKeyData) {
                $foreignKey = $foreignKeyData['constraint_name'];
                $references = ['table' => $foreignKeyData['foreign_table_name'], 'column' => $foreignKeyData['foreign_column_name']];
            }

            // Unique constraint check
            $unique = false;
            $statement = $this->connection->prepare("SELECT constraint_name FROM information_schema.constraint_column_usage  WHERE table_name = :tableName AND column_name = :columnName AND table_catalog = :database");
            $statement->execute(['tableName' => $tableName, 'columnName' => $name, 'database' => $this->database]);
            $uniqueConstraintData = $statement->fetch(PDO::FETCH_ASSOC);
            if ($uniqueConstraintData && str_contains($uniqueConstraintData['constraint_name'], 'unique')) {
                $unique = true;
            }

            $column = new ColumnSchema(
                name: $name,
                original_data_type: $dataType,
                nullable: $nullable,
                default_value: $default,
                primary_key: $primaryKey,
                foreign_key: $foreignKey,
                references: $references,
                unique: $unique
            );
            $columns[$name] = $column;
        }

        return $columns;
    }

    public function getPK(string $tableName): ?string
    {
        if (!$this->isTableExist($tableName)) {
            throw new \InvalidArgumentException('Table '.$tableName.' doesn\'t exist in database');
        }

        $query = "SELECT a.attname AS column_name 
              FROM pg_constraint AS c 
              JOIN pg_attribute AS a ON a.attrelid = c.conrelid AND a.attnum = ANY(c.conkey) 
              WHERE c.contype = 'p' 
              AND c.conrelid = :tableName::regclass";

        $statement = $this->connection->prepare($query);
        $statement->execute([':tableName' => $tableName]);

        $result = $statement->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['column_name'] : null;
    }


    public function isTableExist(string $tableName): bool
    {
        $query = "SELECT 1 FROM information_schema.tables WHERE table_name = :tableName LIMIT 1";
        $statement = $this->connection->prepare($query);
        $statement->execute([':tableName' => $tableName]);

        return (bool)$statement->fetch(PDO::FETCH_ASSOC);
    }
}