<?php

function error($desc) 
{ 
    throw new Exception($desc);
}

function jsonEncode($json, $pretty = false)
{
    $result = json_encode($json, (($pretty) ? JSON_PRETTY_PRINT:0));
    
    if(json_last_error_msg() != "No error")
    {
        logDebug("Hit JSON encoding error");
        $result = json_encode(array("error" => json_last_error_msg()));
    }
    
    return $result;
}

function jsonDecode($data)
{
    return json_decode($data, true);
}

function mylog($v)
{
    $line = date('r') . " - " . $v . "\n";
    echo $line;
    file_put_contents(getDataPath() . "log.txt", $line, FILE_APPEND | LOCK_EX);
}

function extractStringBetween($src, $from, $to)
{
    $posFrom = strpos($src, $from);
    if($posFrom === false)
        return "";
    $posTo = strpos($src, $to, $posFrom + strlen($from));
    if($posTo === false)
        return "";
    return substr($src, $posFrom + strlen($from), $posTo-$posFrom-strlen($from));
}

function getDataPath()
{
    return __DIR__ . "/data/";
}

// Generic fetch with log
function fetchUrl($url, $post = null, $options = array())
{
    $result = fetchUrlEx($url, $post, $options);

    $error = "";

    if($result["error"] !== 0)
        $error = $result["error"];
    else if($result["info"]["http_code"] !== 200)
        $error = "HTTP " . $result["info"]["http_code"];
    
    if($error !== "")
    {
        mylog("Fetch " . $url . " failed: " . $error);
        return null;
    }
    else
    {
        $data = $result["body"];
        mylog("Fetch " . $url . " ok, " . strlen($data) . " bytes");
        return $data;
    }
}

function fetchUrlEx($url, $post = null, $options = array())
{
    $timeout = 120;
    if(isset($options["timeout"]))
        $timeout = $options["timeout"];
    
    $curl = curl_init($url);

    $curlOptions = array(
        CURLOPT_RETURNTRANSFER => true,     // return web page
        CURLOPT_HEADER         => true,    // don't return headers
        CURLOPT_FOLLOWLOCATION => true,     // follow redirects
        CURLOPT_ENCODING       => "",       // handle all encodings
        CURLOPT_USERAGENT      => "spider", // who am i
        CURLOPT_AUTOREFERER    => true,     // set referer on redirect
        CURLOPT_CONNECTTIMEOUT => $timeout,      // timeout on connect
        CURLOPT_TIMEOUT        => $timeout,      // timeout on response
        CURLOPT_MAXREDIRS      => 10,       // stop after 10 redirects
    );

    curl_setopt_array($curl, $curlOptions );

    if(isset($options["resolve"]))
        curl_setopt($curl, CURLOPT_RESOLVE, $options["resolve"]);

    if( (isset($options["verify"])) && ($options["verify"] === false) )
    {
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false); 
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    }
    
    if($post != null)
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($post));

    $response = curl_exec($curl);
    
    $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    
    $result = array();
    $result["error"] = curl_errno($curl);
    $result["error_message"] = curl_error($curl);
    $result["info"] = curl_getinfo($curl);
    $result["headers"] = array();
    foreach(explode("\n", substr($response, 0, $header_size)) as $headerLine)
    {
        $posDoubleDot = strpos($headerLine,":");
        if($posDoubleDot !== false)
            $result["headers"][trim(strtolower(substr($headerLine,0,$posDoubleDot)))] = trim(substr($headerLine,$posDoubleDot+1));
    }
    $result["body"] = substr($response, $header_size);
    
    curl_close($curl);
    
    return $result;
}

function dbConnect($host, $db, $user, $password)
{	
    $myConnection = mysqli_connect($host, $user, $password) or error("Unable to connect to database: ".mysqli_connect_error());				
    if($db != "none")
        $database = mysqli_select_db($myConnection, $db) or error("Unable to select the database: ".mysqli_error());
    
    $sql = "SET NAMES 'utf8'";
    mysqli_query($myConnection, $sql);		
    
    if($host == null)
        $connection = $myConnection;
    
    return $myConnection;
}

function escapeSql2($connection, $text)
{
    return mysqli_real_escape_string($connection, $text);
}

function escapeSqlNum($v)
{	
    return floatval($v);
}

function fetchSql($connection, $sql, $resultType = MYSQLI_ASSOC)
{	
    $result = executeSql($connection, $sql);
    
    $out = array();
    while ($row = mysqli_fetch_array($result, $resultType)) 
    {	
        $out[] = $row;
    }
    return $out;
}

function fetchSqlRowNull($connection, $sql, $resultType = MYSQLI_BOTH)
{	
    $result = executeSql($connection, $sql);
    
    $row = mysqli_fetch_array($result, $resultType);
    if($row)
        return $row;
    else
        return null;
}

function executeSql($connection, $sql)
{
    for(;;)
    {
        $retry = false;
        
        $result = mysqli_query($connection, $sql);
        if (!$result) 
        {
            $msg = mysqli_error($connection);
            if($msg == "Deadlock found when trying to get lock; try restarting transaction")
            {
                $retry = true;
                sleep(5);
            }
            else if($msg == "Lock wait timeout exceeded; try restarting transaction")
            {
                $retry = true;
                sleep(5);
            }
            else
            {
                error("MySql Error:" . $msg . ", Sql:" . $sql);
                return false;
            }
        }
                
        if($retry == false)
            return $result;
    }
}

function getSqlField($connection, $sql, $default = null)
{
    $result = executeSql($connection, $sql);
    $row = mysqli_fetch_array($result);
    if($row == null)
        return $default;
    else
        return $row[0];
}

