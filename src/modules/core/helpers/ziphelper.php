<?php
namespace Core;

use FileSystem\Zip as Zip;

/**
 * @deprecated
 * Use FileSystem\Zip
 */
class ZipHelper extends Zip
{

    /**
     * @deprecated
     * Use Phpr\Zip
     */
    public static function unzip($path, $archivePath, $no_set_permissions = false, $replace_files = true)
    {
        Zip::unzip($path, $archivePath, $replace_files, !$no_set_permissions);
    }
}
