<?php

namespace Phpr;

use Phpr;

/**
 * PHPR HTML helper
 *
 * This class contains functions that may be useful for working with HTML.
 */
class Html
{
    /**
     * Converts all applicable characters to HTML entities. For example: "<-" becomes "&lt;-"
     *
     * @param string $Str Specifies the string to encode.
     * @return string
     */
    public static function encode($Str)
    {
        return htmlentities($Str, ENT_COMPAT, 'UTF-8');
    }

    /**
     * Converts all HTML entities to their applicable characters . For example: "&lt;-" becomes "<-"
     *
     * @param string $Str Specifies the string to decode.
     * @return string
     */
    public static function decode($Str)
    {
        return strtr($Str, array_flip(get_html_translation_table(HTML_ENTITIES, ENT_QUOTES)));
    }

    /**
     * Formats a list of attributes to use in the opening tag.
     * This function is mostly used by other helpers.
     *
     * @param array $Attrubutes Specifies a list of attributes.
     * @param array $Defaults Specifies a list of default attribute values.
     *                          If the attribute is omitted in the Attributes
     *                          list the default value will be used.
     * @return string
     */
    public static function formatAttributes($Attributes, $Defaults = array())
    {
        foreach ($Defaults as $AttrName => $AttrValue) {
            if (!array_key_exists($AttrName, $Attributes)) {
                $Attributes[$AttrName] = $Defaults[$AttrName];
            }
        }

        $result = array();
        foreach ($Attributes as $AttrName => $AttrValue) {
            if (strlen($AttrValue)) {
                $result[] = $AttrName . "=\"" . $AttrValue . "\"";
            }
        }

        return implode(" ", $result);
    }

    /**
     * Truncates HTML string
     * Thanks to www.phpinsider.com/smarty-forum/viewtopic.php?t=533
     * @param string $string Specifies a string to truncate
     * @param integer $length Specifies a string length
     * @return string
     */
    public static function limitCharacters($string, $length)
    {
        if (!empty($string) && $length > 0) {
            $is_text = true;
            $ret = "";
            $i = 0;

            $current_char = "";
            $last_space_pos = -1;
            $last_char = "";

            $tags_array = array();
            $current_tag = "";
            $tag_level = 0;

            $no_tag_length = strlen(strip_tags($string));

            for ($j = 0; $j < strlen($string); $j++) {
                $current_char = substr($string, $j, 1);
                $ret .= $current_char;

                if ($current_char == "<") {
                    $is_text = false;
                }

                if ($is_text) {
                    if ($current_char == " ") {
                        $last_space_pos = $j;
                    } else {
                        $last_char = $current_char;
                    }

                    $i++;
                } else {
                    $current_tag .= $current_char;
                }

                if ($current_char == ">") {
                    $is_text = true;

                    if ((strpos($current_tag, "<") !== false) &&
                        (strpos($current_tag, "/>") === false) &&
                        (strpos($current_tag, "</") === false)) {
                        if (strpos($current_tag, " ") !== false) {
                            $current_tag = substr($current_tag, 1, strpos($current_tag, " ") - 1);
                        } else {
                            $current_tag = substr($current_tag, 1, -1);
                        }

                        array_push($tags_array, $current_tag);
                    } else {
                        if (strpos($current_tag, "</") !== false) {
                            array_pop($tags_array);
                        }
                    }

                    $current_tag = "";
                }

                if ($i >= $length) {
                    break;
                }
            }

            if ($length < $no_tag_length) {
                if ($last_space_pos != -1) {
                    $ret = substr($string, 0, $last_space_pos) . '...';
                } else {
                    $ret = substr($string, $j) . '...';
                }
            }

            while (sizeof($tags_array) != 0) {
                $a_tag = array_pop($tags_array);
                $ret .= "</" . $a_tag . ">\n";
            }
        } else {
            $ret = "";
        }

        return $ret;
    }


