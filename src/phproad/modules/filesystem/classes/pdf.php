<?php

namespace FileSystem;

use Phpr\Request as Request;

class PDF
{
    public static function loadTcpdf()
    {
        $request = new Request;
        define('K_TCPDF_EXTERNAL_CONFIG', true);
        define('K_PATH_MAIN', PATH_SYSTEM . '/vendor/tcpdf/');
        define('K_PATH_URL', $request->get_hostname() . '/phproad/vendor/tcpdf/');
        define('K_PATH_FONTS', K_PATH_MAIN . 'fonts/');
        define('K_PATH_CACHE', sys_get_temp_dir() . '/');
        define('K_TCPDF_THROW_EXCEPTION_ERROR', true);
        require_once(K_PATH_MAIN . 'tcpdf.php');
    }

    public static function createNew(
        $or = 'P',
        $unit = 'mm',
        $format = 'A4',
        $unicode = true,
        $encoding = 'UTF-8',
        $diskcache = false,
        $pdfa = false
    ) {
        self::loadTcpdf();
        return new \TCPDF($or, $unit, $format, $unicode, $encoding, $diskcache, $pdfa);
    }

}