<?php


use Phinx\Seed\AbstractSeed;

class TstData extends AbstractSeed
{
    public function run()
    {
        $data = [
            [
                'id'           => 1,
                'var_int'      => 123,
                'var_string'   => 'this is string',
                'var_text'     => 'this is text',
                'var_enum'     => 'first',
                'var_bool'     => 1,
                'var_datetime' => '2019-02-23 08:23:15',
                'var_set'      => 'set1',
            ],
            [
                'id'           => 2,
                'var_int'      => 321,
                'var_string'   => 'this is another string',
                'var_text'     => 'this is another text',
                'var_enum'     => 'second',
                'var_bool'     => 0,
                'var_datetime' => '1917-11-07 21:40:00',
                'var_set'      => 'set2',
            ],
        ];
        $t = $this->table('record');
        $t->truncate();
        $t->insert($data)->save();
    }
}
