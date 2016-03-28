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
 * Class SimpleObject_BaseModelGenerator
 */
class SimpleObject_BaseModelGenerator extends SimpleObject_PHPObjectGenerator
{
    /**
     * SimpleObject_ObjectGenerator constructor.
     * @param $clasName
     * @param bool $abstract
     * @return void
     */
    public function __construct($className, $abstract)
    {
        $className = 'Model_Base_'.$className;
        parent::__construct($className, $abstract);
    }

}