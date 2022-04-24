<?php
namespace Core;

use Phpr;
use Phpr\SystemException;

/**
 * @deprecated
 * Cron is normally executed from command line.
 * This methods was being used by URL access points that can use their own methods
 * to control access.
 */
class CronManager
{
    public static function access_allowed()
    {
        $ip = Phpr::$request->getUserIp();
        $allowed_ips = Phpr::$config->get('CRON_ALLOWED_IPS', array());

        try {
            if (!in_array($ip, $allowed_ips)) {
                throw new SystemException('Cron access from the IP address '.$ip.' is denied.');
            }
        } catch (\Exception $ex) {
            echo "Error. ".h($ex->getMessage());
            return false;
        }
            
        return true;
    }
}
