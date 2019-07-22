<?php

declare(strict_types=1);

use Phinx\Console\PhinxApplication;
use Phinx\Wrapper\TextWrapper;
use PHPUnit\Framework\TestCase;
use Sanovskiy\SimpleObject\Util;

final class GenerationTest extends TestCase
{
    /**
     * @var TextWrapper
     */
    protected static $phinxApp;

    public static function setUpBeforeClass(): void
    {

        self::$phinxApp = new TextWrapper(new PhinxApplication());
        self::$phinxApp->setOption('configuration',
            implode(DIRECTORY_SEPARATOR, [dirname(__DIR__), 'config', 'phinx.php']));
        self::$phinxApp->setOption('environment', 'mysql');
        self::$phinxApp->getMigrate('mysql');
        self::$phinxApp->getSeed('mysql');
        $config = include implode(DIRECTORY_SEPARATOR, [dirname(__DIR__), 'config', 'phpunit.php']);
        $config = $config['database']['mysql'];
        try {
            $config['dbcon']['database'] = $config['dbcon']['name'];
            $config['dbcon']['driver'] = $config['dbcon']['adapter'];
            $config['dbcon']['password'] = $config['dbcon']['pass'];
            Util::init($config);
            Util::reverseEngineerModels(true);
        } catch (Exception $e) {
            self::throwException($e);
        }
    }

    public static function tearDownAfterClass(): void
    {
        self::$phinxApp->getRollback('mysql', 0);
    }

    public function testGeneratedModelFiles()
    {
        $this->assertFileExists(implode(DIRECTORY_SEPARATOR, [dirname(__DIR__, 2), 'models', 'Base', 'Record.php']));
        $this->assertFileExists(implode(DIRECTORY_SEPARATOR, [dirname(__DIR__, 2), 'models', 'Logic', 'Record.php']));

    }

    public function testModelLoad()
    {
        $instance = new \Sanovskiy\SimpleObject\models\Logic\Record(1);
        $this->assertObjectHasAttribute('propertiesMapping', $instance);
        $this->assertEquals(1, $instance->id);
        $this->assertEquals(123, $instance->VarInt);
        $this->assertEquals('this is string', $instance->VarString);
        $this->assertEquals('this is text', $instance->VarText);
        $this->assertEquals(strtotime('2019-02-23 08:23:15'), $instance->VarDatetime);
        $this->assertEquals('set1', $instance->VarSet);
    }

    public function testModelSave()
    {
        $instance = new \Sanovskiy\SimpleObject\models\Logic\Record(1);
        $num = random_int(1, 1000);
        $instance->VarInt = $num;
        $instance->save();
        $instance->reload();
        $this->assertEquals($num, $instance->VarInt);
    }

}