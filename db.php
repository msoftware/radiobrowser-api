<?php

require 'utils.php';

$columnMapping = [
    'id' => 'StationID',
    'name' => 'Name',
    'url' => 'Url',
    'homepage' => 'Homepage',
    'favicon' => 'Favicon',
    'tags' => 'Tags',
    'country' => 'Country',
    'state' => 'Subcountry',
    'language' => 'Language',
    'votes' => 'Votes',
    'negativevotes' => 'NegativeVotes',
    'codec' => 'Codec',
    'bitrate' => 'Bitrate',
    'lastcheckok' => 'LastCheckOK',
    'lastchecktime' => 'LastCheckTime',
    'lastcheckoktime' => 'LastCheckOkTime',
    'clicktimestamp' => 'ClickTimestamp',
    'clickcount' => 'clickcount',
    'clicktrend' => 'ClickTrend',
    'lastchangetime' => 'Creation'
];

$columnMappingHistory = [
    'id' => 'StationID',
    'changeid' => 'StationChangeID',
    'name' => 'Name',
    'url' => 'Url',
    'homepage' => 'Homepage',
    'favicon' => 'Favicon',
    'tags' => 'Tags',
    'country' => 'Country',
    'state' => 'Subcountry',
    'language' => 'Language',
    'votes' => 'Votes',
    'negativevotes' => 'NegativeVotes',
    'lastchangetime' => 'Creation'
];

function openDB()
{
    $db = new PDO('mysql:host=localhost;dbname=radio', 'radiouser', '');
    // use exceptions for error handling
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // create needed tables if they do not exist
    if (!tableExists($db, 'Station')) {
        $db->query('CREATE TABLE Station(
          StationID INT NOT NULL AUTO_INCREMENT,
          Primary Key (StationID),
          Name TEXT,
          Url TEXT,
          UrlCache TEXT NOT NULL,
          Homepage TEXT,
          Favicon TEXT,
          Creation TIMESTAMP,
          Country VARCHAR(50),
          Subcountry VARCHAR(50),
          Language VARCHAR(50),
          Tags TEXT,
          Votes INT DEFAULT 0,
          NegativeVotes INT DEFAULT 0,
          Source VARCHAR(20),
          Codec VARCHAR(20),
          Bitrate INT DEFAULT 0 NOT NULL,
          clickcount INT DEFAULT 0,
          ClickTrend INT DEFAULT 0,
          ClickTimestamp DATETIME,
          LastCheckOK boolean default true NOT NULL,
          LastCheckOKTime DATETIME,
          LastCheckTime DATETIME)
          ');
    }
    if (!tableExists($db, 'StationHistory')) {
        $db->query('CREATE TABLE StationHistory(
          StationChangeID INT NOT NULL AUTO_INCREMENT,
          Primary Key (StationChangeID),
          StationID INT NOT NULL,
          Name TEXT,
          Url TEXT,
          Homepage TEXT,
          Favicon TEXT,
          Creation TIMESTAMP,
          Country VARCHAR(50),
          Subcountry VARCHAR(50),
          Language VARCHAR(50),
          Tags TEXT,
          Votes INT DEFAULT 0,
          NegativeVotes INT DEFAULT 0)
          ');
    }
    if (!tableExists($db, 'IPVoteCheck')) {
        $db->query('CREATE TABLE IPVoteCheck(CheckID INT NOT NULL AUTO_INCREMENT,
          Primary Key (CheckID),
          IP VARCHAR(15) NOT NULL,
          StationID INT NOT NULL,
          VoteTimestamp TIMESTAMP)
          ');
    }
    if (!tableExists($db, 'StationClick')) {
        $db->query('CREATE TABLE StationClick(ClickID INT NOT NULL AUTO_INCREMENT,
          Primary Key (ClickID),
          StationID INT,
          ClickTimestamp TIMESTAMP)
          ');
    }
    if (!tableExists($db, 'TagCache')) {
        $db->query('CREATE TABLE TagCache(
          TagName VARCHAR(100) COLLATE utf8_bin NOT NULL,
          Primary Key (TagName),
          StationCount INT DEFAULT 0,
          StationCountWorking INT DEFAULT 0) CHARSET=utf8 COLLATE=utf8_bin
          ');
    }

    return $db;
}

function tableExists($db, $tableName)
{
    if ($result = $db->query("SHOW TABLES LIKE '".$tableName."'")) {
        return $result->rowCount() > 0;
    }

    return false;
}

function print_object($row, $format, $columns, $itemname)
{
    print_output_item_start($format, $itemname);
    $j = 0;
    foreach ($columns as $outputName => $dbColumn) {
        if ($j > 0) {
            print_output_item_dict_sep($format);
        }
        if (isset($row[$dbColumn])) {
            print_output_item_content($format, $outputName, $row[$dbColumn]);
        }else{
            print_output_item_content($format, $outputName, '');
        }
        ++$j;
    }
    print_output_item_end($format);
}

function print_list($stmt, $format, $columns, $itemname)
{
    print_output_header($format);
    print_output_arr_start($format);
    $i = 0;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($i > 0) {
            print_output_item_arr_sep($format);
        }
        print_object($row, $format, $columns, $itemname);
        ++$i;
    }
    print_output_arr_end($format);
    print_output_footer($format);
}

