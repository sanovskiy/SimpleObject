#!/usr/bin/env php
<?php
@define('APPLICATION_ENV',"pater_dev");
/**
 * CLI script
 */
// Define path to application directory
defined('APPLICATION_PATH')
        || define('APPLICATION_PATH', realpath(dirname(__FILE__) . '/../application'));
@define('APPLICATION_ENV','development');
// Define application environment
defined('APPLICATION_ENV')
        || define('APPLICATION_ENV', (getenv('APPLICATION_ENV') ? getenv('APPLICATION_ENV') : 'production'));

// Ensure library/ is on include_path
set_include_path(implode(PATH_SEPARATOR, array(
            realpath(APPLICATION_PATH . '/../library'),
            get_include_path(),
        )));

class App {

    /**
     * @var Zend_Db_Adapter_Pdo_Mysql
     */
    static protected $DBCon;

    /**
     * @var Zend_Application
     */
    static protected $Application;

    /**
     *
     * @var Console_Controller
     */
    static protected $CC;

    protected static function Init() {
        /** Zend_Application */
        require_once 'Zend/Application.php';

        // Create application, bootstrap, and run
        self::$Application = new Zend_Application(
                        APPLICATION_ENV,
                        APPLICATION_PATH . '/configs/application.ini'
        );

        self::$Application->getBootstrap()->bootstrap('Common');
        Zend_Loader_Autoloader::getInstance()->registerNamespace('Console_');
        self::$Application->getBootstrap()->bootstrap('Db');

        self::$CC = Console_Controller::getInstance();

        self::$DBCon = Zend_Registry::get('db');
    }

    public static function Main() {
        self::Init();
        $dbtables = array();
        $stmt = self::$DBCon->query('SHOW TABLES FROM ' . Zend_Registry::get('config')->db->params->dbname);
        while ($_ = $stmt->fetch(Zend_Db::FETCH_NUM)) {
            $dbtables[] = $_[0];
        }

        $classes = array();

        foreach ($dbtables as $table) {
            $_t = array();
            $_t['table'] = $table;
            $_t['class_name'] = self::CCName($table);
            $_t['filename'] = self::CCName($table);
            $_t['base_class_name'] = 'BaseModel_' . self::CCName($table);
            $_t['fields'] = array();

            $columns = self::$DBCon->query('DESCRIBE ' . $table)->fetchAll();
            //self::$CC->showDump($columns);
            foreach ($columns as $col) {
                $_c = array();
                $_c['name'] = $col['Field'];
                $_c['field_type'] = $col['Type'];
                $_c['property_name'] = $col['Field'] == 'id' ? 'ID' : self::CCName($col['Field']);
                switch ($col['Type']) {
                    default:
                        //self::$CC->dropText($col['Type']);
                        break;
                    case 'timestamp':
                    case 'date':
                    case 'datetime':
                        //self::$CC->dropText($col['Type'], Console_Colors::LIGHT_BLUE);
                        $_c['field2PropertyTransform'] = 'date2time';
                        $_c['property2FieldTransform'] = 'time2date';
                        $_c['field2ReturnTransform'] = 'time2date|d.m.Y H:i';
                        break;
                    case 'tinyint(1)':
                        //self::$CC->dropText($col['Type'], Console_Colors::LIGHT_GREEN);
                        $_c['field2PropertyTransform'] = 'digit2boolean';
                        $_c['property2FieldTransform'] = 'boolean2digit';
                        break;
                }
                $_t['fields'][] = $_c;
            }

            $classes[$table] = $_t;
        }
        //self::$CC->showDump($classes);
        //die();
        $modelsPath = realpath(dirname(__FILE__) . '/../application/models');
        $formsPath = realpath(dirname(__FILE__) . '/../application/forms');
        $dir = opendir($modelsPath . '/base/');
        while ($f = readdir($dir)) {
            if (is_dir($modelsPath . '/base/' . $f)) {
                continue;
            }
            unlink($modelsPath . '/base/' . $f);
        }
        $dir = opendir($formsPath . '/base/');
        while ($f = readdir($dir)) {
            if (is_dir($formsPath . '/base/' . $f)) {
                continue;
            }
            unlink($formsPath . '/base/' . $f);
        }
        $view = new Zend_View();
        $view->setScriptPath(dirname(__FILE__).'/class_templates');
        
        self::$CC->increaseOutputIndent();
        foreach ($classes as $class) {
            self::$CC->dropText($class['class_name'], Console_Colors::LIGHT_BLUE);

            $baseModelFile = $modelsPath . '/base/base' . $class['filename'] . '.php';
            $modelFile = $modelsPath . '/' . $class['filename'] . '.php';
            $view->classData = $class;
            
            if (file_exists($baseModelFile)) {
                unlink($baseModelFile);
            }
            file_put_contents($baseModelFile, $view->render('baseModelTemplate.php'));
            if (!file_exists($modelFile)) {
                file_put_contents($modelFile, $view->render('modelTemplate.php'));
            }

            $baseFormFile = $formsPath . '/base/' . $class['filename'] . 'Edit.php';
            $formFile = $formsPath . '/' . $class['filename'] . 'Edit.php';
            if (file_exists($baseFormFile)) {
                unlink($baseFormFile);
            }
            file_put_contents($baseFormFile, $view->render('baseFormTemplate.php'));
            if (!file_exists($formFile)) {
                file_put_contents($formFile, $view->render('formTemplate.php'));
            }
        }
        self::$CC->decreaseOutputIndent();
        self::$CC->dropText('Models generated successfully', Console_Colors::LIGHT_GREEN);
    }

    static protected function CCName($string, $firstCharUpper = true) {
        $s = strtolower($string);
        $s = str_replace('_', ' ', $s);
        $s = str_replace('-', ' ', $s);
        $s = ucwords($s);
        $s = str_replace(' ', '', $s);
        if (!$firstCharUpper) {
            $s = strtolower(substr($s, 0, 1)) . substr($s, 1);
        }
        return $s;
    }

}

App::Main();