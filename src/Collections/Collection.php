<?php

namespace Sanovskiy\SimpleObject\Collections;

use Exception;
use RuntimeException;
use Sanovskiy\SimpleObject\ActiveRecordAbstract;
use Sanovskiy\Traits\ArrayAccess;
use Sanovskiy\Traits\Countable;
use Sanovskiy\Traits\Iterator;

class Collection implements  \Iterator, \ArrayAccess, \Countable
{
    use Iterator, ArrayAccess, Countable;

    protected const ERROR_LOCKED = 'Collection is locked. You can\'t modify elements list';
    protected const ERROR_CLASS_MISMATCH = 'New object\'s class didn\'t match collection\'s class';
    protected const ERROR_CLASS_NOT_FOUND = 'Class not found';

    protected array $records = [];

    /**
     * @var string|null
     */
    protected ?string $className = null;

    /**
     * @var bool
     */
    protected bool $isLocked = false;

    /**
     * @var bool
     */
    protected bool $isUnlockable = true;

    //<editor-fold desc="Collection interface">
    private array $returnedIdList = [];

    /**
     * SimpleObject_Collection constructor.
     *
     * @param ActiveRecordAbstract[] $data Elements array
     * @param string|null $forceClass
     */
    public function __construct(array $data = [], ?string $forceClass = null)
    {
        if ($forceClass !== null) {
            $this->className = $forceClass;
        }
        if (count($data) > 0) {
            if (empty($this->className)) {
                $this->className = get_class($data[0]);
            }
            foreach ($data as $obj) {
                if ($obj instanceof $this->className) {
                    $this->records[] = $obj;
                }
            }
        }
    }

    /**
     *
     * @param bool $disallowUnlock
     *
     * @return Collection
     */
    public function lock(bool $disallowUnlock = false): static
    {
        $this->isLocked = true;
        if ($disallowUnlock) {
            $this->isUnlockable = false;
        }
        return $this;
    }

    /**
     *
     * @return Collection
     */
    public function unlock(): static
    {
        if (!$this->isUnlockable) {
            throw new RuntimeException('Collection is not unlockable.');
        }
        $this->isLocked = false;
        return $this;
    }

    /**
     * Sets class name for an empty collection
     *
     * @param string $name
     *
     * @return Collection
     */
    public function setClassName(string $name): static
    {
        if (empty($name)) {
            return $this;
        }
        if (count($this->records) > 0) {
            throw new RuntimeException('Collection not empty. You can\'t change classname');
        }
        if ($this->isLocked) {
            throw new RuntimeException('Collection is locked. You can\'t change classname');
        }
        $this->className = $name;
        return $this;
    }
    //</editor-fold>

    //<editor-fold desc="Random access">

    /**
     * Returns $n-th element
     *
     * @param int $n
     *
     * @return null
     */
    public function getElement(int $n = 0)
    {
        return $this->records[$n] ?? null;
    }

    /**
     * @return mixed
     */
    public function getNextRandomElement(): mixed
    {
        $forSelect = array_values(array_diff(array_keys($this->records), $this->returnedIdList));
        if (count($forSelect) > 0) {
            try {
                $returnIndex = $forSelect[random_int(0, count($forSelect) - 1)];
            } catch (Exception $e) {
                throw new RuntimeException($e->getMessage(),$e->getCode(),$e);
            }
            $this->returnedIdList[] = $returnIndex;
            return $this->records[$returnIndex];
        }
        return false;
    }

    /**
     * @return void
     */
    public function resetRandom(): void
    {
        $this->returnedIdList = [];
    }
    //</editor-fold>

    //<editor-fold desc="Array behavior">

    /**
     * @return mixed|null
     */
    public function shift(): mixed
    {
        if ($this->isLocked) {
            throw new RuntimeException(self::ERROR_LOCKED);
        }
        if (count($this->records) > 0) {
            return array_shift($this->records);
        }
        return null;
    }

