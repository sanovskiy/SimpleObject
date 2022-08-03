<?php namespace Sanovskiy\SimpleObject;
/**
 * Copyright 2010-2017 Pavel Terentyev <pavel.terentyev@gmail.com>
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
 * Class AutoloadModels
 * @package Sanovskiy\SimpleObject
 */
class AutoloadModels
{
    /**
     * Registers autoloader for SimpleObject
     * @return bool
     */
    public static function register()
    {
        return spl_autoload_register(['Sanovskiy\\SimpleObject\\AutoloadModels', 'autoload']);
    }

    /**
     * @param string $classname
     *
     * @return bool
     * @throws Exception
     */
    public static function autoload( $classname)
    {
        if (!($config = self::detectConfig($classname))) {
            return false;
        }

        $path = Util::getSettingsValue('path_models', $config);
        $namespace = Util::getSettingsValue('models_namespace', $config);
        $classFilepath = $path . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, str_replace($namespace, '', $classname)) . '.php';

        if (!file_exists($classFilepath)) {
            throw new Exception('Class ' . $classname . ' not found. You must generate models first to use it');
        }
        require $classFilepath;

        return true;
    }

    /**
     * @param string $classname
     *
     * @return string|bool
     */
    protected static function detectConfig( $classname)
    {
        foreach (Util::getConfigNames() as $config) {

            if (strpos( $classname ,  Util::getSettingsValue('models_namespace', $config)) === 0) {
                return $config;
            }
        }
        return false;
    }
}