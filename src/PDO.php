<?php namespace Sanovskiy\SimpleObject;

use Monolog\Logger;

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
 * Class \Sanovskiy\SimpleObject\PDO
 */
class PDO extends \PDO
{

    protected $queries_count = 0;
    protected $total_query_time = 0;
    protected $longest_query = '';
    protected $longest_query_time = 0;

    /**
     * @var Logger|null
     */
    protected $logger = null;

    /**
     * Sanovskiy\SimpleObject\PDO constructor.
     *
     * @param        $dsn
     * @param string $username
     * @param string $password
     * @param array  $driver_options
     */
    public function __construct($dsn, $username = '', $password = '', $driver_options = array())
    {
        parent::__construct($dsn, $username, $password, $driver_options);
        $this->setAttribute(\PDO::ATTR_STATEMENT_CLASS, array('Sanovskiy\SimpleObject\PDOStatement', array($this)));
    }

    public function setLogger(Logger $logger)
    {
        $this->logger = $logger;
    }

    public function log( $string,  $context = [])
    {
        if ($this->logger instanceof Logger) {
            $this->logger->info($string,$context);
        }
    }

    /**
     * @return array
     * @noinspection PhpUnused - It's used
     */
    public function getUsageInfo()
    {
        return [
            'TotalQueries'     => $this->queries_count,
            'TotalQueriesTime' => $this->total_query_time,
            'LongestQueryTime' => $this->longest_query_time,
            'LongestQuery'     => $this->longest_query
        ];
    }

    /**
     * @return float
     */
    public function getMicro()
    {
        return microtime(true);
    }

    /**
     * @param float $start
     * @param float $end
     * @param string $query
     */
    public function registerTime( $start,  $end,  $query = '')
    {
        $this->queries_count++;
        $time = $end - $start;
        $this->total_query_time += (int) round($time);
        if ($time > $this->longest_query_time) {
            $this->longest_query_time = (int) round($time);
            $this->longest_query = $query;
        }
        $this->log($query, ['total_time' => $time, 'start' => $start, 'end' => $end]);
    }

    /**
     * @param string $statement
     *
     * @return int
     */
    public function exec($statement)
    {
        $start = $this->getMicro();
        $result = parent::exec($statement);
        $end = $this->getMicro();
        $this->registerTime($start, $end, $statement);
        return $result;
    }

    /**
     * Overloads parent query method to add some profiling
     * Some IDEs can mark declaration of this method as incopatible with parent. That's not true.
     *
     * @param $statement
     * @param null $mode
     * @param null $arg3
     * @param array $ctorargs
     * @return false|\PDOStatement
     */
    public function query( $statement,  $mode = null, $arg3 = null, array $ctorargs = [])
    {
        $start = $this->getMicro();
        $result = parent::query($statement, $mode, $arg3,$ctorargs);
        $end = $this->getMicro();
        $this->registerTime($start, $end, $statement);
        return $result;
    }


}