/*
Print a result set with many stations in the given format
*/
function print_result_stations($stmt, $format)
{
    global $columnMapping;
    print_list($stmt, $format, $columnMapping, 'station');
}

/*
Print a result set with many stations in the given format
*/
function print_result_stations_history($stmt, $format)
{
    global $columnMappingHistory;
    print_list($stmt, $format, $columnMappingHistory, 'station');
}

function print_tags($db, $format, $search_term, $order, $reverse, $hideBroken)
{
    $stationCountColumn = strtolower($hideBroken) === "true" ? "StationCountWorking" : "StationCount";
    $reverseDb = filterOrderReverse($reverse);
    if ($order === "stationcount"){
        $orderDb = $stationCountColumn;
        $orderDb2 = "TagName";
    }else{
        $orderDb = "TagName";
        $orderDb2 = $stationCountColumn;
    }
    $stmt = $db->prepare('SELECT TagName,'.$stationCountColumn.' FROM TagCache WHERE TagName LIKE :search AND '.$stationCountColumn.'>0 ORDER BY '.$orderDb.' '.$reverseDb.', '.$orderDb2.' ASC');
    $result = $stmt->execute(['search' => '%'.$search_term.'%']);
    if ($result) {
        print_list($stmt, $format, ['name' => 'TagName', 'value' => 'TagName', 'stationcount' => $stationCountColumn], 'tag');
    }
}

function print_1_n($db, $format, $column, $outputItemName, $search_term, $order, $reverse, $hideBroken)
{
    $hideBrokenDb = $hideBroken === true ? " AND LastCheckOK=TRUE" : "";
    $reverseDb = filterOrderReverse($reverse);
    if ($order === "stationcount"){
        $orderDb = "StationCount";
        $orderDb2 = $column;
    }else{
        $orderDb = $column;
        $orderDb2 = "StationCount";
    }
    $stmt = $db->prepare('SELECT '.$column.', COUNT(*) AS StationCount FROM Station WHERE '.$column.' LIKE :search AND '.$column.'<>"" '.$hideBrokenDb.' GROUP BY '.$column.' ORDER BY '.$orderDb.' '.$reverseDb.', '.$orderDb2.' ASC');
    $result = $stmt->execute(['search' => '%'.$search_term.'%']);
    if ($result) {
        print_list($stmt, $format, ['name' => $column, 'value' => $column, 'stationcount' => 'StationCount'], $outputItemName);
    }
}

function print_states($db, $format, $search_term, $country, $order, $reverse, $hideBroken)
{
    $hideBrokenDb = $hideBroken === true ? " AND LastCheckOK=TRUE" : "";
    $reverseDb = filterOrderReverse($reverse);
    if ($order === "stationcount"){
        $orderDb = "StationCount";
        $orderDb2 = "SubCountry";
    }else{
        $orderDb = "SubCountry";
        $orderDb2 = "StationCount";
    }
    if ($country !== '' && $country !== null) {
        $stmt = $db->prepare('SELECT Country, Subcountry, COUNT(*) AS StationCount FROM Station WHERE Country=:country AND Subcountry LIKE :search AND Country<>"" AND Subcountry<>"" '.$hideBrokenDb.' GROUP BY Country, Subcountry ORDER BY '.$orderDb.' '.$reverseDb.', '.$orderDb2.' ASC');
        $result = $stmt->execute(['search' => '%'.$search_term.'%', 'country' => $country]);
    } else {
        $stmt = $db->prepare('SELECT Country, Subcountry, COUNT(*) AS StationCount FROM Station WHERE Subcountry LIKE :search AND Country<>"" AND Subcountry<>"" '.$hideBrokenDb.' GROUP BY Country, Subcountry ORDER BY '.$orderDb.' '.$reverseDb.', '.$orderDb2.' ASC');
        $result = $stmt->execute(['search' => '%'.$search_term.'%']);
    }

    if ($result) {
        print_list($stmt, $format, ['name' => 'Subcountry', 'value' => 'Subcountry', 'country' => 'Country', 'stationcount' => 'StationCount'], 'state');
    }
}

function get_station_count($db)
{
    $result = $db->query('SELECT COUNT(*) FROM Station WHERE Source is NULL');
    if ($result) {
        return $result->fetchColumn(0);
    }

    return 0;
}

function get_station_broken_count($db)
{
    $result = $db->query('SELECT COUNT(*) FROM Station WHERE Source is NULL AND LastCheckOK=FALSE');
    if ($result) {
        return $result->fetchColumn(0);
    }

    return 0;
}

function get_tag_count($db)
{
    $result = $db->query('SELECT COUNT(*) FROM TagCache');
    if ($result) {
        return $result->fetchColumn(0);
    }

    return 0;
}

function get_click_count_hours($db, $hours)
{
    $stmt = $db->prepare('SELECT COUNT(*) FROM StationClick WHERE TIMEDIFF(NOW(),ClickTimestamp)<MAKETIME(:hours,0,0)');
    $result = $stmt->execute(['hours' => $hours]);
    if ($result) {
        return $stmt->fetchColumn(0);
    }

    return 0;
}

