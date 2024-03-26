<?php

namespace Sanovskiy\SimpleObject\Interfaces;

interface ModelWriterInterface
{
    public function fileExists();
    public function write();
    public function setReferences(array $references);
}