    /**
     * Returns any orhpaned HTML tags
     * @param string $string Specifies a string to evaluate
     * @param bool $is_reopen Return open or close tags
     * @return string
     */
    public static function getOrphanTags($string, $is_reopen = false)
    {
        preg_match_all('/<(.*?)>/s', stripslashes($string), $out);

        $html_arr_close = $html_arr_open = array();
        foreach ($out[1] as $key => $val) {
            if (preg_match("/br.*/", $val) || empty($val)) {
                continue;
            }

            $val_arr = explode(" ", $val);
            $val = $val_arr[0];

            if (preg_match("/^\//", $val)) {
                $html_arr_close[] = strtolower($val);
            } else {
                $html_arr_open[] = strtolower($val);
            }
        }

        $not_closed_tags = array();
        foreach ($html_arr_open as $tag) {
            $key = array_search("/" . $tag, $html_arr_close);
            if ($key !== false) {
                unset($html_arr_close[$key]);
            } else {
                $not_closed_tags[] = $tag;
            }
        }

        $closed_tags_str = '';
        foreach ($not_closed_tags as $tag) {
            $closed_tags_str .= (!$is_reopen) ? "</" . $tag . ">" : "<" . $tag . ">";
        }

        return $closed_tags_str;
    }


    /**
     * Strips HTML tags and converts HTML entities to characters
     *
     * @param string $Str A string to process
     * @param int $Len Optional length to trim the string
     * @return string
     */
    public static function plainText($Str, $Len = null)
    {
        $Str = strip_tags($Str);

        if ($Len !== null) {
            $Str = self::strTrim($Str, $Len);
        }

        return htmlspecialchars_decode($Str);
    }

    /**
     * Replaces new line characters with HTML paragraphs and line breaks
     *
     * @param string $text Specifies a text to process
     * @return string
     */
    public static function paragraphize($text)
    {
        $text = preg_replace('/\r\n/m', "[-LB-]", $text);
        $text = preg_replace('/\n/m', "[-LB-]", $text);
        $text = preg_replace('/\r/m', "[-LB-]", $text);
        $text = str_replace("[-LB-][-LB-][-LB-]", "[-LB-][-LB-]", $text);

        $text = preg_replace('/\s+/m', " ", $text);

        $text = preg_replace('/\[-LB-\]\[-LB-\]/m', "</p><p>", $text);

        $text = preg_replace('/\[-LB-\]/m', "<br/>\r\n", $text);
        $text = "<p>" . $text . "</p>";

        $text = str_replace("<p></p>", "", $text);
        $text = preg_replace(",\<p\>\s*\<br/\>\s*\</p\>,m", "", $text);
        $text = preg_replace(",\<p\>\s*\<br/\>,m", "<p>", $text);
        $text = str_replace("<br/>\r\n</p>", "</p>", $text);
        $text = str_replace("\r\n\r\n", "", $text);
        $text = str_replace("</p><p>", "</p>\r\n\r\n<p>", $text);

        return $text;
    }

    /**
     * Replaces HTML paragraphs and line breaks with new line characters
     *
     * @param string $text Specifies a text to process
     * @return string
     */
    public static function deparagraphize($text)
    {
        $result = str_replace('<p>', '', $text);
        $result = str_replace('</p>', '', $result);
        $result = str_replace('<br/>', '', $result);
        $result = str_replace('<br>', '', $result);

        return $result;
    }

    /**
     * Truncates a string
     *
     * @param string $Str A string to process
     * @param int $Len Length to trim the string
     * @param bool $Right Truncate the string from the beginning
     * @return string
     */
    public static function strTrim($Str, $Len, $Right = false)
    {
        $StrLen = mb_strlen($Str);
        if ($StrLen > $Len) {
            if (!$Right) {
                return mb_substr($Str, 0, $Len - 3) . '...';
            } else {
                return '...' . mb_substr($Str, $StrLen - $Len + 3, $Len - 3);
            }
        }

        return $Str;
    }

    /**
     * Truncates a string by removing characters from the middle of the string
     *
     * @param string $Str A string to process
     * @param int $Len Length to trim the string
     * @return string
     */
    public static function strTrimMiddle($Str, $Len)
    {
        $StrLen = mb_strlen($Str);
        if ($StrLen > $Len) {
            if ($Len > 3) {
                $Len = $Len - 3;
            }

            $CharsStart = floor($Len / 2);
            $CharsEnd = $Len - $CharsStart;

            return trim(mb_substr($Str, 0, $CharsStart)) . '...' . trim(mb_substr($Str, -1 * $CharsEnd));
        }

        return $Str;
    }

