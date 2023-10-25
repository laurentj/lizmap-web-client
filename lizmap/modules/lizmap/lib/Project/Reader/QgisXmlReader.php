<?php
/**
 * Read a qgis project.
 *
 * @author    3liz
 * @copyright 2023 3liz
 *
 * @see      http://3liz.com
 *
 * @license Mozilla Public License : http://www.mozilla.org/MPL/
 */

namespace Lizmap\Project\Reader;

class QgisXmlReader extends \SimpleXMLReader
{
    /**
     * @var array QGIS project data
     */
    protected $data = array();

    public function __construct()
    {
        // parent::__construct();

        $this->registerCallback('/qgis', array($this, 'readQgisElement'));
        $this->registerCallback('/qgis/properties/WMSServiceTitle', array($this, 'readWMSProperty'));
        $this->registerCallback('/qgis/properties/WMSServiceAbstract', array($this, 'readWMSProperty'));
        $this->registerCallback('/qgis/properties/WMSKeywordList', array($this, 'readWMSProperty'));
        $this->registerCallback('/qgis/properties/WMSMaxWidth', array($this, 'readWMSProperty'));
        $this->registerCallback('/qgis/properties/WMSMaxHeight', array($this, 'readWMSProperty'));
        $this->registerCallback('/qgis/properties/WMSExtent', array($this, 'readWMSProperty'));
        $this->registerCallback('/qgis/properties/WMSOnlineResource', array($this, 'readQgisString'));
        $this->registerCallback('/qgis/properties/WMSContactMail', array($this, 'readQgisString'));
        $this->registerCallback('/qgis/properties/WMSContactOrganization', array($this, 'readQgisString'));
        $this->registerCallback('/qgis/properties/WMSContactPerson', array($this, 'readQgisString'));
        $this->registerCallback('/qgis/properties/WMSContactPhone', array($this, 'readQgisString'));
        $this->registerSaveStringIntoData('/qgis/mapcanvas/destinationsrs/spatialrefsys/authid', 'ProjectCrs');
    }

    /**
     * @param string $xpath
     * @param string $dataKey
     *
     * @throws \Exception
     */
    public function registerSaveStringIntoData($xpath, $dataKey)
    {
        $reader = $this;
        $this->registerCallback($xpath, function () use ($reader, $dataKey) {
            $reader->data[$dataKey] = $reader->readString();

            return true;
        });
    }

    public function getQgisVersion()
    {
        return $this->data['version'];
    }

    public function getData()
    {
        return $this->data;
    }

    /**
     * @param QgisXmlReader $reader
     *
     * @return bool
     */
    protected function readQgisElement($reader)
    {
        $qgisProjectVersion = $reader->getAttribute('version');
        if ($qgisProjectVersion == '') {
            return true;
        }
        $qgisProjectVersion = explode('-', $qgisProjectVersion);
        $qgisProjectVersion = $qgisProjectVersion[0];
        $qgisProjectVersion = explode('.', $qgisProjectVersion);
        $a = '';
        foreach ($qgisProjectVersion as $k) {
            if (strlen($k) == 1) {
                $a .= '0'.$k;
            } else {
                $a .= $k;
            }
        }
        $this->data['version'] = $a;

        return true;
    }

    protected function parseValueList($reader)
    {
        $node = new \SimpleXMLElement($reader->readOuterXML());

        // For QStringList
        foreach ($node->value as $value) {
            if ((string) $value !== '') {
                $values[] = (string) $value;
            }
        }

        return $values;
    }

    protected function readQgisString($reader)
    {
        $this->data[$reader->localName] = $reader->readString();

        return true;
    }

    protected function readWMSProperty($reader)
    {
        switch ($reader->localName) {
            case 'WMSServiceTitle':
                $value = $reader->readString();
                if ($value != '') {
                    $this->data['title'] = $value;
                }
                $this->data['WMSServiceTitle'] = $value;

                break;

            case 'WMSServiceAbstract':
                $this->data['abstract'] = $reader->readString();
                $this->data['WMSServiceAbstract'] = $reader->readString();

                break;

            case 'WMSKeywordList':
                $this->data['keywordList'] = implode(', ', $this->parseValueList($reader));
                $this->data['WMSKeywordList'] = $this->data['keywordList'];

                break;

            case 'WMSExtent':
                $this->data['WMSExtent'] = implode(', ', $this->parseValueList($reader));

                break;

            case 'WMSMaxWidth':
                $value = $reader->readString();
                if ($value != '') {
                    $this->data['wmsMaxWidth'] = (int) $value;
                }
                // FIXME why ?
                if (!array_key_exists('WMSMaxWidth', $this->data) or !$this->data['wmsMaxWidth']) {
                    unset($this->data['wmsMaxWidth']);
                }

                break;

            case 'WMSMaxHeight':
                $value = $reader->readString();
                if ($value != '') {
                    $this->data['wmsMaxHeight'] = (int) $value;
                }
                // FIXME why ?
                if (!array_key_exists('WMSMaxHeight', $this->data) or !$this->data['wmsMaxHeight']) {
                    unset($this->data['wmsMaxHeight']);
                }

                break;
        }

        return true;
    }
}
