<?php
/**
 * Copyright 2010-2016 Pavel Terentyev <pavel.terentyev@gmail.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 */

/**
 * Class SimpleObject_Autoload
 */
class SimpleObject_Autoload
{
    /**
     * Registers autoloader for SimpleObject
     * @return bool
     */
    public static function register()
    {
        return spl_autoload_register(['SimpleObject_Autoload', 'autoload']);
    }

    /**
     * @param $classname
     * @return bool
     */
    public static function autoload($classname)
    {
        if (preg_match('/^Model\_/', $classname)) {
            return self::loadModel($classname);
        }
        $classpath = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..') . DIRECTORY_SEPARATOR . str_replace('_',
                DIRECTORY_SEPARATOR,
                $classname) . '.php';
        if (!file_exists($classpath)) {
            return false;
        }
        /** @noinspection PhpIncludeInspection */
        require $classpath;
        return true;
    }

    /**
     * @param $modelname
     * @return bool
     */
    private static function loadModel($modelname)
    {
        $realname = preg_replace('/^Model\_(.+)$/', '$1', $modelname);
        $modelNameParts = explode('_', $realname);
        $configNames = SimpleObject::getConfigNames();
        $configNames = array_map('strtolower',$configNames);
        $probableConfigName = strtolower($modelNameParts[0]);
        $modelsPath = SimpleObject::getSettingsValue('path_models', 'default');
        if (!in_array($probableConfigName, SimpleObject::getRestrictedConfigNames()) && in_array($probableConfigName,$configNames) && count($modelNameParts) > 1) {
            $modelsPath = SimpleObject::getSettingsValue('path_models', $probableConfigName);
            unset($modelNameParts[0]);
            $realname = implode('_', $modelNameParts);
        }

        $modelPath = $modelsPath . DIRECTORY_SEPARATOR . str_replace('_', DIRECTORY_SEPARATOR, $realname) . '.php';

        if (!file_exists($modelPath)) {
            return false;
        }
        /** @noinspection PhpIncludeInspection */
        require $modelPath;
        return true;
    }
}