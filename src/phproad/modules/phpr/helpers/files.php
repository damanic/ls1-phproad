<?php

namespace Phpr;

use Phpr;
use FileSystem\File;
use FileSystem\Csv;
use FileSystem\Upload;
use FileSystem\Directory;

/**
 * @deprecated
 */
class Files
{
    public static $dir_copy_file_num = 0;

    /**
     * @deprecated
     */
    public static function maxUploadSize()
    {
        Phpr::$deprecate->setFunction('maxUploadSize', 'FileSystem\Upload::maxUploadSize');
        return Upload::maxUploadSize();
    }

    /**
     * @deprecated
     */
    public static function determineCsvDelimeter($path)
    {
        Phpr::$deprecate->setFunction('determineCsvDelimeter', 'FileSystem\Csv::determineCsvDelimeter');
        return Csv::determineCsvDelimeter($path);
    }

    /**
     * @deprecated
     */
    public static function outputCsvRow($Row, $separator = ';', $return_data = false)
    {
        Phpr::$deprecate->setFunction('outputCsvRow', 'FileSystem\Csv::outputCsvRow');
        return Csv::outputCsvRow($Row, $separator, $return_data);
    }

    /**
     * @deprecated
     */
    public static function convertCsvEncoding(&$data)
    {
        Phpr::$deprecate->setFunction('convertCsvEncoding', 'FileSystem\Csv::convertCsvEncoding');
        return Csv::convertCsvEncoding($data);
    }

    /**
     * @deprecated
     */
    public static function getCsvField(&$row, $index, $default = null)
    {
        Phpr::$deprecate->setFunction('getCsvField', 'FileSystem\Csv::getCsvField');
        return Csv::getCsvField($row, $index, $default);
    }

    /**
     * @deprecated
     */
    public static function csvRowIsEmpty(&$row)
    {
        Phpr::$deprecate->setFunction('csvRowIsEmpty', 'FileSystem\Csv::csvRowIsEmpty');
        return Csv::csvRowIsEmpty($row);
    }

    /**
     * @deprecated
     */
    public static function fileSize($size)
    {
        Phpr::$deprecate->setFunction('fileSize', 'FileSystem\File::sizeFromBytes');
        return File::sizeFromBytes($size);
    }

    /**
     * @deprecated
     */
    public static function validateUploadedFile($fileInfo)
    {
        Phpr::$deprecate->setFunction('validateUploadedFile', 'FileSystem\Upload::validateUploadedFile');
        Upload::validateUploadedFile($fileInfo);
    }

    /**
     * @deprecated
     */
    public static function extract_mutli_file_info($multi_file_info)
    {
        Phpr::$deprecate->setFunction('extract_mutli_file_info', 'FileSystem\Upload::extractMultiFileInfo');
        return Upload::extractMultiFileInfo($multi_file_info);
    }

    /**
     * @deprecated
     */
    public static function copyDir($source, $target, &$options = array())
    {
        Phpr::$deprecate->setFunction('copyDir', 'FileSystem\Directory::copy');
        Directory::copy($source, $target, $options);
    }

    /**
     * @deprecated
     */
    public static function removeDir($dir)
    {
        Phpr::$deprecate->setFunction('removeDir', 'FileSystem\Directory::delete');
        Directory::delete($dir);
    }

    /**
     * @deprecated
     */
    public static function removeDirRecursive($sDir)
    {
        Phpr::$deprecate->setFunction('removeDirRecursive', 'FileSystem\Directory::deleteRecursive');
        return Directory::deleteRecursive($sDir);
    }

    /**
     * @deprecated
     */
    public static function listSubdirectories($dir)
    {
        Phpr::$deprecate->setFunction('listSubdirectories', 'FileSystem\Directory::listSubdirectories');
        return Directory::listSubdirectories($dir);
    }


    /**
     * @deprecated
     */
    public static function getFolderPermissions()
    {
        Phpr::$deprecate->setFunction('getFolderPermissions', 'FileSystem\Directory::getPermissions');
        return Directory::getPermissions();
    }

    /**
     * @deprecated
     */
    public static function getFilePermissions()
    {
        Phpr::$deprecate->setFunction('getFilePermissions', 'FileSystem\File::getPermissions');
        return File::getPermissions();
    }

    /**
     * @deprecated
     */
    public static function readFile($filename)
    {
        Phpr::$deprecate->setFunction('readFile', 'FileSystem\File::print');
        return File::print($filename);
    }

    /**
     * @deprecated
     */
    public static function findFiles($directory, $file_types = null)
    {
        Phpr::$deprecate->setFunction('findFiles');
        return null;
    }

    /**
     * @deprecated
     */
    protected static function findFilesRecursive($directory, $base, $file_types)
    {
        Phpr::$deprecate->setFunction('findFilesRecursive');
        return null;
    }

    /**
     * @deprecated
     */
    public static function rootRelative($path)
    {
        Phpr::$deprecate->setFunction('rootRelative');
        return null;
    }
}