    /**
     * @return mixed|null
     */
    public function pop(): mixed
    {
        if ($this->isLocked) {
            throw new RuntimeException(self::ERROR_LOCKED);
        }
        if (count($this->records) > 0) {
            return array_pop($this->records);
        }
        return null;
    }

    /**
     * @param $value
     *
     * @return bool
     */
    public function unshift($value): bool
    {
        if ($this->isLocked) {
            throw new RuntimeException(self::ERROR_LOCKED);
        }
        if (!is_object($value)) {
            return false;
        }
        if (empty($this->className)) {
            $this->className = get_class($value);
        }
        if (!($value instanceof $this->className) && !is_subclass_of($value, $this->className)) {
            throw new RuntimeException(self::ERROR_CLASS_MISMATCH);
        }
        array_unshift($this->records, $value);
        $this->records = array_values($this->records);
        return true;
    }

    /**
     * @param bool $reverse
     * @param string $field
     *
     * @return void
     * @throws Exception
     */
    public function reindexByField(bool $reverse = false, string $field = 'Id'): void
    {
        if ($this->isLocked) {
            throw new Exception(self::ERROR_LOCKED);
        }
        $result = [];
        foreach ($this->records as $indexValue) {
            $result[$indexValue->{$field}] = $indexValue;
        }
        if ($reverse) {
            krsort($result);
        } else {
            ksort($result);
        }
        $this->records = array_values($result);
    }
    //</editor-fold>

    //<editor-fold desc="Custom items actions">

    /**
     * @param string $method
     * @param array $args
     *
     * @return array
     */
    public function callForEach(string $method, array $args = []): array
    {
        $reply = [];
        for ($index = 0, $indexMax = count($this->records); $index < $indexMax; $index++) {
            $reply[$index] = call_user_func_array([$this->records[$index], $method], $args);
        }
        return $reply;
    }

    /**
     * @param      $property
     * @param null $value
     *
     * @return void
     */
    public function setForEach($property, $value = null): void
    {
        foreach ($this->records as $indexValue) {
            $indexValue->{$property} = $value;
        }
    }

    /**
     * @param string $property
     * @param mixed $value
     *
     * @return Collection
     */
    public function getElementsByPropertyValue(string $property, mixed $value): static
    {
        $elements = new self;
        foreach ($this->records as $indexValue) {
            if (!property_exists($indexValue, $property)) {
                throw new RuntimeException('Objects in current set does not have property ' . $property);
            }
            if ($indexValue->{$property} === $value) {
                $elements->push($indexValue);
            }
        }
        return $elements;
    }

    /**
     * @param $value
     *
     * @return bool
     */
    public function push($value): bool
    {
        if ($this->isLocked) {
            throw new RuntimeException(self::ERROR_LOCKED);
        }
        if (!is_object($value)) {
            return false;
        }
        if (empty($this->className)) {
            $this->className = get_class($value);
        }
        if (!($value instanceof $this->className)) {
            throw new RuntimeException(self::ERROR_CLASS_MISMATCH);
        }
        $this->records[] = $value;
        return true;
    }

    /**
     * @param string $method
     * @param mixed $value
     *
     * @return Collection
     * @throws Exception
     */
    public function getElementsByFunctionResult(string $method, mixed $value): static
    {
        $elements = new self;
        foreach ($this->records as $indexValue) {
            if (!method_exists($indexValue, $method)) {
                throw new Exception('Objects in current set does not have method ' . $method);
            }
            if ($indexValue->{$method}() === $value) {
                $elements->push($indexValue);
            }
        }
        return $elements;
    }

    /**
     * @return array
     */
    public function getAllRecords(): array
    {
        return $this->records;
    }

    /**
     * @param string|array $property
     *
     * @return array
     */
    public function getFromEach(string|array $property): array
    {
        $values = [];
        foreach ($this->records as $index => $element) {
            if (!is_array($property)) {
                $values[$index] = $element->{$property};
            } else {
                $_ = [];
                foreach ($property as $prop) {
                    $_[$prop] = $element->{$prop};
                }
                $values[$index] = $_;
            }
        }
        return $values;
    }
    //</editor-fold>

}