<?php

namespace Sanovskiy\SimpleObject\ModelsWriter\Parsers;

use PDO;
use Sanovskiy\SimpleObject\ModelsWriter\Schemas\ColumnSchema;
use Sanovskiy\SimpleObject\ModelsWriter\Schemas\TableSchema;

class ParserMSSQL extends ParserAbstract
{

    public function getDatabaseTables(): array
    {
        $statement = $this->connection->prepare("SELECT table_name FROM information_schema.tables WHERE table_catalog = :database");
        $statement->execute(['database' => $this->database]);
        $tableList = $statement->fetchAll(PDO::FETCH_COLUMN);
        return array_combine($tableList, array_map(fn ($tableName) => new TableSchema($tableName, $this), $tableList));
    }

    public function getTableColumns(string $tableName): array
    {
        if(!$this->isTableExist($tableName)){
            throw new \InvalidArgumentException('Table '.$tableName.' doesn\'t exist in database');
        }
        $statement = $this->connection->prepare("SELECT column_name, data_type, is_nullable, column_default, ordinal_position
            FROM information_schema.columns
            WHERE table_name = :tableName
            AND table_catalog = :database");
        $statement->execute(['tableName' => $tableName, 'database' => $this->database]);
        $columnsData = $statement->fetchAll(PDO::FETCH_ASSOC);

        $columns = [];
        foreach ($columnsData as $columnData) {
            $name = $columnData['column_name'];
            $dataType = $columnData['data_type'];
            $nullable = $columnData['is_nullable'] === 'YES';
            $default = $columnData['column_default'];
            $primaryKey = false; // MSSQL doesn't directly specify primary keys in this table

            // Fetching primary key information
            $statement = $this->connection->prepare("SELECT column_name
                FROM information_schema.key_column_usage
                WHERE table_name = :tableName
                AND column_name = :columnName
                AND table_catalog = :database");
            $statement->execute(['tableName' => $tableName, 'columnName' => $name, 'database' => $this->database]);
            $primaryKeyData = $statement->fetch(PDO::FETCH_ASSOC);
            if ($primaryKeyData) {
                $primaryKey = true;
            }

            // Foreign key information retrieval
            $foreignKey = null;
            $references = null;
            // Fetching foreign key constraints
            $statement = $this->connection->prepare("SELECT  
    fk.name AS FK_NAME,    
    ref_tab.name AS REFERENCED_TABLE,
    ref_col.name AS REFERENCED_COLUMN
FROM 
    sys.foreign_key_columns AS fkc
INNER JOIN 
    sys.objects AS fk ON fk.object_id = fkc.constraint_object_id
INNER JOIN 
    sys.tables AS parent_tab ON parent_tab.object_id = fkc.parent_object_id
INNER JOIN 
    sys.schemas AS sch ON parent_tab.schema_id = sch.schema_id
INNER JOIN 
    sys.columns AS parent_col ON parent_col.column_id = fkc.parent_column_id AND parent_col.object_id = parent_tab.object_id
INNER JOIN 
    sys.tables AS ref_tab ON ref_tab.object_id = fkc.referenced_object_id
INNER JOIN 
    sys.columns AS ref_col ON ref_col.column_id = fkc.referenced_column_id AND ref_col.object_id = ref_tab.object_id
WHERE 
    parent_tab.name = :tableName 
    AND parent_col.name = :columnName");
            $statement->execute(['tableName' => $tableName, 'columnName' => $name]);
            $foreignKeyData = $statement->fetch(PDO::FETCH_ASSOC);
            if ($foreignKeyData) {
                $foreignKey = $foreignKeyData['FK_NAME'];
                $references = ['table' => $foreignKeyData['REFERENCED_TABLE'], 'column' => $foreignKeyData['REFERENCED_COLUMN']];
            }

            // Unique constraint check
            $unique = false;
            $statement = $this->connection->prepare("SELECT constraint_name FROM information_schema.constraint_column_usage 
                WHERE table_name = :tableName AND column_name = :columnName AND table_catalog = :database");
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

        $query = "SELECT COLUMN_NAME 
              FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
              WHERE OBJECTPROPERTY(OBJECT_ID(CONSTRAINT_SCHEMA + '.' + CONSTRAINT_NAME), 'IsPrimaryKey') = 1 
              AND TABLE_NAME = :tableName";

        $statement = $this->connection->prepare($query);
        $statement->execute([':tableName' => $tableName]);

        $result = $statement->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['COLUMN_NAME'] : null;
    }

    public function isTableExist(string $tableName): bool
    {
        $query = "SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = :tableName";
        $statement = $this->connection->prepare($query);
        $statement->execute([':tableName' => $tableName]);

        return (bool)$statement->fetch(PDO::FETCH_ASSOC);
    }
}