<?php

namespace Sanovskiy\SimpleObject\ModelsWriter\Writers;

interface ModelWriterlInterface
{
    public function fileExists();
    public function write();
}