function get_languages_count($db)
{
    $result = $db->query('SELECT COUNT(DISTINCT Language) FROM Station');
    if ($result) {
        return $result->fetchColumn(0);
    }

    return 0;
}

function get_countries_count($db)
{
    $result = $db->query('SELECT COUNT(DISTINCT Country) FROM Station');
    if ($result) {
        return $result->fetchColumn(0);
    }

    return 0;
}

function print_stats($db, $format)
{
    print_output_header($format);
    print_output_item_start($format, 'stats');
    print_output_item_content($format, 'stations', get_station_count($db));
    print_output_item_dict_sep($format);
    print_output_item_content($format, 'stations_broken', get_station_broken_count($db));
    print_output_item_dict_sep($format);
    print_output_item_content($format, 'tags', get_tag_count($db));
    print_output_item_dict_sep($format);
    print_output_item_content($format, 'clicks_last_hour', get_click_count_hours($db, 1));
    print_output_item_dict_sep($format);
    print_output_item_content($format, 'clicks_last_day', get_click_count_hours($db, 24));
    print_output_item_dict_sep($format);
    print_output_item_content($format, 'languages', get_languages_count($db));
    print_output_item_dict_sep($format);
    print_output_item_content($format, 'countries', get_countries_count($db));
    print_output_item_end($format);
    print_output_footer($format);
}

function filterOrderColumnName($columnName){
    global $columnMapping;

    foreach ($columnMapping as $apiName => $dbColumnName) {
        if ($apiName === $columnName){
            return $dbColumnName;
        }
    }
    return "Name";
}

function filterOrderReverse($reverse){
    if (strtolower($reverse) === "true"){
        return "DESC";
    }
    return "ASC";
}

function print_stations_list_data_advanced($db, $format, $name, $nameExact, $country, $countryExact, $state, $stateExact, $language, $languageExact, $tag, $tagExact, $bitrateMin, $bitrateMax, $order, $reverse, $hideBroken, $offset, $limit)
{
    $orderDb = filterOrderColumnName($order);
    $reverseDb = filterOrderReverse($reverse);
    $hideBrokenDb = $hideBroken === true ? " AND LastCheckOK=TRUE" : "";
    $where = '';
    $bindingValues = array();
    if ($name !== null) {
        if ($nameExact === true){
            $where .= ' AND Name=:name';
            $bindingValues[':name'] = $name;
        }else{
          $where .= ' AND Name LIKE :name';
          $bindingValues[':name'] = '%'.$name.'%';
        }
    }

    if ($country !== null) {
        if ($countryExact === true){
            $where .= ' AND Country=:country';
            $bindingValues[':country'] = $country;
        }else{
            $where .= ' AND Country LIKE :country';
            $bindingValues[':country'] = '%'.$country.'%';
        }
    }

    if ($state !== null) {
        if ($stateExact === true){
            $where .= ' AND SubCountry=:state';
            $bindingValues[':state'] = $state;
        }else{
            $where .= ' AND SubCountry LIKE :state';
            $bindingValues[':state'] = '%'.$state.'%';
        }
    }

    if ($language !== null) {
        if ($languageExact === true){
            $where .= ' AND Language=:language';
            $bindingValues[':language'] = $language;
        }else{
            $where .= ' AND Language LIKE :language';
            $bindingValues[':language'] = '%'.$language.'%';
        }
    }

    if ($tag !== null) {
        if ($tagExact === true) {
            $where .= ' AND (Tags=:tagSingle OR Tags LIKE :tagRight OR Tags LIKE :tagLeft OR Tags LIKE :tagMiddle)';
            $bindingValues[':tagSingle'] = $tag;
            $bindingValues[':tagLeft'] = '%,'.$tag;
            $bindingValues[':tagRight'] = $tag.',%';
            $bindingValues[':tagMiddle'] = '%,'.$tag.',%';
        }else{
            $where .= ' AND Tags LIKE :tag';
            $bindingValues[':tag'] = '%'.$tag.'%';
        }
    }


    $stmt = $db->prepare('SELECT * FROM Station WHERE Source IS NULL '.$hideBrokenDb.' '.$where.' AND Bitrate>=:bitratemin AND Bitrate<=:bitratemax ORDER BY '.$orderDb.' '.$reverseDb.' LIMIT :limit OFFSET :offset');
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':bitratemin', $bitrateMin, PDO::PARAM_INT);
    $stmt->bindValue(':bitratemax', $bitrateMax, PDO::PARAM_INT);
    foreach ($bindingValues as $bindName => $bindValue){
        $stmt->bindValue($bindName, $bindValue, PDO::PARAM_STR);
    }

    $result = $stmt->execute();
    if ($result) {
        print_result_stations($stmt, $format);
    }else{
        sendResult($format, false, "Error in query");
    }
}

