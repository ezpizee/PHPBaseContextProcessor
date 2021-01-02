<?php

namespace Ezpizee\ContextProcessor;

class CustomLoader
{
    private static $packages = array();
    protected static $objects = array();
    protected static $delimiter = "\\";

    private function __construct() {}

    public static function appendPackage(array $packages) {
        if (!empty($packages)) {
            foreach ($packages as $nameSpacePfx=>$dir) {
                if (!isset(self::$packages[$nameSpacePfx])) {
                    self::$packages[$nameSpacePfx] = $dir;
                }
            }
        }
    }

    public static final function exec()
    {
        spl_autoload_register(function($class){
            $parts = explode(self::$delimiter, trim($class, self::$delimiter));
            $passed = false;
            if (in_array($class, self::$objects)) {
                $passed = true;
            }
            else if (in_array($parts[0], self::$packages)) {
                $file = self::$packages[$parts[0]] . DS . str_replace(self::$delimiter, DS, $class) . '.php';
                if (!file_exists($file)) {
                    if (strpos($file, DS.$parts[0].DS.$parts[0].DS) !== false) {
                        $file = str_replace(DS.$parts[0].DS.$parts[0].DS, '', $file);
                    }
                    else if (strpos($file, DS.strtolower($parts[0]).DS.$parts[0].DS) !== false) {
                        $tmp = str_replace(DS.strtolower($parts[0]).DS.$parts[0].DS, DS.$parts[0].DS, $file);
                        if (!file_exists($tmp)) {
                            $tmp = str_replace(DS.strtolower($parts[0]).DS.$parts[0].DS, DS.strtolower($parts[0]).DS, $file);
                        }
                        $file = $tmp;
                    }
                    else if (strpos($file, DS.$parts[0].DS.strtolower($parts[0]).DS) !== false) {
                        $tmp = str_replace(DS.$parts[0].DS.strtolower($parts[0]).DS, DS.$parts[0].DS, $file);
                        if (!file_exists($tmp)) {
                            $tmp = str_replace(DS.$parts[0].DS.strtolower($parts[0]).DS, DS.strtolower($parts[0]).DS, $file);
                        }
                        $file = $tmp;
                    }
                    else if (strpos($file, DS.strtolower($parts[0]).DS.strtolower($parts[0]).DS) !== false) {
                        $tmp = str_replace(DS.strtolower($parts[0]).DS.strtolower($parts[0]).DS, DS.$parts[0].DS, $file);
                        if (!file_exists($tmp)) {
                            $tmp = str_replace(DS.strtolower($parts[0]).DS.strtolower($parts[0]).DS, DS.strtolower($parts[0]).DS, $file);
                        }
                        $file = $tmp;
                    }
                }
                if (file_exists($file)) {
                    self::$objects[] = $class;
                    include $file;
                    $passed = true;
                }
            }
            return $passed;
        });
    }

    public static final function getLoadedObjects(): array {return self::$objects;}
}