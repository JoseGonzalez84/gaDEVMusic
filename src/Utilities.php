<?php

namespace App\Src;

class Utilities
{
    public function __construct()
    {
        
    }

    public static function checkIsAlreadyRunning(): bool
    {
        exec('ps -ax | grep -i '.NOMBRE_SCRIPT_EXE.' | grep -v grep', $salida);
        return count($salida) > 2;
    }
}