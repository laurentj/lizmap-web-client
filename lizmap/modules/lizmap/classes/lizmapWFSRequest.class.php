<?php
/**
 * Manage OGC request.
 *
 * @author    3liz
 * @copyright 2015 3liz
 *
 * @see      http://3liz.com
 *
 * @license Mozilla Public License : http://www.mozilla.org/MPL/
 */

use Lizmap\App;

class lizmapWFSRequest extends lizmapOGCRequest
{
    protected $tplExceptions = 'lizmap~wfs_exception';

    /**
     * @var null|string the requested typename
     */
    protected $wfs_typename;

    /**
     * @var qgisMapLayer|qgisVectorLayer
     */
    protected $qgisLayer;

    /**
     * @var object datasource parameters
     */
    protected $datasource;

    protected $selectFields = array();

    protected $blockSqlWords = array(
        ';',
        'select',
        'delete',
        'insert',
        'update',
        'drop',
        'alter',
        '--',
        'truncate',
        'vacuum',
        'create',
    );

    public function parameters()
    {
        $params = parent::parameters();

        // Filter data by login if necessary
        // as configured in the plugin for login filtered layers.

        // Filter data by login for request: getfeature
        if ($this->param('request') !== 'getfeature') {
            return $params;
        }

        // No filter data by login rights
        if (jAcl2::check('lizmap.tools.loginFilteredLayers.override', $this->repository->getKey())) {
            return $params;
        }

        // filter data by login
        $typenames = $this->requestedTypename();
        if (is_string($typenames) && $typenames !== '') {
            $typenames = explode(',', $typenames);
        }

        // get login filters
        $loginFilters = array();

        if (is_array($typenames)) {
            $loginFilters = $this->project->getLoginFilters($typenames);
        }

        // login filters array is empty
        if (empty($loginFilters)) {
            return $params;
        }

        $expFilters = array();

        // Get client exp_filter parameter
        $clientExpFilter = $this->param('exp_filter');
        if ($clientExpFilter != null && !empty($clientExpFilter)) {
            $expFilters[] = $clientExpFilter;
        }

        // Merge login filter
        $attribute = '';
        foreach ($loginFilters as $typename => $lfilter) {
            $expFilters[] = $lfilter['filter'];
            $attribute = $lfilter['filterAttribute'];
        }

        // Update exp_filter parameter
        $params['exp_filter'] = implode(' AND ', $expFilters);

        // Update propertyname parameter
        $propertyName = $this->param('propertyname');
        if ($propertyName != null && !empty($propertyName)) {
            $propertyName = trim($propertyName).",{$attribute}";
            $params['propertyname'] = $propertyName;
        }

        return $params;
    }

    /**
     * Get the requested typename based on TYPENAME or FEATUREID parameter.
     *
     * @return string the requested typename
     */
    public function requestedTypename()
    {
        if (!is_string($this->wfs_typename)) {
            $typename = $this->param('typename', '');
            if (!$typename) {
                $featureid = $this->param('featureid', '');
                if ($featureid) {
                    $featureIds = explode(',', $featureid);
                    $typenames = array();
                    foreach ($featureIds as $fid) {
                        $exp_fid = explode('.', $fid);
                        if (count($exp_fid) == 2) {
                            $typenames[] = trim($exp_fid[0]);
                        }
                    }
                    $typenames = array_unique($typenames);
                    $typename = implode(',', $typenames);
                }
            }
            $this->wfs_typename = $typename;
        }

        return $this->wfs_typename;
    }

    /**
     * @see https://en.wikipedia.org/wiki/Web_Feature_Service#Static_Interfaces.
     */
    protected function process_getcapabilities()
    {
        $version = $this->param('version');
        // force version if not defined
        if (!$version) {
            $this->params['version'] = '1.0.0';
        }

        $result = parent::process_getcapabilities();

        $data = $result->data;
        if (empty($data) or floor($result->code / 100) >= 4) {
            if (empty($data)) {
                jLog::log('GetCapabilities empty data', 'error');
            } else {
                jLog::log('GetCapabilities result code: '.$result->code, 'error');
            }
            jMessage::add('Server Error !', 'Error');

            return $this->serviceException();
        }

        if (preg_match('#ServiceExceptionReport#i', $data)) {
            return $result;
        }

        // Replace qgis server url in the XML (hide real location)
        $sUrl = jUrl::getFull(
            'lizmap~service:index',
            array('repository' => $this->repository->getKey(), 'project' => $this->project->getKey())
        );
        $sUrl = str_replace('&', '&amp;', $sUrl).'&amp;';
        preg_match('/<get>.*\n*.+xlink\:href="([^"]+)"/i', $data, $matches);
        if (count($matches) < 2) {
            preg_match('/get onlineresource="([^"]+)"/i', $data, $matches);
        }
        if (count($matches) < 2) {
            preg_match('/ows:get.+xlink\:href="([^"]+)"/i', $data, $matches);
        }
        if (count($matches) > 1) {
            $data = str_replace($matches[1], $sUrl, $data);
        }
        $data = str_replace('&amp;&amp;', '&amp;', $data);

        return (object) array(
            'code' => 200,
            'mime' => $result->mime,
            'data' => $data,
            'cached' => false,
        );
    }

