<?php

use Phinx\Db\Adapter\AdapterInterface;
use Phinx\Migration\AbstractMigration;

class InitialTestDatabase extends AbstractMigration
{
    public function up()
    {
        $table = $this->table('record');
        $table
            ->addColumn('var_int', AdapterInterface::PHINX_TYPE_INTEGER)
            ->addColumn('var_string', AdapterInterface::PHINX_TYPE_STRING, ['limit' => 64])
            ->addColumn('var_text', AdapterInterface::PHINX_TYPE_TEXT)
            ->addColumn('var_enum', AdapterInterface::PHINX_TYPE_ENUM, ['values' => ['first', 'second', 'third']])
            ->addColumn('var_bool', AdapterInterface::PHINX_TYPE_BOOLEAN, ['default' => '1'])
            ->addColumn('var_datetime', AdapterInterface::PHINX_TYPE_DATETIME)
            ->addColumn('var_timestamp', AdapterInterface::PHINX_TYPE_TIMESTAMP, ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('var_set', AdapterInterface::PHINX_TYPE_SET, ['values' => ['set1', 'set2', 'set3']])
        ;
        $table->save();
    }

    public function down()
    {
        $this->table('record')->drop()->save();
    }
}
