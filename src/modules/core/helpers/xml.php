<?php
namespace Core;

use Phpr\Xml as PhprXml;

/**
 * @deprecated
 * Use Phpr\Xml
 */

class Xml
{
    /**
     * @deprecated
     * Use Phpr\Xml::createNode
     */
    public static function create_dom_element($document, $parent, $name, $value = null, $cdata = false)
    {
        return PhprXml::createNode($document, $parent, $name, $value, $cdata);
    }

    /**
     * @deprecated
     * Use Phpr\Xml::createCdata
     */
    public static function create_cdata($document, $parent, $value)
    {
        return PhprXml::createCdata($document, $parent, $value);
    }

    /**
     * @deprecated
     * Use Phpr\Xml::toPlainArray
     */
    public static function to_plain_array($document, $use_parent_keys = false)
    {
        if (is_a($document, 'DOMDocument')) {
            $xmlString = $document->saveXML();
        }
        return PhprXml::toPlainArray($xmlString, $use_parent_keys);
    }
}