function print_stations_list_data_all($db, $format, $order, $reverse, $offset, $limit)
{
    $orderDb = filterOrderColumnName($order);
    $reverseDb = filterOrderReverse($reverse);
    $stmt = $db->prepare('SELECT * FROM Station WHERE Source IS NULL ORDER BY '.$orderDb.' '.$reverseDb.' LIMIT :limit OFFSET :offset');
    $stmt->bindValue(':limit', intval($limit), PDO::PARAM_INT);
    $stmt->bindValue(':offset', intval($offset), PDO::PARAM_INT);
    $result = $stmt->execute();
    if ($result) {
        print_result_stations($stmt, $format);
    }
}

function print_stations_list_data($db, $format, $column, $search_term, $order, $reverse, $offset, $limit)
{
    $orderDb = filterOrderColumnName($order);
    $reverseDb = filterOrderReverse($reverse);
    if ($search_term !== "" && $search_term !== null && $search_term !== false) {
        $stmt = $db->prepare('SELECT * FROM Station WHERE Source IS NULL AND '.$column.' LIKE :search ORDER BY '.$orderDb.' '.$reverseDb.' LIMIT :limit OFFSET :offset');
        $stmt->bindValue(':search', '%'.$search_term.'%', PDO::PARAM_STR);
    } else {
        $stmt = $db->prepare('SELECT * FROM Station WHERE Source IS NULL AND '.$column.'="" ORDER BY '.$orderDb.' '.$reverseDb.' LIMIT :limit OFFSET :offset');
    }
    $stmt->bindValue(':limit', intval($limit), PDO::PARAM_INT);
    $stmt->bindValue(':offset', intval($offset), PDO::PARAM_INT);
    $result = $stmt->execute();
    if ($result) {
        print_result_stations($stmt, $format);
    }
}

function print_stations_list_data_exact($db, $format, $column, $search_term, $multivalue, $order, $reverse, $offset, $limit)
{
    $orderDb = filterOrderColumnName($order);
    $reverseDb = filterOrderReverse($reverse);
    $result = false;
    if ($search_term !== '' && $search_term !== null && $search_term !== false) {
        if ($multivalue === true) {
            $stmt = $db->prepare('SELECT * FROM Station WHERE Source IS NULL AND ('.$column.'=:searchSingle OR '.$column.' LIKE :searchRight OR '.$column.' LIKE :searchLeft OR '.$column.' LIKE :searchMiddle) ORDER BY '.$orderDb.' '.$reverseDb.' LIMIT :limit OFFSET :offset');
            $stmt->bindValue(':limit', intval($limit), PDO::PARAM_INT);
            $stmt->bindValue(':offset', intval($offset), PDO::PARAM_INT);
            $stmt->bindValue(':searchSingle', $search_term, PDO::PARAM_STR);
            $stmt->bindValue(':searchLeft', '%,'.$search_term, PDO::PARAM_STR);
            $stmt->bindValue(':searchRight', $search_term.',%', PDO::PARAM_STR);
            $stmt->bindValue(':searchMiddle', '%,'.$search_term.',%', PDO::PARAM_STR);
            $result = $stmt->execute();
        } else {
            $stmt = $db->prepare('SELECT * FROM Station WHERE Source IS NULL AND '.$column.'=:search ORDER BY '.$orderDb.' '.$reverseDb.' LIMIT :limit OFFSET :offset');
            $stmt->bindValue(':limit', intval($limit), PDO::PARAM_INT);
            $stmt->bindValue(':offset', intval($offset), PDO::PARAM_INT);
            $stmt->bindValue(':search', $search_term, PDO::PARAM_STR);
            $result = $stmt->execute();
        }
    }else{
        $stmt = $db->prepare('SELECT * FROM Station WHERE Source IS NULL AND '.$column.'="" ORDER BY '.$orderDb.' '.$reverseDb.' LIMIT :limit OFFSET :offset');
        $stmt->bindValue(':limit', intval($limit), PDO::PARAM_INT);
        $stmt->bindValue(':offset', intval($offset), PDO::PARAM_INT);
        $result = $stmt->execute();
    }
    if ($result) {
        print_result_stations($stmt, $format);
    }
}

function print_stations_list_broken($db, $format, $limit)
{
    $result = false;
    $stmt = $db->prepare('SELECT * FROM Station WHERE Source IS NULL AND LastCheckOK=0 ORDER BY RAND() LIMIT :limit');
    $stmt->bindValue(':limit', intval($limit), PDO::PARAM_INT);
    $result = $stmt->execute();

    if ($result) {
        print_result_stations($stmt, $format);
    }
}

function print_stations_list_improvable($db, $format, $limit)
{
    $result = false;
    $stmt = $db->prepare('SELECT * FROM Station WHERE Source IS NULL AND LastCheckOK=1 AND (Tags="" OR Country="") ORDER BY RAND() LIMIT :limit');
    $stmt->bindValue(':limit', intval($limit), PDO::PARAM_INT);
    $result = $stmt->execute();

    if ($result) {
        print_result_stations($stmt, $format);
    }
}