    /**
     * @see https://en.wikipedia.org/wiki/Web_Feature_Service#Static_Interfaces.
     */
    protected function process_describefeaturetype()
    {
        // Extensions to get aliases and type
        $returnJson = (strtolower($this->param('outputformat', '')) == 'json');
        if ($returnJson) {
            $this->params['outputformat'] = 'XMLSCHEMA';
        }

        // Get remote data
        $response = $this->request();
        $code = $response->code;
        $mime = $response->mime;
        $data = $response->data;

        if ($code < 400 && $returnJson) {
            $jsonData = array();

            $wfs_typename = $this->requestedTypename();
            $layer = $this->project->findLayerByAnyName($wfs_typename);
            if ($layer != null) {

                // Get data from XML
                $go = true;
                // Create a DOM instance
                $xml = App\XmlTools::xmlFromString($data);
                if (!is_object($xml)) {
                    $errormsg = '\n'.$data.'\n'.$xml;
                    $errormsg = '\n'.http_build_query($this->params).$errormsg;
                    $errormsg = 'An error has been raised when loading DescribeFeatureType:'.$errormsg;
                    jLog::log($errormsg, 'error');
                    $go = false;
                }
                if ($go && $xml->complexType) {
                    $typename = (string) $xml->complexType->attributes()->name;
                    if ($typename == $wfs_typename.'Type') {
                        $jsonData['name'] = $layer->name;
                        $types = array();
                        $elements = $xml->complexType->complexContent->extension->sequence->element;
                        foreach ($elements as $element) {
                            $types[(string) $element->attributes()->name] = (string) $element->attributes()->type;
                        }
                        $jsonData['types'] = (object) $types;
                    }
                }
                $layer = $this->project->getLayer($layer->id);
                $jsonData['aliases'] = (object) $layer->getAliasFields();
                $jsonData['defaults'] = (object) $layer->getDefaultValues();
            }
            $data = json_encode((object) $jsonData);
            $mime = 'text/json; charset=utf-8';
        }

        return (object) array(
            'code' => $code,
            'mime' => $mime,
            'data' => $data,
            'cached' => false,
        );
    }

    /**
     * @see https://en.wikipedia.org/wiki/Web_Feature_Service#Static_Interfaces.
     */
    protected function process_getfeature()
    {
        if ($this->requestXml !== null) {
            return $this->getfeatureQgis();
        }

        // Get type name
        $typename = $this->requestedTypename();
        if (!$typename) {
            jMessage::add('TYPENAME or FEATUREID is mandatory', 'RequestNotWellFormed');

            return $this->serviceException();
        }

        // add outputformat if not provided
        $output = $this->param('outputformat');
        if (!$output) {
            $this->params['outputformat'] = 'GML2';
        }

        // Get Lizmap layer config
        $lizmapLayer = $this->project->findLayerByTypeName($typename);
        if (!$lizmapLayer) {
            jMessage::add('The layer '.$typename.' does not exists !', 'Error');

            return $this->serviceException();
        }
        $qgisLayer = $this->project->getLayer($lizmapLayer->id);
        $this->qgisLayer = $qgisLayer;

        // Get provider
        $provider = $qgisLayer->getProvider();

        if ($provider == 'postgres') {
            $dtparams = $qgisLayer->getDatasourceParameters();
            // Add key if not present ( WFS need to export id = typename.id for each feature)
            // To be sure to get the primary keys even if there is an issue in QGIS Server
            $propertyname = $this->param('propertyname', '');
            if (!empty($propertyname)) {
                $pfields = explode(',', $propertyname);
                $key = $dtparams->key;
                $keys = explode(',', $key);
                foreach ($keys as $k) {
                    if (!in_array($k, $pfields)) {
                        // prepend primary keys
                        array_unshift($pfields, $k);
                    }
                }
                $this->params['propertyname'] = implode(',', $pfields);
            }
        }

        // Get OGC filter
        $filter = $this->param('filter', '');

        // Use direct SQL query to improve performance for PostgreSQL layer
        // but only of not OGC filter is passed (complex to implement)
        // and only for GeoJSON (specific to Lizmap)
        // and only if it is not a complex query like table="(SELECT ...)"
        if ($provider == 'postgres'
            and empty($filter)
            and strtolower($output) == 'geojson'
            and $dtparams->table[0] != '('
        ) {
            return $this->getfeaturePostgres();
        }

        return $this->getfeatureQgis();
    }

