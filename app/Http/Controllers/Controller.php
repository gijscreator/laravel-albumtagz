<?php

namespace App\Http\Controllers;

abstract class Controller
{
    abstract public function getProductType(): string;
}
