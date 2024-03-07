<?php

namespace Sanovskiy\SimpleObject\DatabaseParser;

use PDO;
use Sanovskiy\SimpleObject\DatabaseParser\Schemas\ColumnSchema;
use Sanovskiy\SimpleObject\DatabaseParser\Schemas\TableSchema;

class ParserMySQL extends AbstractParser
{

    /**
     * @return TableSchema[]
     */
    public function getDatabaseTables(): array
    {
        $statement = $this->connection->prepare('SELECT table_name FROM information_schema.tables WHERE table_schema = :database');
        $statement->execute(['database' => $this->database]);
        $tableList = $statement->fetchAll(PDO::FETCH_COLUMN);
        return array_combine($tableList,array_map(fn($tableName) => new TableSchema($tableName,$this),$tableList));
    }

    /**
     * @param string $tableName
     * @return ColumnSchema[]
     */
    public function getTableColumns(string $tableName): array
    {
        $statement = $this->connection->prepare("DESCRIBE $tableName");
        $statement->execute();
        $columnsData = $statement->fetchAll(PDO::FETCH_ASSOC);

        $columns = [];
        foreach ($columnsData as $columnData) {
            $name = $columnData['Field'];
            $dataType = $columnData['Type'];
            $nullable = $columnData['Null'] === 'YES';
            $default = $columnData['Default'] ?? null;
            $primaryKey = $columnData['Key'] === 'PRI';
            $foreignKey = null;
            $references = null;
            $unique = $columnData['Key'] === 'UNI';

            // Check if column is part of a foreign key constraint
            $statement = $this->connection->prepare('SELECT
                COLUMN_NAME,
                CONSTRAINT_NAME,
                REFERENCED_TABLE_NAME,
                REFERENCED_COLUMN_NAME
            FROM
                information_schema.KEY_COLUMN_USAGE
            WHERE
                TABLE_SCHEMA = :database
                AND TABLE_NAME = :table
                AND COLUMN_NAME = :column');
            $statement->execute([
                'database' => $this->database,
                'table' => $tableName,
                'column' => $name
            ]);
            $keyInfo = $statement->fetch(PDO::FETCH_ASSOC);
            if ($keyInfo) {
                $foreignKey = $keyInfo['CONSTRAINT_NAME'];
                $references = [
                    'table'=>$keyInfo['REFERENCED_TABLE_NAME'],
                    'column'=>$keyInfo['REFERENCED_COLUMN_NAME']
                ];
            }

            $column = new ColumnSchema(
                name: $name,
                data_type: $dataType,
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
}