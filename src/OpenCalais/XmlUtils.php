<?php

declare(strict_types = 1);

namespace OpenCalais;

/**
 * Class XmlUtils
 *
 * @author Nicolas Duteil <nduteil@gmail.com>
 * @package OpenCalais
 */
class XmlUtils
{

    /**
     * Convert an array to a XML string
     * @param array $data
     * @param string $charset (optional)
     * @return string xml
     * @throws InvalidArgumentException
     */
    public static function arrayToXml($data, $charset = 'utf-8')
    {
        if (! is_array($data)) {
            throw new \InvalidArgumentException('Parameter must be an array');
        }

        $xml = self::buildXmlFromArray($data, new \SimpleXMLElement('<document></document>'));
        $xml = html_entity_decode($xml->asXML(), ENT_NOQUOTES, $charset);
        
        return $xml;
    }

    /**
     * Build an XML document; Recursive function
     * @param array $data
     * @param object $xml
     * @return object $xml
     */
    private static function buildXmlFromArray($data, $xml)
    {
        foreach ($data as $key => $value) {
    
            if (is_numeric($key)) {
                $key = 'item'.$key; // dealing with <0/>..<n/> issues
            }
    
            if (is_array($value)) {
                $subnode = $xml->addChild($key);
                self::buildXmlFromArray($value, $subnode);
            }
            else {
                $xml->addChild("$key", htmlspecialchars("$value"));
            }
    
        }
        
        return $xml;
    }

}