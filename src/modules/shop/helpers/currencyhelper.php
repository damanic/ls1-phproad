<?php
namespace Shop;

use Phpr\ApplicationException;

class CurrencyHelper
{

    public static bool $allowUnknownCurrencyCodes = false;

    protected static array $cacheCurrencySettings = array();

    public static function get_currency_setting($currency_code)
    {
        if (!isset(self::$cacheCurrencySettings[$currency_code])) {
            self::$cacheCurrencySettings[$currency_code] = null;
            $currencies = new CurrencySettings();
            $obj = $currencies->where(
                'code = :code || iso_4217_code = :code',
                array( 'code' => $currency_code )
            )->find_all();
            if ($obj) {
                self::$cacheCurrencySettings[$currency_code] = $obj;
            }
        }
        return $obj = self::$cacheCurrencySettings[$currency_code];
    }

    public static function format_currency($num, $decimals, $currency_code)
    {
        if (strlen($num) && strlen($currency_code)) {
            $obj = self::get_currency_setting($currency_code);

            if ($obj) {
                $negative   = $num < 0;
                $neg_symbol = null;
                if ($negative) {
                    $num        *= - 1;
                    $neg_symbol = '-';
                }

                $num = number_format($num, $decimals, $obj->dec_point, $obj->thousands_sep);
                if ($obj->sign_before) {
                    return $neg_symbol . $obj->sign . $num;
                }

                return $neg_symbol . $num . $obj->sign;
            }
        }
        return null;
    }

    public static function convert_price($price, $currency_code)
    {

        $internal_currency = CurrencySettings::get();
        $currency_converter = CurrencyConverter::create();
        $to_currency = null;

        if (!is_numeric($price) || !$currency_code) {
            throw new ApplicationException('Cannot convert price: Invalid price/currency parameters given');
        }

        if (!self::$allowUnknownCurrencyCodes) {
            $to_currency = self::get_currency_setting($currency_code);
            if (!$to_currency) {
                throw new ApplicationException(
                    'Cannot convert price: Unknown currency code give (' . $currency_code . ')'
                );
            }
        }

        $to_currency_code = $to_currency ? $to_currency->code : $currency_code;

        if ($to_currency && $internal_currency->id != $to_currency->id) {
            return $currency_converter->convert($price, $internal_currency->code, $to_currency_code, 2);
        }
        return $price;
    }
}
