<?php
namespace Core;

use Phpr;
use Db\Helper as DbHelper;

/**
 * Class Core_Metrics
 * @deprecated
 *
*            This class was used to compile usage data and
*            submit a report to developers.
*
*            The server(s) that received this data are
 *           no longer active.
 */
class Metrics
{
    /**
     * @deprecated
     */
    public static function update_metrics()
    {
    }

    /**
     * @deprecated
     */
    public static function log_pageview()
    {
    }

    /**
     * @deprecated
     */
    public static function log_order($order)
    {
    }

    /**
     * @deprecated
     */
    protected static function extract_shipping_module_usage($last_update)
    {
        return array();
    }

    /**
     * @deprecated
     */
    protected static function extract_payment_module_usage($last_update)
    {
        return array();
    }
}
