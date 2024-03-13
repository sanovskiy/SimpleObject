<?php

namespace Sanovskiy\SimpleObject;

class ExtendedCLIMate extends \League\CLImate\CLImate
{
    protected int $indentation = 0;
    protected string $indentationCharacter = "\t";

    public function out(string $str): mixed
    {
        return parent::out($this->getIndentStr() . $str);
    }

    public function getIndentStr(): string
    {
        return str_repeat($this->indentationCharacter, $this->indentation);
    }

    public function increaseIndent(int $num = 1)
    {
        $this->indentation += $num;
    }

    public function decreaseIndent(int $num = 1)
    {
        $this->indentation -= $num;
        if ($this->indentation < 0) {
            $this->resetIndent();
        }
    }

    public function resetIndent()
    {
        $this->indentation = 0;
    }

    public function newline($num=1)
    {
        $this->inline(str_repeat(PHP_EOL,$num));
    }

    /**
     * @return int
     */
    public function getIndentation(): int
    {
        return $this->indentation;
    }

    /**
     * @param int $indentation
     */
    public function setIndentation(int $indentation): void
    {
        $this->indentation = $indentation > -1 ? $indentation : 0;
    }

    /**
     * @return string
     */
    public function getIndentationCharacter(): string
    {
        return $this->indentationCharacter;
    }

    /**
     * @param string $indentationCharacter
     */
    public function setIndentationCharacter(string $indentationCharacter): void
    {
        $this->indentationCharacter = $indentationCharacter;
    }
}