    /**
     * Queries Qgis Server for getFeature.
     *
     * @see https://en.wikipedia.org/wiki/Web_Feature_Service#Static_Interfaces
     */
    protected function getfeatureQgis()
    {

        // Else pass query to QGIS Server
        // Get remote data
        $response = $this->request(true);
        $code = $response->code;
        $mime = $response->mime;
        $data = $response->data;

        if ($mime == 'text/plain' && strtolower($this->param('outputformat')) == 'geojson') {
            $mime = 'text/json';
            $layer = $this->project->findLayerByAnyName($this->requestedTypename());
            if ($layer != null) {
                $layer = $this->project->getLayer($layer->id);
                $aliases = $layer->getAliasFields();
                $layer = json_decode($data);
                $layer->aliases = (object) $aliases;
                $data = json_encode($layer);
            }
        }

        return (object) array(
            'code' => $code,
            'mime' => $mime,
            'data' => $data,
            'cached' => false,
        );
    }

    public function getfeaturePostgres()
    {
        $params = $this->parameters();

        // Get database connexion for this layer
        $cnx = $this->qgisLayer->getDatasourceConnection();
        // Get datasource
        $this->datasource = $this->qgisLayer->getDatasourceParameters();

        // Get fields
        $wfsFields = $this->qgisLayer->getWfsFields();

        // Get Db fields
        try {
            $dbFields = $this->qgisLayer->getDbFieldList();
        } catch (Exception $e) {
            return $this->getfeatureQgis();
        }

        // Verifying that every wfs fields are db fields
        // if not return getfeatureQgis
        foreach ($wfsFields as $field) {
            if (!array_key_exists($field, $dbFields)) {
                return $this->getfeatureQgis();
            }
        }

        // Build SQL
        // SELECT
        $sql = ' SELECT ';
        $propertyname = '';
        if (array_key_exists('propertyname', $params)) {
            $propertyname = $params['propertyname'];
        }
        if (!empty($propertyname)) {
            $sfields = array();
            $pfields = explode(',', $propertyname);
            foreach ($pfields as $pfield) {
                if (in_array($pfield, $wfsFields)) {
                    $sfields[] = $pfield;
                }
            }
        } else {
            $sfields = $wfsFields;
        }
        // Add key if not present ( WFS need to export id = typename.id for each feature)
        $key = $this->datasource->key;
        $keys = explode(',', $key);
        foreach ($keys as $k) {
            if (!in_array($k, $sfields)) {
                $sfields[] = $k;
            }
        }
        // deduplicate columns to avoid SQL errors
        $sfields = array_values(array_unique($sfields));

        $this->selectFields = array_map(function ($item) use ($cnx) {
            return $cnx->encloseName($item);
        }, $sfields);

        $sql .= implode(', ', $this->selectFields);

        // Get spatial field
        $geometryname = '';
        if (array_key_exists('geometryname', $params)) {
            $geometryname = strtolower($params['geometryname']);
        }
        $geocol = $this->datasource->geocol;
        if (!empty($geocol) && $geometryname !== 'none') {
            $geocolalias = 'geosource';
            $sql .= ', '.$cnx->encloseName($geocol).' AS '.$cnx->encloseName($geocolalias);
        }

        // FROM
        $sql .= ' FROM '.$this->datasource->table;

        // WHERE
        $sql .= ' WHERE True';

        $dtsql = trim($this->datasource->sql);
        if (!empty($dtsql)) {
            $sql .= ' AND '.$dtsql;
        }

        // BBOX
        if (!empty($this->datasource->geocol)) {
            $bbox = '';
            if (array_key_exists('bbox', $params)) {
                $bbox = $params['bbox'];
            }
            $bboxvalid = false;
            if (!empty($bbox)) {
                $bboxvalid = true;
                $bboxitem = explode(',', $bbox);
                if (count($bboxitem) == 4) {
                    foreach ($bboxitem as $coord) {
                        if (!is_numeric(trim($coord))) {
                            $bboxvalid = false;
                        }
                    }
                }
            }
            if ($bboxvalid) {
                $xmin = trim($bboxitem[0]);
                $ymin = trim($bboxitem[1]);
                $xmax = trim($bboxitem[2]);
                $ymax = trim($bboxitem[3]);
                $sql .= ' AND ST_Intersects("';
                $sql .= $this->datasource->geocol;
                $sql .= '", ST_MakeEnvelope('.$xmin.','.$ymin.','.$xmax.','.$ymax.', '.$this->qgisLayer->getSrid().'))';
            }
        }

        // EXP_FILTER
        $exp_filter = '';
        if (array_key_exists('exp_filter', $params)) {
            $exp_filter = $params['exp_filter'];
        }
        if (!empty($exp_filter)) {
            $validFilter = $this->validateFilter($exp_filter);
            if (!$validFilter) {
                return $this->getfeatureQgis();
            }
            if (strpos($validFilter, '$id') !== false) {
                $key = $this->datasource->key;
                if (count(explode(',', $key)) == 1) {
                    $sql .= ' AND '.str_replace('$id ', $cnx->encloseName($key).' ', $validFilter);
                } else {
                    return $this->getfeatureQgis();
                }
            } else {
                $sql .= ' AND '.$validFilter;
            }
        }

        // FEATUREID
        $featureid = '';
        $typename = $params['typename'];
        if (array_key_exists('featureid', $params)) {
            $featureid = $params['featureid'];
        }
        if (!empty($featureid)) {
            $fids = explode(',', $featureid);
            $fidsSql = array();
            foreach ($fids as $fid) {
                $exp = explode('.', $fid);
                if (count($exp) == 2 and $exp[0] == $typename) {
                    $fidSql = '(';
                    $pks = explode('@@', $exp[1]);
                    $i = 0;
                    $v = '';
                    foreach ($keys as $key) {
                        $fidSql .= $v.$cnx->encloseName($key).' = ';
                        if (ctype_digit($pks[$i])) {
                            $fidSql .= $pks[$i];
                        } else {
                            $fidSql .= $cnx->quote($pks[$i]);
                        }
                        $v = ' AND ';
                        ++$i;
                    }
                    $fidSql .= ')';
                    $fidsSql[] = $fidSql;
                }
            }
            //jLog::log(implode(' OR ', $fidsSql), 'error');
            $sql .= ' AND '.implode(' OR ', $fidsSql);
        }

        // ORDER BY
        // séparé par virgule, et fini par espace + a ou d
        // si pas de a ou d , c'est a
        // SortBY=id a,name d
        $sortby = '';
        if (array_key_exists('sortby', $params)) {
            $sortby = $params['sortby'];
        }
        if (!empty($sortby)) {
            $sort_items = array();
            $items = explode(',', $sortby);
            foreach ($items as $item) {
                // !!! Validate on fields to avoid injection !!!
                $exp = explode(' ', $item);
                $field = $exp[0];
                if (in_array($field, $wfsFields)) {
                    $sort_items[$field] = 'ASC';
                    if (count($exp) == 2 and $exp[1] == 'd') {
                        $sort_items[$field] = 'DESC';
                    }
                }
            }
            if (count($sort_items) > 0) {
                $sql .= ' ORDER BY ';
                $sep = '';
                foreach ($sort_items as $f => $o) {
                    $sql .= $sep.$cnx->encloseName($f).' '.$o;
                    $sep = ', ';
                }
            }
        }

        // LIMIT
        $maxfeatures = '';
        if (array_key_exists('maxfeatures', $params)) {
            $maxfeatures = $params['maxfeatures'];
        }
        $maxfeatures = filter_var($maxfeatures, FILTER_VALIDATE_INT);
        // !!! validate integer to avoid injection !!!
        if (is_int($maxfeatures)) {
            $sql .= ' LIMIT '.$maxfeatures;
        }

        // OFFSET
        // !!! validate integer to avoid injection !!!
        $startindex = '';
        if (array_key_exists('startindex', $params)) {
            $startindex = $params['startindex'];
        }
        $startindex = filter_var($startindex, FILTER_VALIDATE_INT);
        if (is_int($startindex)) {
            $sql .= ' OFFSET '.$startindex;
        }

        //jLog::log($sql);
        // Use PostgreSQL method to export geojson
        $sql = $this->setGeojsonSql($sql, $cnx, $typename, $geometryname);
        //jLog::log($sql);
        // Run query
        try {
            $q = $cnx->query($sql);
        } catch (Exception $e) {
            jLog::logEx($e, 'error');

            return $this->getfeatureQgis();
        }

        // To avoid memory issues, we do not ask PostgreSQL for a unique big line containing the geojson
        // but asked for a feature in JSON per line
        // the we store the data into a file
        $path = tempnam(sys_get_temp_dir(), 'wfs_'.session_id().'_');
        $fd = fopen($path, 'w');
        fwrite($fd, '
{
  "type": "FeatureCollection",
  "features": [
');
        $virg = '';
        foreach ($q as $d) {
            fwrite($fd, $virg.$d->geojson);
            $virg = ',
';
        }
        fwrite($fd, '
]}
');
        fclose($fd);

        // Return response
        return (object) array(
            'code' => '200',
            'mime' => 'application/vnd.geo+json; charset=utf-8',
            'file' => true, // we use this to inform controler postgres has been used
            'data' => $path,
            'cached' => false,
        );
    }

    /**
     * Parses and validate a filter for postgresql.
     *
     * @param string $filter The filter to parse
     *
     * @return false|string returns the validate filter if the expression does not contains dangerous chars
     */
    protected function validateFilter($filter)
    {
        $block_items = array();
        if (preg_match('#'.implode('|', $this->blockSqlWords).'#i', $filter, $block_items)) {
            jLog::log('The EXP_FILTER param contains dangerous chars : '.implode(', ', $block_items));

            return false;
        }
        $filter = str_replace('intersects', 'ST_Intersects', $filter);
        $filter = str_replace('geom_from_gml', 'ST_GeomFromGML', $filter);

        return str_replace('$geometry', '"'.$this->datasource->geocol.'"', $filter);
    }

    /**
     * @param string        $sql
     * @param jDbConnection $cnx
     * @param mixed         $typename
     * @param mixed         $geometryname
     *
     * @return string
     */
    private function setGeojsonSql($sql, $cnx, $typename, $geometryname)
    {
        $sql = '
        WITH source AS (
        '.$sql.'
        )';

        $sql .= "
        --SELECT row_to_json(fc, True) AS geojson
        --FROM (
        --    SELECT
        --        'FeatureCollection' As type,
        --        array_to_json(array_agg(f)) As features
        SELECT row_to_json(f, True) AS geojson
            FROM (
                SELECT
                    'Feature' AS type,
        ";

        // feature id
        // The feature ID is very fragile
        // it needs to be an integer, and only one columnn, and must be unique
        // this means some Lizmap features won't work with multiple keys or string ids
        // for example when using a filter clause in this query, row_number() will be false
        $sql .= ' Concat(
            '.$cnx->quote($typename).",
            '.',
            ";
        $key = $this->datasource->key;

        if (count(explode(',', $key)) == 1) {
            $sql .= $cnx->encloseName($key);
        } else {
            $sql .= ' row_number() OVER() ';
        }
        $sql .= ') AS id,';

        // Get geometryname param
        $geosql = '';
        if (!empty($this->datasource->geocol)) {
            // use PostGIS functions to change geometry based on geometryname
            if ($geometryname === 'extent') {
                $geosql = 'ST_Envelope(lg.geosource::geometry)';
            } elseif ($geometryname === 'centroid') {
                $geosql = 'ST_Centroid(lg.geosource::geometry)';
            } elseif ($geometryname === 'none') {
                $geosql = null;
            } else {
                $geosql = 'lg.geosource::geometry';
            }
        }

        if (!empty($geosql)) {
            // Define BBOX SQL
            $bboxsql = 'ST_Envelope(lg.geosource::geometry)';
            // For new QGIS versions, export into EPSG:4326
            $lizservices = lizmap::getServices();
            if (version_compare($lizservices->qgisServerVersion, '2.18', '>=')) {
                $geosql = 'ST_Transform('.$geosql.', 4326)';
                $bboxsql = 'ST_Transform('.$bboxsql.', 4326)';
            }

            // Transform BBOX into JSON
            $sql .= '
                        array_to_json(ARRAY[
                            ST_XMin('.$bboxsql.'),
                            ST_YMin('.$bboxsql.'),
                            ST_XMax('.$bboxsql.'),
                            ST_YMax('.$bboxsql.')
                        ]) As bbox,
            ';

            // Transform into GeoJSON
            $sql .= '
                        ST_AsGeoJSON('.$geosql.')::json As geometry,
            ';
        } else {
            $sql .= '
                        Null As geometry,
            ';
        }

        // bbox
        //$sql.= "
        //trim(regexp_replace( Box2D(" . $geosql . ")::text, 'BOX', ''), '()') AS bbox,
        //";

        $sql .= '
                    row_to_json(
                        ( SELECT l FROM
                            (
                                SELECT ';

        $sql .= implode(', ', $this->selectFields);
        $sql .= '
                            ) As l
                        )
                    ) As properties
                FROM source As lg
            ) As f
        --) As fc';

        return $sql;
    }
}
