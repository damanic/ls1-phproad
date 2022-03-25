<?php

namespace FileSystem;

use ReflectionClass;

class Path
{
    // Returns a public path from an absolute one
    // eg: /home/mysite/public_html/welcome -> /welcome
    public static function getPublicPath($path)
    {
        $result = null;

        if (strpos($path, PATH_PUBLIC) === 0) {
            $result = str_replace("\\", "/", substr($path, strlen(PATH_PUBLIC)));
        }

        return $result;
    }

    // Finds the path to a class
    public static function getPathToClass($class_name)
    {
        $class_info = new ReflectionClass($class_name);
        return dirname($class_info->getFileName());
    }
}