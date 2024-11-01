<?php
/**
 * @category UPS
 * @copyright UPS Company
 */

function ups_autoload($class)
{
    if (substr($class, 0, 3) !== 'Ups') {
        return;
    }

    $name = str_replace('\\', '/', substr($class,4));
    $path = __DIR__ . DIRECTORY_SEPARATOR . $name . '.php';
    if (!file_exists($path)) {
        return;
    }

    require_once $path;
}

spl_autoload_register('ups_autoload');