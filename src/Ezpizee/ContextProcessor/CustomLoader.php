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
    spl_autoload_register(function ($class) {
      $parts = explode(self::$delimiter, trim($class, self::$delimiter));
      $passed = false;
      if (in_array($class, self::$objects)) {
        $passed = true;
      }
      else if (isset(self::$packages[$parts[0]])) {
        $file = self::$packages[$parts[0]] . DS . str_replace(self::$delimiter, DS, $class) . '.php';
        if (!file_exists($file)) {
          if (strpos($file, DS . $parts[0] . DS . $parts[0] . DS) !== false) {
            $file = str_replace(DS . $parts[0] . DS . $parts[0] . DS, '', $file);
          }
          else if (strpos($file, DS . strtolower($parts[0]) . DS . $parts[0] . DS) !== false) {
            $tmp = str_replace(DS . strtolower($parts[0]) . DS . $parts[0] . DS, DS . $parts[0] . DS, $file);
            if (!file_exists($tmp)) {
              $tmp = str_replace(DS . strtolower($parts[0]) . DS . $parts[0] . DS, DS . strtolower($parts[0]) . DS, $file);
            }
            $file = $tmp;
          }
          else if (strpos($file, DS . $parts[0] . DS . strtolower($parts[0]) . DS) !== false) {
            $tmp = str_replace(DS . $parts[0] . DS . strtolower($parts[0]) . DS, DS . $parts[0] . DS, $file);
            if (!file_exists($tmp)) {
              $tmp = str_replace(DS . $parts[0] . DS . strtolower($parts[0]) . DS, DS . strtolower($parts[0]) . DS, $file);
            }
            $file = $tmp;
          }
          else if (strpos($file, DS . strtolower($parts[0]) . DS . strtolower($parts[0]) . DS) !== false) {
            $tmp = str_replace(DS . strtolower($parts[0]) . DS . strtolower($parts[0]) . DS, DS . $parts[0] . DS, $file);
            if (!file_exists($tmp)) {
              $tmp = str_replace(DS . strtolower($parts[0]) . DS . strtolower($parts[0]) . DS, DS . strtolower($parts[0]) . DS, $file);
            }
            $file = $tmp;
          }
        }
        if (file_exists($file)) {
          self::$objects[] = $class;
          self::$files[$class] = $file;
          include $file;
          $passed = true;
        }
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
