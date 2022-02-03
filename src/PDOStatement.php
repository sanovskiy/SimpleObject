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
 * Class SimpleObject_PDOStatement
 */
class PDOStatement extends \PDOStatement
{

    /**
     * @var PDO|null
     */
    protected ?PDO $pdo;


    /**
     * SimpleObject_PDOStatement constructor.
     *
     * @param PDO $pdo
     */
    protected function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @param mixed $params
     *
     * @return bool
     * @throws \Exception
     * @internal param array|null $input_parameters
     */
    public function execute(?array $params = NULL): bool
    {
        $start = $this->pdo->getMicro();
        try {
            $result = parent::execute($params);
        } catch (\Exception $e) {
            $this->pdo->log('Error: ' . $e->getMessage() . ' ' . $this->queryString, ['bind' => $params]);
            throw $e;
        }
        $end = $this->pdo->getMicro();
        $this->pdo->registerTime($start, $end, $this->queryString);
        return $result;
    }

}