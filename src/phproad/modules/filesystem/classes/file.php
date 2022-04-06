<?php
namespace FileSystem;
use Phpr;

class File
{
    public static function getPermissions()
    {
        $permissions = Phpr::$config->get('FILE_PERMISSIONS');
        if ($permissions) {
            return $permissions;
        }

        $permissions = Phpr::$config->get('FILE_FOLDER_PERMISSIONS');
        if ($permissions) {
            return $permissions;
        }

        return 0777;
    }

    /**
     * Returns a file size as string (203 Kb)
     * @param int $size Specifies a size of a file in bytes
     * @return string
     */
    public static function sizeFromBytes($size)
    {
        if ($size < 1024) {
            return $size . ' byte(s)';
        }

        if ($size < 1024000) {
            return ceil($size / 1024) . ' Kb';
        }

        if ($size < 1024000000) {
            return round($size / 1024000, 1) . ' Mb';
        }

        return round($size / 1024000000, 1) . ' Gb';
    }

    /**
     * Returns the file name without extension
     */
    public static function getName($file_path)
    {
        return pathinfo($file_path, PATHINFO_FILENAME);
    }

    /**
     * Returns the file extension component
     */
    public static function getExtension($file_path)
    {
        return pathinfo($file_path, PATHINFO_EXTENSION);
    }

    /**
     * Outputs the content of a given file path
     */
    public static function print($filePath)
    {
        $chunksize = 1 * (1024 * 1024); // how many bytes per chunk
        $buffer = '';
        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            return false;
        }
        while (!feof($handle)) {
            $buffer = fread($handle, $chunksize);
            print $buffer;
        }
        return fclose($handle);
    }

}