    /**
     * Removes all line breaks and repeating spaces from a string
     *
     * @param string $str Specifies a string to process
     * @return string
     */
    public static function removeRedundantSpaces($str)
    {
        $str = str_replace("\r\n", ' ', $str);
        $str = str_replace("\n", ' ', $str);
        $str = str_replace("\t", ' ', $str);

        $cnt = 1;
        while ($cnt) {
            $str = str_replace('  ', ' ', $str, $cnt);
        }

        return $str;
    }

    /**
     * Adds the <span class="highlight"></span> elements around specific words. The string should not contain
     * any HTML characters. The function trims all new line characters.
     *
     * @param string $str Specifies a string to process
     * @param array $words Array of words to highlight
     * @param int $trim_length Allows to leave only a part of the source string, surrounding the
     *                            first occurrence of specified words. The parameter specifies how
     *                            many symbols to leave before and after the first occurrence.
     *
     * @param int    &$cnt Returns a number of words highlighted
     * @return string
     */
    public static function highlightWords($str, $words, $trim_length, &$cnt)
    {
        $cnt = 0;

        if (!$words) {
            return $str;
        }

        $str = self::removeRedundantSpaces($str);

        /*
         * Cut the string
         */

        $upper_str = mb_strtoupper($str);

        if ($trim_length) {
            foreach ($words as $word) {
                $pos = mb_strpos($upper_str, mb_strtoupper($word));
                if ($pos !== false) {
                    $orig_length = mb_strlen($str);
                    $trim_start = max($pos - $trim_length, 0);
                    $trim_end = $pos + mb_strlen($word) + $trim_length;

                    $str = mb_substr($str, $trim_start, $pos - $trim_start + mb_strlen($word) + $trim_length);

                    if ($trim_start > 0) {
                        $str = '...' . $str;
                    }

                    if ($orig_length > $trim_end) {
                        $str .= '...';
                    }

                    break;
                }
            }
        }

        /*
         * Highlight all occurrences
         */

        $str = h($str);
        $upper_str = mb_strtoupper($str);

        foreach ($words as $word) {
            $pos = mb_strpos($upper_str, mb_strtoupper($word));
            if ($pos !== false) {
                $cnt++;
                $str = mb_substr($str, 0, $pos) .
                    '<span class="highlight">' .
                    mb_substr($str, $pos, mb_strlen($word)) .
                    '</span>' .
                    mb_substr($str, $pos + mb_strlen($word));

                $upper_str = mb_strtoupper($str);
            }
        }

        return $str;
    }