function print_stations_list_deleted($db, $format, $stationid){
    $stmt = $db->prepare('SELECT sth.* FROM Station st RIGHT JOIN StationHistory sth ON st.StationID=sth.StationID WHERE st.StationID IS NULL AND sth.StationID=:id ORDER BY sth.Creation DESC');
    $stmt->bindValue(':id', $stationid, PDO::PARAM_INT);
    $result = $stmt->execute();

    if ($result) {
        print_result_stations_history($stmt, $format);
    }
}

function print_stations_list_deleted_all($db, $format){
    $stmt = $db->prepare('SELECT sth.* FROM Station st RIGHT JOIN StationHistory sth ON st.StationID=sth.StationID WHERE st.StationID IS NULL ORDER BY sth.Creation DESC');
    $result = $stmt->execute();

    if ($result) {
        print_result_stations_history($stmt, $format);
    }
}

function print_stations_list_changed($db, $format, $stationid){
    $stmt = $db->prepare('SELECT sth.* FROM Station st RIGHT JOIN StationHistory sth ON st.StationID=sth.StationID WHERE st.StationID IS NOT NULL AND sth.StationID=:id ORDER BY sth.Creation DESC');
    $stmt->bindValue(':id', $stationid, PDO::PARAM_INT);
    $result = $stmt->execute();

    if ($result) {
        print_result_stations_history($stmt, $format);
    }
}

function print_stations_list_changed_all($db, $format){
    $stmt = $db->prepare('SELECT sth.* FROM Station st RIGHT JOIN StationHistory sth ON st.StationID=sth.StationID WHERE st.StationID IS NOT NULL ORDER BY sth.Creation DESC');
    $result = $stmt->execute();

    if ($result) {
        print_result_stations_history($stmt, $format);
    }
}


function print_output_item_dict_sep($format)
{
    if ($format == 'xml') {
        echo ' ';
    }
    if ($format == 'json') {
        echo ',';
    }
}

function print_output_item_arr_sep($format)
{
    if ($format == 'xml') {
    }
    if ($format == 'json') {
        echo ',';
    }
}

function print_output_arr_start($format)
{
    if ($format == 'xml') {
    }
    if ($format == 'json') {
        echo '[';
    }
}

function print_output_arr_end($format)
{
    if ($format == 'xml') {
    }
    if ($format == 'json') {
        echo ']';
    }
}

function print_output_header($format)
{
    if ($format == 'xml') {
        header('content-type: text/xml');
        echo '<result>';
    }
    if ($format == 'json') {
        header('content-type: application/json');
    }
}

function print_output_footer($format)
{
    if ($format == 'xml') {
        echo '</result>';
    }
    if ($format == 'json') {
    }
}

function print_output_item_start($format, $itemname)
{
    if ($format == 'xml') {
        echo '<'.$itemname.' ';
    }
    if ($format == 'json') {
        echo '{';
    }
}

function print_output_item_end($format)
{
    if ($format == 'xml') {
        echo '/>';
    }
    if ($format == 'json') {
        echo '}';
    }
}

function print_output_item_content($format, $key, $value)
{
    if ($format == 'xml') {
        echo $key.'="'.htmlspecialchars($value, ENT_QUOTES).'"';
    }
    if ($format == 'json') {
        echo '"'.$key.'":"'.addcslashes(str_replace('\\', '', $value), '"').'"';
    }
}

function backupStation($db, $stationid)
{
    // backup old content
    $stmt = $db->prepare('INSERT INTO StationHistory(StationID,Name,Url,Homepage,Favicon,Country,SubCountry,Language,Tags,Votes,NegativeVotes,Creation) SELECT StationID,Name,Url,Homepage,Favicon,Country,SubCountry,Language,Tags,Votes,NegativeVotes,NOW() FROM Station WHERE StationID=:id');
    $result = $stmt->execute(['id' => $stationid]);
}

function autosetFavicon($db, $stationid, $homepage, &$favicon, &$log){
    $log = array();
    array_push($log, "extract from url: ".$homepage);
    $images = extractFaviconFromUrl($homepage, $logExtract);
    $log = array_merge($log,$logExtract);
    if (count($images) > 0){
        $favicon = $images[0];
        array_push($log, "extract ok: ".$favicon);
        $stmt = $db->prepare('UPDATE Station SET Favicon=:favicon WHERE StationID=:id');
        $result = $stmt->execute(['id'=>$stationid,'favicon'=>$favicon]);
        if ($result){
            array_push($log, "database update ok");
            return true;
        }
    }
    return false;
}

