<?php

use Lizmap\Project\Reader\QgisXmlReader;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
class QgisReaderTest extends TestCase
{

    protected function getReader(array $elements)
    {
        $sXml = new SimpleXMLElement('<qgis></qgis>');
        $this->arrayToXml($elements, $sXml);
        $reader = new QgisXmlReader();
        $reader->XML($sXml->asXML());
        return $reader;
    }

    protected function arrayToXml(array $array, SimpleXMLElement &$xml)
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                if (array_keys($value) !== range(0, count($value) - 1)) {
                    $subnode = $xml->addChild($key);
                    $this->arrayToXml($value, $subnode);
                } else {
                    foreach ($value as $val) {
                        $xml->addChild($key, $val);
                    }
                }
            } else {
                $xml->addChild($key, $value);
            }
        }
    }


    public function testQgisVersion()
    {
        $xml = new \SimpleXMLElement('<qgis version="1.2.3"></qgis>');
        $reader = new QgisXmlReader();
        $reader->XML($xml->asXML());
        $reader->parse();
        $reader->close();

        $this->assertEquals('010203', $reader->getQgisVersion());
    }
    public function testQgisProperties()
    {

        $xmlContent = array(
            'mapcanvas' => array(
                'destinationsrs' => array('spatialrefsys' => array('authid' => 'CRS4242')),
            ),
            'properties' => array(
                'WMSServiceTitle' => 'title',
                'WMSServiceAbstract' => 'abstract',
                'WMSKeywordList' => array(
                    'value' => array('key', 'word', 'WMS'),
                ),
                'WMSExtent' => array(
                    'value' => array('42', '24', '21', '12'),
                ),
                'WMSOnlineResource' => 'ressource',
                'WMSContactMail' => 'test.mail@3liz.org',
                'WMSContactOrganization' => '3liz',
                'WMSContactPerson' => 'marvin',
                'WMSContactPhone' => '',
            ),
        );
        $expectedWMS = array(
            'WMSServiceTitle' => 'title',
            'WMSServiceAbstract' => 'abstract',
            'WMSKeywordList' => 'key, word, WMS',
            'WMSExtent' => '42, 24, 21, 12',
            'ProjectCrs' => 'CRS4242',
            'WMSOnlineResource' => 'ressource',
            'WMSContactMail' => 'test.mail@3liz.org',
            'WMSContactOrganization' => '3liz',
            'WMSContactPerson' => 'marvin',
            'WMSContactPhone' => '',
            'title' => 'title',
            'abstract' => 'abstract',
            'keywordList' => 'key, word, WMS',

        );
        $reader = $this->getReader($xmlContent);
        $reader->parse();
        $reader->close();

        $data = $reader->getData();
        $this->assertEquals($expectedWMS, $data);
    }
}
