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
 * Class SimpleObject_AbstractObjectGenerator
 */
class SimpleObject_PHPObjectGenerator
{
    const CODE_INDENT = '    ';

    protected $className;
    protected $extends = null;
    protected $isAbstract = false;
    protected $properties = [];
    protected $constants = [];
    protected $methods = [];
    protected $allovedVisibility = ['public','private','protected'];

    /**
     * SimpleObject_ObjectGenerator constructor.
     * @param $clasName
     * @param bool $abstract
     * @return void
     */
    public function __construct($className, $abstract = false)
    {
        $this->className = $className;
        $this->isAbstract = $abstract;
    }

    public function setExtends($className)
    {
        $this->extends = $className;
    }

    /**
     * @param $name
     * @param null $defaultValue
     * @param string $visibility
     * @param bool $static
     * @throws SimpleObject_Exception
     */
    public function setProperty($name, $defaultValue = null, $visibility = 'public', $static=false)
    {
        if (!in_array($visibility,$this->allovedVisibility)){
            throw new SimpleObject_Exception('Visibility \''.$visibility.'\' is not allowed here');
        }

        $this->properties[$name] = [
            'visibility' => $visibility,
            'static' => (boolean) $static,
            'value' => $defaultValue,
        ];
    }

    /**
     * @param $name
     * @param $value
     */
    public function setConstant($name, $value)
    {
        $this->constants[$name] = (string) $value;
    }

    /**
     * @param $name
     * @param $code
     * @param string $visibility
     * @param bool $static
     * @param bool $abstract
     */
    public function setMethod($name, $code, $visibility = 'public', $static = false, $abstract = false)
    {
        $this->methods[$name] = [
            'visibility' => $visibility,
            'static' => (boolean) $static,
            'abstract' => (boolean) $abstract,
            'code' => $code,
        ];
    }

    public function getIndent($count=0)
    {
        return str_repeat(self::CODE_INDENT,$count);
    }

    /**
     * @return string
     */
    public function generate()
    {
        $codeArray = [];

        $codeArray[] = '<?php';
        $codeArray[] = file_get_contents(__DIR__.'/../CodeParts/license.txt');
        $line = ($this->isAbstract?'abstract':'');
        $line .= 'class '.$this->className;
        if (!is_null($this->extends)){
            $line .= 'extends '.$this->extends;
        }
        $codeArray[] = $line;
        $codeArray[] = '{';
        $codeArray[] = '';

        if (!empty($this->constants)){
            foreach ($this->constants as $name=>$value){
                $codeArray[] = $this->getIndent(1).'const '.$name.' = \''. addslashes($value) .'\';';
            }
        }

        $codeArray .= '}';
        return $codeArray;
    }
}