function addStation($db, $format, $name, $url, $homepage, $favicon, $country, $language, $tags, $state)
{
    if ($format !== "xml" && $format !== "json"){
        sendResult($format, false, "unknown format");
        return false;
    }
    if ($name === null){
        sendResult($format, false, "name is mandatory");
        return false;
    }
    if ($url === null || $url === ""){
        sendResult($format, false, "url is mandatory");
        return false;
    }

    $data = [
      'name' => $name,
      'url' => $url
    ];
    $data["homepage"] = $homepage === null ? "" : $homepage;
    $data["favicon"] = $favicon === null ? "" : $favicon;
    $data["country"] = $country === null ? "" : $country;
    $data["language"] = $language === null ? "" : $language;
    $data["tags"] = $tags === null ? "" : $tags;
    $data["state"] = $state === null ? "" : $state;

    $stmt = $db->prepare('INSERT INTO Station(Name,Url,Creation,Homepage,Favicon,Country,Language,Tags,Subcountry) VALUES(:name,:url,NOW(),:homepage,:favicon,:country,:language,:tags,:state)');
    $stmt->execute($data);

    if ($stmt->rowCount() !== 1) {
        sendResult($format, false, "insert did not work");
        return false;
    }else{
        $stationid = $db->lastInsertId();

        $working = checkStationConnectionById($db, $stationid, $url, $bitrate, $codec, $logConnection);
        $returnValue = array(
            'id' => "".$stationid,
            'stream_check_ok' => $working ? "true" : "false"
        );
        if ($working){
            $returnValue['stream_check_bitrate'] = "".$bitrate;
            $returnValue['stream_check_codec'] = $codec;
        }

        if ($homepage !== null && ($favicon === "" || $favicon === null)){
            $faviconCheck = autosetFavicon($db, $stationid, $homepage, $favicon, $logFavicon);
            $returnValue["favicon_check_ok"] =  $faviconCheck ? "true" : "false";
            if ($faviconCheck){
                $returnValue["favicon_check_url"] = $favicon;
            }
            $returnValue["favicon_check_done"] = "true";
        }else{
            $returnValue["favicon_check_done"] = "false";
        }

        sendResultParameters($format, true, "added station successfully", $returnValue);
        return true;
    }
}

function editStation($db, $format, $stationid, $name, $url, $homepage, $favicon, $country, $language, $tags, $state)
{
    if ($format !== "xml" && $format !== "json"){
        sendResult($format, false, "unknown format");
        return false;
    }
    if ($stationid === null){
        sendResult($format, false, "stationid is mandatory");
        return false;
    }
    if ($name === ""){
        sendResult($format, false, "name cannot be empty");
        return false;
    }
    if ($url === ""){
        sendResult($format, false, "url cannot be empty");
        return false;
    }
    backupStation($db, $stationid);
    $data = ['id' => $stationid];
    $columnStr = "";

    // update values
    if ($name !== null) {
        $data["name"] = $name;
        $columnStr .= ",Name=:name";
    }
    if ($url !== null) {
        $data["url"] = $url;
        $columnStr .= ",Url=:url";
    }

    if ($homepage !== null) {
        $data["homepage"] = $homepage;
        $columnStr .= ",Homepage=:homepage";
    }
    if ($favicon !== null) {
        $data["favicon"] = $favicon;
        $columnStr .= ",Favicon=:favicon";
    }

    if ($country !== null) {
        $data["country"] = $country;
        $columnStr .= ",Country=:country";
    }
    if ($language !== null) {
        $data["language"] = $language;
        $columnStr .= ",Language=:language";
    }

    if ($tags !== null) {
        $data["tags"] = $tags;
        $columnStr .= ",Tags=:tags";
    }
    if ($state !== null) {
        $data["state"] = $state;
        $columnStr .= ",Subcountry=:state";
    }
    $stmt = $db->prepare('UPDATE Station SET Creation=NOW()'.$columnStr.' WHERE StationID=:id');
    $stmt->execute($data);

    if ($stmt->rowCount() !== 1){
        sendResult($format, false, "could not find station with matching id");
        return false;
    }else{
        $returnValue = array(
            'id' => "".$stationid
        );
        if ($url !== null){
            $returnValue["stream_check_done"] = "true";
            $working = checkStationConnectionById($db, $stationid, $url, $bitrate, $codec, $logConnection);
            $returnValue["stream_check_ok"] = $working ? "true" : "false";

            if ($working){
                $returnValue['stream_check_bitrate'] = "".$bitrate;
                $returnValue['stream_check_codec'] = $codec;
            }
        }else{
            $returnValue["stream_check_done"] = "false";
        }

        if ($homepage !== null && ($favicon === "" || $favicon === null)){
            $faviconCheck = autosetFavicon($db, $stationid, $homepage, $favicon, $logFavicon);
            $returnValue["favicon_check_ok"] =  $faviconCheck ? "true" : "false";
            if ($faviconCheck){
                $returnValue["favicon_check_url"] = $favicon;
            }
            $returnValue["favicon_check_done"] = "true";
        }else{
            $returnValue["favicon_check_done"] = "false";
        }

        sendResultParameters($format, true, "changed station successfully", $returnValue);
        return true;
    }
}

function deleteStation($db, $format, $stationid)
{
    if (trim($stationid) != '' && $stationid !== null) {
        backupStation($db, $stationid);
        $stmt = $db->prepare('DELETE FROM Station WHERE StationID=:id');
        $result = $stmt->execute(['id' => $stationid]);
        if ($stmt->rowCount() === 1 && $result){
            sendResult($format, true,"deleted station successfully");
        }else{
            sendResult($format, false,"could not find station with matching id");
        }
    }else{
        sendResult($format, false, "stationid was null");
    }
}

