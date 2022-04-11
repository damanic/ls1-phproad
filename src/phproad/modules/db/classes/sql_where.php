<?php
namespace Db;

class Sql_Where extends Sql_Base
{
    private $where = array();
    private static $getMatches;

    public static function create()
    {
        return new self();
    }

    public function __toString()
    {
        return $this->build_where();
    }

    public function reset()
    {
        $this->where = array();
    }

    protected function _where($operator, $cond)
    {
        if (is_null($cond)) {
            return $this;
        }

        $args = func_get_args();
        // Off $operator
        array_shift($args);
        // Off $cond
        array_shift($args);

        if ($cond instanceof Sql_Where) {
            $cond = $cond->build_where();
        } else {
            if (!self::$getMatches) {
                self::$getMatches = function ($matches) {
                    return ':__table_name__.' . $matches[0];
                };
            }

            $cond = preg_replace_callback(
                '/^([a-z_0-9`]+)[\s|=]+/i',
                self::$getMatches,
                $cond
            );

            if (array_key_exists(0, $args) && is_array($args[0])) {
                $args = $args[0];
            }

            $cond = $this->prepare($cond, $args);
        }

        if (count($this->where) > 0) {
            $cond = ' ' . $operator . ' (' . trim($cond) . ')';
        } else {
            $cond = ' (' . trim($cond) . ')';
        }

        $this->where[] = $cond;
        return $this;
    }

    /**
     * Allows to limit the result of the {@link Db_ActiveRecord::find() find()} and {@link Db_ActiveRecord::find_all() find_all()} methods with SQL filter.
     * Pass a SQL WHERE string to the parameter:
     * <pre>$order->where('customer_id is not null')->find_all();</pre>
     * or use the <em>question mark</em> as a data placeholder to pass a parameter value. Parameter values are automatically escaped (sanitized).
     * <pre>$orders = $order->where('customer_id=?', 4)->find_all();</pre>
     *
     * @documentable
     * @return       Db_ActiveRecord
     */
    public function where()
    {
        $args = func_get_args();
        return call_user_func_array(array(&$this, '_where'), array_merge(array('AND'), $args));
    }

    /**
     * Adds OR statement to the where clause, allowing to to limit the result of the {@link Db_ActiveRecord::find() find()} and {@link Db_ActiveRecord::find_all() find_all()} methods with SQL filter.
     * Pass a SQL WHERE string to the parameter:
     * <pre>$order->where('customer_id is null')->orWhere('customer_id=4')->find_all();</pre>
     * or use the <em>question mark</em> as a data placeholder to pass a parameter value. Parameter values are automatically escaped (sanitized).
     * <pre>$orders = $order->orWhere('customer_id=?', 4)->find_all();</pre>
     *
     * @documentable
     * @return       Db_ActiveRecord
     */
    public function orWhere()
    {
        $args = func_get_args();
        return call_user_func_array(array(&$this, '_where'), array_merge(array('OR'), $args));
    }

    public function build_where()
    {
        $where = array();

        if (count($this->where)) {
            $where[] = implode(' ', $this->where);
        }

        return implode(' ', $where);
    }

    /**
     * @deprecated
     * @see reset()
     */
    public function reset_where()
    {
        $this->where = array();
    }
}
