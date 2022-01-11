<?php

namespace Ezpizee\ContextProcessor;

class CustomLoader
{
    protected static $objects = array();
    protected static $delimiter = "\\";
    protected static $files = array();
    private static $packages = array();

    private function __construct()
    {
    }

    public static function packageExists(string $namespace): bool {return isset(self::$packages[$namespace]);}

    public static function appendPackage(array $packages, bool $invokeExecute = false)
    {
        if (!empty($packages)) {
            foreach ($packages as $nameSpacePfx => $dir) {
                if (!isset(self::$packages[$nameSpacePfx])) {
                    self::$packages[$nameSpacePfx] = $dir;
                }
            }
        }
        if ($invokeExecute === true) {
            self::exec();
        }
    }

    public static final function exec()
    {
        spl_autoload_register(function ($class){
            $parts = explode(self::$delimiter, trim($class, self::$delimiter));
            $file = "";
            $part = "";
            if (isset($parts[2]) && isset(self::$packages[$parts[0] . self::$delimiter . $parts[1] . self::$delimiter . $parts[2]])) {
                $part = $parts[0] . self::$delimiter . $parts[1] . self::$delimiter . $parts[2];
                $file = self::$packages[$part] . EZPIZEE_DS. str_replace(self::$delimiter, EZPIZEE_DS, $class) . '.php';
            }
            else if (isset($parts[1]) && isset(self::$packages[$parts[0] . self::$delimiter . $parts[1]])) {
                $part = $parts[0] . self::$delimiter . $parts[1];
                $file = self::$packages[$part] . EZPIZEE_DS. str_replace(self::$delimiter, EZPIZEE_DS, $class) . '.php';
            }
            else if (isset(self::$packages[$parts[0]])) {
                $part = $parts[0];
                $file = self::$packages[$part] . EZPIZEE_DS. str_replace(self::$delimiter, EZPIZEE_DS, $class) . '.php';
            }
            $file = str_replace(
                EZPIZEE_DS.$part.EZPIZEE_DS.$part.EZPIZEE_DS,
                EZPIZEE_DS.$part.EZPIZEE_DS,
                $file
            );
            $passed = isset(self::$objects[$part]);
            if (!empty($file) && $passed === false && file_exists($file)) {
                self::$objects[$class] = true;
                self::$files[$class] = $file;
                include $file;
                $passed = true;
            }
            return $passed;
        });
    }

    public static final function getLoadedObjects()
    : array
    {
        return self::$objects;
    }

    public static final function getDir(string $class)
    : string
    {
        return isset(self::$files[$class]) ? dirname(self::$files[$class]) : "";
    }

    public static final function getScriptName(string $class)
    : string
    {
        return isset(self::$files[$class]) ? self::$files[$class] : "";
    }
}