function undeleteStation($db, $format, $stationid)
{
    if (trim($stationid) === '' || $stationid === null) {
        sendResult($format, false, "stationid was null");
        return;
    }
    try{
        // check if already existing
        $stmt = $db->prepare('SELECT StationID FROM Station WHERE StationID=:id');
        $stmt->execute(['id' => $stationid]);
        if ($stmt->rowCount() > 0){
            sendResult($format, false, "station with this id is already existing");
            return;
        }

        // get last backup of station
        $stmt = $db->prepare('SELECT StationChangeID FROM StationHistory WHERE StationID=:id ORDER BY Creation DESC LIMIT 1');
        $stmt->execute(['id' => $stationid]);
        if ($stmt->rowCount() !== 1){
            sendResult($format, false, "could not find station backup for id");
            return;
        }

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stationChangeId = $result["StationChangeID"];

        $stmt = $db->prepare('INSERT INTO Station(StationID,Name,Url,Homepage,Favicon,Country,SubCountry,Language,Tags,Votes,NegativeVotes,Creation) SELECT StationID,Name,Url,Homepage,Favicon,Country,SubCountry,Language,Tags,Votes,NegativeVotes,NOW() FROM StationHistory WHERE StationID=:id AND StationChangeID=:changeid');
        $stmt->execute(['id' => $stationid,'changeid' => $stationChangeId]);
        if ($stmt->rowCount() === 1){
            sendResult($format, true, "undeleted station successfully");
        }else{
            sendResult($format, false, "could not find station with matching id");
        }
    }catch(Exception $e){
        sendResult($format, false, "error on server");
    }
}

function revertStation($db, $format, $stationid, $stationchangeid)
{
    if (trim($stationid) === '' || $stationid === null) {
        sendResult($format, false, "stationid was null");
        return;
    }
    if (trim($stationchangeid) === '' || $stationchangeid === null) {
        sendResult($format, false, "stationchangeid was null");
        return;
    }
    try{
        // get last backup of station
        $stmt = $db->prepare('SELECT StationChangeID FROM StationHistory WHERE StationID=:id AND StationChangeID=:changeid');
        $stmt->execute(['id' => $stationid,'changeid' => $stationchangeid]);
        if ($stmt->rowCount() !== 1){
            sendResult($format, false, "could not find the matching station backup");
            return;
        }

        backupStation($db, $stationid);

        // delete current station
        $stmt = $db->prepare('DELETE FROM Station WHERE StationID=:id');
        $stmt->execute(['id' => $stationid]);

        // insert old station
        $stmt = $db->prepare('INSERT INTO Station(StationID,Name,Url,Homepage,Favicon,Country,SubCountry,Language,Tags,Votes,NegativeVotes,Creation) SELECT StationID,Name,Url,Homepage,Favicon,Country,SubCountry,Language,Tags,Votes,NegativeVotes,NOW() FROM StationHistory WHERE StationID=:id AND StationChangeID=:changeid');
        $stmt->execute(['id' => $stationid,'changeid' => $stationchangeid]);
        if ($stmt->rowCount() === 1){
            sendResult($format, true, "reverted station successfully");
        }else{
            sendResult($format, false, "could not find station with matching id");
        }
    }catch(Exception $e){
        sendResult($format, false, "error on server");
    }
}

function sendResult($format, $ok, $message){
    sendResultParameters($format, $ok, $message, null);
}

function sendResultParameters($format, $ok, $message, $otherParameters){
    if ($format === "xml" || $format === "json"){
        print_output_header($format);
        print_output_item_start($format, 'status');
        print_output_item_content($format, 'ok', $ok ? 'true' : 'false');
        print_output_item_dict_sep($format);
        print_output_item_content($format, 'message', $message);
        if ($otherParameters !== null){
            foreach ($otherParameters as $name => $value) {
                print_output_item_dict_sep($format);
                print_output_item_content($format, $name, $value);
            }
        }
        print_output_item_end($format);
        print_output_footer($format);
    }else{
        echo "supported formats are: xml, json";
        echo $message;
        http_response_code(406);
    }
}

function IPVoteChecker($db, $id)
{
    $ip = $_SERVER['REMOTE_ADDR'];

    // delete ipcheck entries after 10 minutes
    $db->query('DELETE FROM IPVoteCheck WHERE TIME_TO_SEC(TIMEDIFF(Now(),VoteTimestamp))>10*60');

    // was there a vote from the ip in the last 10 minutes?
    $stmt = $db->prepare('SELECT COUNT(*) FROM IPVoteCheck WHERE StationID=:id AND IP=:ip');
    $result = $stmt->execute(['id' => $id, 'ip' => $ip]);
    if ($result) {
        // if no, then add new entry
        $ccc = $stmt->fetchColumn(0);
        if ($ccc == 0) {
            $stmt = $db->prepare('INSERT INTO IPVoteCheck(IP,StationID) VALUES(:ip,:id)');
            $result = $stmt->execute(['id' => $id, 'ip' => $ip]);
            if ($result) {
                return true;
            }
        }
    }

    return false;
}

function clickedStationID($db, $id)
{
    $stmt = $db->prepare('INSERT INTO StationClick(StationID) VALUES(:id)');
    $result = $stmt->execute(['id' => $id]);

    $stmt = $db->prepare('UPDATE Station SET ClickTimestamp=NOW() WHERE StationID=:id');
    $result2 = $stmt->execute(['id' => $id]);

    if ($result && $result2) {
        return true;
    }

    return false;
}

