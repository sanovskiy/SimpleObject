<?php

namespace Sanovskiy\SimpleObject\ModelsWriter\Writers;

interface ModelWriterInterface
{
    public function fileExists();
    public function write();
    public function setReferences(array $references);
}