    /**
     * Returns a CSS class string determining a current browser.
     *
     * PHP CSS Browser Selector v0.0.1
     * Bastian Allgeier (http://bastian-allgeier.de)
     * http://bastian-allgeier.de/css_browser_selector
     * License: http://creativecommons.org/licenses/by/2.5/
     */
    public static function cssBrowserSelector($ua = null)
    {
        if ($ua) {
            $ua = strtolower($ua);
        } else {
            if (array_key_exists('HTTP_USER_AGENT', $_SERVER)) {
                $ua = strtolower($_SERVER['HTTP_USER_AGENT']);
            }
        }

        $g = 'gecko';
        $w = 'webkit';
        $s = 'safari';
        $b = array();

        // browser
        if (!preg_match('/opera|webtv/i', $ua) && preg_match('/msie\s(\d)/', $ua, $array)) {
            $b[] = 'ie ie' . $array[1];
        } else {
            if (strstr($ua, 'firefox/2')) {
                $b[] = $g . ' ff2';
            } else {
                if (strstr($ua, 'firefox/3.5')) {
                    $b[] = $g . ' ff3 ff3_5';
                } else {
                    if (strstr($ua, 'firefox/3')) {
                        $b[] = $g . ' ff3';
                    } else {
                        if (strstr($ua, 'gecko/')) {
                            $b[] = $g;
                        } else {
                            if (preg_match('/opera(\s|\/)(\d+)/', $ua, $array)) {
                                $b[] = 'opera opera' . $array[2];
                            } else {
                                if (strstr($ua, 'konqueror')) {
                                    $b[] = 'konqueror';
                                } else {
                                    if (strstr($ua, 'chrome')) {
                                        $b[] = $w . ' ' . $s . ' chrome';
                                    } else {
                                        if (strstr($ua, 'iron')) {
                                            $b[] = $w . ' ' . $s . ' iron';
                                        } else {
                                            if (strstr($ua, 'applewebkit/')) {
                                                $b[] = (preg_match(
                                                    '/version\/(\d+)/i',
                                                    $ua,
                                                    $array
                                                )) ? $w . ' ' . $s . ' ' . $s . $array[1] : $w . ' ' . $s;
                                            } else {
                                                if (strstr($ua, 'mozilla/')) {
                                                    $b[] = $g;
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        // platform
        if (strstr($ua, 'j2me')) {
            $b[] = 'mobile';
        } else {
            if (strstr($ua, 'iphone')) {
                $b[] = 'iphone';
            } else {
                if (strstr($ua, 'ipod')) {
                    $b[] = 'ipod';
                } else {
                    if (strstr($ua, 'mac')) {
                        $b[] = 'mac';
                    } else {
                        if (strstr($ua, 'darwin')) {
                            $b[] = 'mac';
                        } else {
                            if (strstr($ua, 'webtv')) {
                                $b[] = 'webtv';
                            } else {
                                if (strstr($ua, 'win')) {
                                    $b[] = 'win';
                                } else {
                                    if (strstr($ua, 'freebsd')) {
                                        $b[] = 'freebsd';
                                    } else {
                                        if (strstr($ua, 'x11') || strstr($ua, 'linux')) {
                                            $b[] = 'linux';
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        return join(' ', $b);
    }

    /**
     * Returns word "even" each even call for a specified counter.
     * Example: <tr class="<?=Phpr\Html::zebra('customer') ?>">
     * $param string $counter_name Specifies a counter name.
     */
    public static function zebra($counter_name)
    {
        global $zebra_counters;
        if (!is_array($zebra_counters)) {
            $zebra_counters = array();
        }

        if (!isset($zebra_counters[$counter_name])) {
            $zebra_counters[$counter_name] = 0;
        }

        $zebra_counters[$counter_name]++;
        return $zebra_counters[$counter_name] % 2 ? null : 'even';
    }

    /**
     * Outputs a pagination markup with AJAX next and previous link handlers
     * @param Phpr\Pagination $pagination Specifies a pagination object
     * @param string $next_page_handler JavaScript code for handling the next page link
     * @param string $prev_page_handler JavaScript code for handling the previous page link
     * @param string $exact_page_handler JavaScript code for handling the page number page link.
     * The link should contain the %s sequence for substituting the page index
     */
    public static function ajaxPagination(
        $pagination,
        $next_page_handler = 'null',
        $prev_page_handler = 'null',
        $exact_page_handler = 'null'
    ) {
        $cur_page_index = $pagination->getCurrentPageIndex();
        $page_number = $pagination->getPageCount();

        $result = '<div class="pagination">';

        // Summary
        $result .= '<div class="summary">';
        $result .= '<p>';
        $result .= '<span class="interval">Showing  ';
        $result .= '<strong>' . ($pagination->getFirstPageRowIndex() + 1) . '-' . ($pagination->getLastPageRowIndex() + 1) . '</strong> ';
        $result .= 'of </span>';
        $result .= '<strong class="row-count" id="list_row_count_label">' . $pagination->getRowCount() . '</strong> ';
        $result .= 'records.';
        $result .= '</p>';
        $result .= '</div>';

        $result .= '<ul class="pages">';

        // Previous link
        //
        $result .= '<li>';
        if ($cur_page_index) {
            $result .= '<a href="javascript:;" onclick="return ' . sprintf(
                    $next_page_handler,
                    $cur_page_index - 1
                ) . '">';
        }

        $result .= '&#x2190; Previous';
        if ($cur_page_index) {
            $result .= '</a>';
        }

        $result .= '</li>';

        // Pages
        //

        if ($page_number < 11) {
            for ($i = 1; $i <= $page_number; $i++) {
                if ($i != $cur_page_index + 1) {
                    $result .= '<li><a href="javascript:;" onclick="return ' . sprintf(
                            $exact_page_handler,
                            $i - 1
                        ) . '">' . $i . '</a></li>';
                } else {
                    $result .= '<li class="active"><span>' . $i . '</span></li>';
                }
            }
        } else {
            $start_index = $cur_page_index - 5;
            $end_index = $cur_page_index + 5;
            $last_page_index = $page_number - 1;

            if ($start_index < 0) {
                $start_index = 0;
            }

            if ($end_index > $last_page_index) {
                $end_index = $last_page_index;
            }

            if (($end_index - $start_index) < 11) {
                $end_index = $start_index + 11;
            }

            if ($end_index > $last_page_index) {
                $end_index = $last_page_index;
            }

            if (($end_index - $start_index) < 11) {
                $start_index = $end_index - 11;
            }

            if ($start_index < 0) {
                $start_index = 0;
            }

            $pages_str = null;

            for ($i = $start_index + 1; $i <= $end_index + 1; $i++) {
                if ($i != $cur_page_index + 1) {
                    $pages_str .= '<li><a href="javascript:;" onclick="return ' . sprintf(
                            $exact_page_handler,
                            $i - 1
                        ) . '">' . $i . '</a></li>';
                } else {
                    $pages_str .= '<li class="active"><span>' . $i . '</span></li>';
                }
            }

            if ($start_index > 0) {
                $start_links = '<li><a href="javascript:;" onclick="return ' . sprintf(
                        $exact_page_handler,
                        0
                    ) . '">1</a></li> ';

                if ($start_index > 1) {
                    $start_links .= '<li class="disabled"><span class="ellipsis">&#8230;</span></li>';
                }

                $pages_str = $start_links . $pages_str;
            }

            if ($end_index < $last_page_index) {
                if ($last_page_index - $end_index > 1) {
                    $pages_str .= '<li class="disabled"><span class="ellipsis">&#8230;</span></li>';
                }

                $pages_str .= '<li><a href="javascript:;" onclick="return ' . sprintf(
                        $exact_page_handler,
                        $last_page_index
                    ) . '">' . ($last_page_index + 1) . '</a></li>';
            }

            $result .= $pages_str;
        }

        // Next link
        //
        $result .= '<li>';

        if ($cur_page_index < $page_number - 1) {
            $result .= '<a href="javascript:;" onclick="return ' . sprintf(
                    $prev_page_handler,
                    $cur_page_index + 1
                ) . '">';
        }

        $result .= 'Next &#x2192;';
        if ($cur_page_index < $page_number - 1) {
            $result .= '</a>';
        }

        $result .= '</li>';

        // End
        $result .= '</ul>';

        $result .= '</div>';
        return $result;
    }

    /**
     * @TODO Uses Jquery, LS1 Uses MooTools!
     * Attaches the calendar control to a specified HTML field
     * @return string Returns java-script code string
     */
    public static function calendar($field_id, $date_format = '')
    {
        if (!strlen($date_format)) {
            $date_format = str_replace(
                array('%d', '%m', '%Y'),
                array('dd', 'mm', 'yy'),
                Phpr::$lang->getString('phpr.dates', "short_date_format")
            );
        } else {
            $date_format = str_replace(array('%d', '%m', '%Y'), array('dd', 'mm', 'yy'), $date_format);
        }

        $week = Phpr::$lang->getString('phpr.dates', 'week_abbr');
        $days = self::getLocaleDatesArray('A_weekday_', 7);
        $days_short = self::getLocaleDatesArray('a_weekday_', 7, 7);
        $days_min = self::getLocaleDatesArray('a_weekday_', 7, 7, 2);
        $months = self::getLocaleDatesArray('n_month_', 12);
        $months_short = self::getLocaleDatesArray('b_month_', 12);

        return "$('#" . $field_id . "').datepicker({
				dateFormat: '" . $date_format . "',
				dayNamesShort: [" . $days_short . "],
				dayNamesMin: [" . $days_min . "],
				dayNames: [" . $days . "],
				monthNames: [" . $months . "],
				monthNamesShort: [" . $months_short . "],
				beforeShow: function(input, inst) {
					var widget = jQuery(inst).datepicker('widget');
					widget.css('margin-left', jQuery(input).outerWidth() - widget.outerWidth());
				}
			});";
    }

    public static function getLocaleDatesArray($modifier, $num, $offset = 1, $max_chars = false)
    {
        $result = array();

        $index = $offset;
        $count = 1;
        while ($count <= $num) {
            $date_string = Phpr::$lang->getString('phpr.dates', $modifier . $index);

            if ($max_chars) {
                $date_string = substr($date_string, 0, $max_chars);
            }

            $result[] = "'" . $date_string . "'";
            $index++;
            $count++;

            if ($index > $num) {
                $index = 1;
            }
        }

        return implode(',', $result);
    }

    public static function cleanXss($string)
    {
        // Fix &entity\n;
        //

        $string = str_replace(array('&amp;', '&lt;', '&gt;'), array('&amp;amp;', '&amp;lt;', '&amp;gt;'), $string);
        $string = preg_replace('#(&\#*\w+)[\x00-\x20]+;#u', "$1;", $string);
        $string = preg_replace('#(&\#x*)([0-9A-F]+);*#iu', "$1$2;", $string);
        $string = html_entity_decode($string, ENT_COMPAT, 'UTF-8');

        // Remove any attribute starting with "on" or xmlns
        $string = preg_replace('#(<[^>]+[\x00-\x20\"\'\/])(on|xmlns)[^>]*>#iUu', "$1>", $string);

        // Remove javascript: and vbscript: protocols
        //

        $string = preg_replace(
            '#([a-z]*)[\x00-\x20\/]*=[\x00-\x20\/]*([\`\'\"]*)[\x00-\x20\/]*j[\x00-\x20]*a[\x00-\x20]*v[\x00-\x20]*a[\x00-\x20]*s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:#iUu',
            '$1=$2nojavascript...',
            $string
        );
        $string = preg_replace(
            '#([a-z]*)[\x00-\x20\/]*=[\x00-\x20\/]*([\`\'\"]*)[\x00-\x20\/]*v[\x00-\x20]*b[\x00-\x20]*s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:#iUu',
            '$1=$2novbscript...',
            $string
        );
        $string = preg_replace(
            '#([a-z]*)[\x00-\x20\/]*=[\x00-\x20\/]*([\`\'\"]*)[\x00-\x20\/]*-moz-binding[\x00-\x20]*:#Uu',
            '$1=$2nomozbinding...',
            $string
        );
        $string = preg_replace(
            '#([a-z]*)[\x00-\x20\/]*=[\x00-\x20\/]*([\`\'\"]*)[\x00-\x20\/]*data[\x00-\x20]*:#Uu',
            '$1=$2nodata...',
            $string
        );

        // Only works in IE: <span style="width: expression(alert('Ping!'));"></span>
        $string = preg_replace('#(<[^>]+[\x00-\x20\"\'\/])style[^>]*>#iUu', "$1>", $string);

        // Remove namespaced elements (we do not need them)
        $string = preg_replace('#</*\w+:\w[^>]*>#i', "", $string);

        // Remove really unwanted tags
        //

        do {
            $old_string = $string;
            $string = preg_replace(
                '#</*(applet|meta|xml|blink|link|style|script|embed|object|iframe|frame|frameset|ilayer|layer|bgsound|title|base)[^>]*>#i',
                "",
                $string
            );
        } while ($old_string !== $string);

        return $string;
    }


    /**
     * @deprecated
     */
    public static function trunc($string, $length)
    {
        Phpr::$deprecate->setFunction('trunc', 'limitCharacters');
        return self::limitCharacters($string, $length);
    }

    public static function css_browser_selector($ua = null)
    {
        Phpr::$deprecate->setFunction('css_browser_selector', 'cssBrowserSelector');
        return self::cssBrowserSelector($ua);
    }
}