function voteForStation($db, $format, $id)
{
    if (trim($id) === '' || $id === null) {
        sendResult($format, false, "stationid was null");
        return false;
    }

    if (!IPVoteChecker($db, $id)) {
        sendResult($format, false, "you are voting for the same station too often");
        return false;
    }
    $stmt = $db->prepare('UPDATE Station SET Votes=Votes+1 WHERE StationID=:id');
    $stmt->execute(['id' => $id]);
    if ($stmt->rowCount() === 1){
        sendResult($format, true, "voted for station successfully");
        return true;
    }else{
        sendResult($format, false, "could not find station with matching id");
        return false;
    }
}

function negativeVoteForStation($db, $format, $id)
{
    if (!IPVoteChecker($db, $id)) {
        print_station_by_id($db, $format, $id);

        return false;
    }
    $stmt = $db->prepare('UPDATE Station SET NegativeVotes=NegativeVotes+1 WHERE StationID=:id');
    $result = $stmt->execute(['id' => $id]);
    print_station_by_id($db, $format, $id);
    if ($result) {
        //$db->query("DELETE FROM Station WHERE NegativeVotes>5");
        return true;
    }

    return false;
}

function print_station_by_id($db, $format, $id)
{
    $stmt = $db->prepare('SELECT * from Station WHERE Station.StationID=:id');
    $result = $stmt->execute(['id' => $id]);
    if ($result) {
        print_result_stations($stmt, $format);
    }
}

function print_stations_list_data_url($db, $format, $url)
{
    if ($url === false || $url === null){
        sendResult($format, false, "parameter url is mandatory");
        return;
    }
    $stmt = $db->prepare('SELECT * from Station WHERE Station.Url=:url OR Station.UrlCache=:url');
    $result = $stmt->execute(['url' => $url]);
    if ($result) {
        print_result_stations($stmt, $format);
    }
}

function print_station_real_url($db, $format, $stationid){
    if ($format !== "xml" && $format !== "m3u" && $format !== "json" && $format !== "pls") {
        sendResult($format, false, "unknown format! supported formats: xml, json, pls, m3u");
        return false;
    }

    if ($stationid === null && $stationid === "") {
        sendResult($format, false, "empty parameter stationid");
        return false;
    }

    $stmt = $db->prepare('SELECT Name, Url, UrlCache FROM Station WHERE LastCheckOK=TRUE AND StationID=:stationid');
    $stmt->execute(['stationid'=>$stationid]);
    if ($stmt->rowCount() !== 1) {
        sendResult($format, false, "did not find station with matching id");
        return false;
    }

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $url = $row['Url'];
    $stationname = $row['Name'];

    // $audiofile = checkStation($url,$bitrate,$codec,$log);
    $audiofile = $row['UrlCache'];

    if ($audiofile !== false) {
        if ($format == "xml" || $format == "json") {
            $params = [
                'id' => $stationid,
                'name' => $stationname,
                'url' => $audiofile
            ];
            sendResultParameters($format, true, "retrieved station url successfully", $params);
            clickedStationID($db, $stationid);
            return true;
        } elseif ($format == 'pls') {
            //header('content-type: audio/x-scpls');
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename=radio.pls');
            header('Content-Transfer-Encoding: chunked'); //changed to chunked
            header('Expires: 0');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Pragma: public');

            echo "[playlist]\n";

            echo 'File1='.$audiofile."\n";
            echo 'Title1='.$stationname;
            clickedStationID($db, $stationid);
            return true;
        } elseif ($format == 'm3u') {
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename=radio.m3u');
            header('Content-Transfer-Encoding: chunked'); //changed to chunked
            header('Expires: 0');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Pragma: public');

            echo "#EXTM3U\n";
            echo "#EXTINF:1,".$stationname."\n";
            echo "".$audiofile."\n";
            clickedStationID($db, $stationid);
            return true;
        } else {
            sendResult($format, false, "unknown format");
            return false;
        }
    } else {
        sendResult($format, false, "did not find station with matching id");
        return false;
    }
}

function listExtractedImages($format, $url){
    if ($url === null || $url === false){
        sendResult($format, false, "parameter url is mandatory");
        return;
    }
    $images = extractFaviconFromUrl($url, $log);
    if ($format === "xml"){
        print_output_header($format);
        print_output_arr_start($format);

        print_output_item_start($format, "status");
        print_output_item_content($format, "ok", "true");
        print_output_item_end($format);

        echo "<images>";
        $i = 0;
        foreach ($images as $image){
            if ($i > 0) {
                print_output_item_arr_sep($format);
            }

            print_output_item_start($format, "image");
            print_output_item_content($format, "href", $image);
            print_output_item_end($format);

            ++$i;
        }
        echo "</images>";
        print_output_arr_end($format);
        print_output_footer($format);
    }
    if ($format === "json"){
        print_output_header($format);
        $result = array("ok" => "true", "images" => $images);
        echo json_encode($result);
    }
}
