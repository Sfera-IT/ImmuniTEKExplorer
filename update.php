<?php

// @clodo: Questo script fetcha tutte le fonti e popola ./data/current.json, in modo da separare raccolta/computazione dati dal php che genera visualizzazione
// @clodo: Questo script gira ogni ora (riga in /etc/crontab). Usate una copia per esperimenti. 

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/utils.php';

ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);

function downloadFiles($countryCode, $countryData)
{
    // List of files
    $filesNames = array();
    if(isset($countryData["tek"]["list"]))
    {
        if($countryData["tek"]["mode"] === "json-oldest-newest")
        {   
            $rawUrl = $countryData["tek"]["list"];
            mylog("Fetch for list");
            $rawList = fetchUrl($rawUrl);
            if($rawList !== null)
            {
                $dataList = jsonDecode($rawList);

                $result = array();
                for($i=$dataList["oldest"];$i<=$dataList["newest"];$i++)
                {
                    $result[] = $i;
                }

                $filesNames = $result;
            }
        }        
        else if($countryData["tek"]["mode"] === "json-filenames")
        {   
            $rawUrl = $countryData["tek"]["list"];
            mylog("Fetch for list");
            $rawList = fetchUrl($rawUrl);
            if($rawList !== null)
            {
                $dataList = jsonDecode($rawList);

                $filesNames = $dataList;
            }
        }     
        else if($countryData["tek"]["mode"] === "json-stoppcorona")
        {
            // TODO, json con X batch diversi di raggruppo
        }
        else if($countryData["tek"]["mode"] === "swiss")
        {
            $result = array();
            $start_date = date_create('2020-09-19 00:00:00');
            $new_date = clone $start_date;
            $new_date_formatted = date_format($new_date, 'Ymd');

            // while not today
            while ($new_date_formatted != date_format((new DateTime())->modify('+1 day'), 'Ymd')) {
                $result[] = $new_date->getTimestamp()."000";
                $new_date->modify('+1 day');
                $new_date_formatted = date_format($new_date, 'Ymd');
            }

            $filesNames = $result;
        }
        else if($countryData["tek"]["mode"] === "nhs")
        {
            $result = array();
            $start_date = date_create('2020-09-29');
            $new_date = clone $start_date;
            $new_date_formatted = date_format($new_date, 'Ymd');

            // while not today
            while ($new_date_formatted != date_format((new DateTime())->modify('+1 day'), 'Ymd')) {
                $result[] = $new_date_formatted . "00";
                $new_date->modify('+1 day');
                $new_date_formatted = date_format($new_date, 'Ymd');
            }

            $filesNames = $result;
        }
    }

    foreach($filesNames as $fileName)
    {
        mylog("Check file '" . $fileName . "'");

        $zipPath = getDataPath() . $countryCode . "/zip/" . $fileName . ".zip";

        if( ($countryData["tek"]["redownload"]) || (file_exists($zipPath) === false) )
        {
            usleep(100000); // Un minimo di sleep

            $url = $countryData["tek"]["files"];

            $url = str_replace("{@name}", $fileName, $url);

            $fileData = fetchUrl($url);
            if($fileData !== null)
            {
                $needSave = true;
                if(file_exists($zipPath))
                {                    
                    $hashN = (hash("sha256", $fileData));
                    $hashO = (hash("sha256", file_get_contents($zipPath)));
                    if( $hashN != $hashO )
                    {
                        mylog("File changed, resave!");
                        $needSave = true;   
                    }
                    else
                    {
                        mylog("Not changed.");
                        $needSave = true;
                    }
                }
                else
                {
                    mylog("New file, saved!");
                    $needSave = true;
                }
                
                if($needSave)
                {
                    file_put_contents($zipPath, $fileData);

                    // Extract
                    $extractPath = getDataPath() . $countryCode . "/bin/" . $fileName;
                    if(file_exists($extractPath))                    
                        exec("rm -rf \"" . $extractPath . "\"");
                    
                    mylog("Extract in " . $extractPath);

                    $zip = new ZipArchive;
                    if ($zip->open($zipPath) === TRUE) 
                    {
                        $zip->extractTo($extractPath);                
                        $zip->close();
                    }
                }
            }            
        }
    }
}

function main()
{    
    $timeStart = microtime(true);

    // Read config
    $config = jsonDecode(file_get_contents(__DIR__ . "/config.json"));

    // Reset log    
    file_put_contents(getDataPath() . "log.txt","");    
    mylog("Log path:" . getDataPath() . "log.txt");

    // DB
    $db = null;
    if(isset($config["db"]))
        $db = dbConnect($config["db"]["host"], $config["db"]["name"], $config["db"]["user"], $config["db"]["password"]);

    // Read static JSON data
    $static = jsonDecode(file_get_contents(__DIR__ . "/static.json"));

    if(isset($static["countries"]) === false)
        error("Fatal error, unable to read static.json?");

    // Build current JSON data
    $current = array();

    $current["static"] = $static;

    // 'days' will contain a map YYYYMMDD=>data, 
    $current["days"] = array();
    
    // ----------------------------
    // Load OWID
    // ----------------------------

    $dataOwidCovidPath = getDataPath() . "owid.json";
    $dataOwidCovidRaw = "";
    if( (file_exists($dataOwidCovidPath)) && (filemtime($dataOwidCovidPath)>time()-60*60*12) )
    {
        $dataOwidCovidRaw = file_get_contents($dataOwidCovidPath);
    }
    else
    {
        mylog("Fetch OWID data");
        $dataOwidCovidRaw = fetchUrl("https://covid.ourworldindata.org/data/owid-covid-data.json");
        if($dataOwidCovidRaw !== null)
        {
            file_put_contents($dataOwidCovidPath, $dataOwidCovidRaw);        
        }
    }    
    $dataOwidCovid = jsonDecode($dataOwidCovidRaw);

    // ----------------------------
    // Process for each country
    // ----------------------------

    foreach($static["countries"] as $countryCode => $countryData)
    {
        if($countryData["active"] === false)
            continue;

        // ----------------------------
        // OWID data
        // ----------------------------

        file_put_contents(getDataPath() . "/" . $countryCode . "/owid.json", jsonEncode($dataOwidCovid[$countryData["alpha3"]], true));

        $countryAlpha3 = $countryData["alpha3"];
        if(isset($dataOwidCovid[$countryAlpha3]) === false)
            error("Fatal error: " . $countryAlpha3 . " not found in OWID data");
        foreach($dataOwidCovid[$countryAlpha3]["data"] as $item)
        {
            $d = $item["date"];
            $dn = date('Y-m-d', strtotime($d));
            $current["days"][$dn][$countryCode]["new_cases"] = $item["new_cases"];
            $current["days"][$dn][$countryCode]["total_cases"] = $item["total_cases"];            
            $current["days"][$dn][$countryCode]["new_deaths"] = $item["new_deaths"];            
            $current["days"][$dn][$countryCode]["total_deaths"] = $item["total_deaths"];
        }

        // ----------------------------
        // Copy manual data
        // ----------------------------

        /* // Not yet used
        $dataManualCountryPath = __DIR__ . "/data_manual/" . $countryCode . ".json";
        if(file_exists($dataManualCountryPath))
        {
            $dataCountryManual = jsonDecode(file_get_contents($dataManualCountryPath));            
            foreach($dataCountryManual as $day => $dayData)
            {
                $current["days"][$day][$countryCode] = array_merge($current["days"][$day][$countryCode], $dayData);
            }
        }
        */

        // ----------------------------
        // Invoke plugin for custom code
        // ----------------------------
        {
            $pluginPath = __DIR__ . "/update_" . $countryCode . ".php";			
            if(file_exists($pluginPath))
            {
                require_once($pluginPath);
                $className = "TekExplorer_" . $countryCode;
                $plugin = new $className();            
                $plugin->do($current, $countryCode, $countryData);
            }
        }

        // ----------------------------
        // Fetch TEK
        // ----------------------------

        mylog("----------------");
        mylog($countryCode . " - " . $countryData["name"]);
        mylog("----------------");

        @mkdir(getDataPath() . $countryCode . '/zip',0777,true);
        @mkdir(getDataPath() . $countryCode . '/bin',0777,true);

        // Download and maintain the data fetched
        downloadFiles($countryCode, $countryData);
        
        // Enum step - Warning: alphanum sortable expected
        $binPath = getDataPath() . $countryCode . '/bin/';
        $dirs = scandir($binPath);
        $dirNames = [];
        foreach ($dirs as $dir) 
        {
            if ($dir == '..' || $dir == '.') continue;

            if (file_exists($binPath . '/' . $dir . '/export.bin')) {
                $dirNames[] = $dir;
            }
        }
        sort($dirNames);

                
        if($config["compute_keys_hit"])
        {
            // Reset Hit Counter        
            $sql = "update tek_keys set k_hit=0 where k_hit!=0 and k_source='" . escapeSql2($db, $countryCode) . "'";
            executeSql($db, $sql);
        }

        // Process TEK
        mylog("TEK analysis");
        foreach ($dirNames as $dirName) 
        {
            $filename = getDataPath() . $countryCode . '/bin/'.$dirName.'/export.bin';

            $data = "";

            $fp = fopen($filename,"rb");
            if($fp === FALSE)
                error("Unexpected fail to read " . $filename);

            $discard = fread($fp, 12);
            while (!feof($fp)) {                
                $data .= fread($fp,16);
            }

            $pbuf = new TemporaryExposureKeyExport();

            $stream = new \Google\Protobuf\Internal\CodedInputStream($data);
            $res = $pbuf->parseFromStream($stream);

            $batchStartUnix = $pbuf->getStartTimestamp();
            $batchEndUnix = $pbuf->getEndTimestamp();
            $batchStartTimestamp = date('Y-m-d', $batchStartUnix); 
            $batchEndTimestamp = date('Y-m-d', $batchEndUnix);
            $d = date('Y-m-d', $pbuf->getEndTimestamp());

            // DB Batch 
            if($db !== null)
            {
                $sqlWhere = " where k_name='" . escapeSql2($db, $dirName) . "' and k_source='" . escapeSql2($db, $countryCode) . "'";
                $rowBatch = fetchSqlRowNull($db, "select * from tek_batches " . $sqlWhere);
                if($rowBatch === null)
                {
                    $sql = "insert into tek_batches (k_name, k_source, k_first_start_ts, k_first_end_ts, k_last_start_ts, k_last_end_ts) values (";
                    $sql .= "'" . escapeSql2($db, $dirName) . "',";
                    $sql .= "'" . escapeSql2($db, $countryCode) . "',";
                    $sql .= "" . escapeSqlNum($batchStartUnix) . ",";
                    $sql .= "" . escapeSqlNum($batchEndUnix) . ",";
                    $sql .= "" . escapeSqlNum($batchStartUnix) . ",";
                    $sql .= "" . escapeSqlNum($batchEndUnix) . "";
                    $sql .= ")";
                    executeSql($db, $sql);
                    //mylog("SQL Batch Insert:" . $sql);
                }
                else
                {
                    if( (intval($rowBatch["k_last_start_ts"])!=$batchStartUnix) || (intval($rowBatch["k_last_end_ts"])!=$batchEndUnix) )
                    {
                        $sql = "update tek_batches set ";
                        $sql .= " k_last_start_ts=" . escapeSqlNum($batchStartUnix) . ",";
                        $sql .= " k_last_end_ts=" . escapeSqlNum($batchEndUnix) . ",";
                        $sql .= $sqlWhere;
                        executeSql($db,$sql);
                        //mylog("SQL Batch Update:" . $$sql);
                    }
                }
            }

            $nKeysNew = 0;
            $nKeysTotal = 0;

            $rollingDateMin = 0;
            $rollingDateMax = 0;

            foreach ($pbuf->getKeys() as $singleKey) 
            {
                $nKeysTotal++;
                //$id = hash('sha256',$singleKey->getKeyData());
                $id = bin2hex($singleKey->getKeyData());

                $transmissionRiskLevel = $singleKey->getTransmissionRiskLevel();                
                $rollingStartIntervalNumber = $singleKey->getRollingStartIntervalNumber();
                $rollingPeriod = $singleKey->getRollingPeriod();

                $rollingDate = $rollingStartIntervalNumber*10*60;

                if($rollingDateMin === 0)
                {
                    $rollingDateMin = $rollingDate;
                    $rollingDateMax = $rollingDate;
                }

                if($rollingDate<$rollingDateMin) $rollingDateMin = $rollingDate;
                if($rollingDate>$rollingDateMax) $rollingDateMax = $rollingDate;
                
                //mylog("Key: " . $id . " - Period: " . $rollingPeriod . " - Time: " . date('r', $rollingDate));

                // DB Keys
                if($db !== null)
                {
                    $sqlWhere = " k_id='" . escapeSql2($db, $id) . "'";
                    $rowCurrent = fetchSqlRowNull($db, "select * from tek_keys where " . $sqlWhere);
                    if($rowCurrent === null)
                    {
                        $sql = "insert into tek_keys (k_id, k_source, k_rolling_start_interval_number, k_rolling_period, k_rolling_date, k_transmission_risk_level, ";
                        $sql .= " k_batch_first_name, k_batch_first_start_ts, k_batch_first_end_ts, k_batch_last_name, k_batch_last_start_ts, k_batch_last_end_ts, k_hit";
                        $sql .= ") values (";
                        $sql .= "'" . escapeSql2($db, $id) . "',";
                        $sql .= "'" . escapeSql2($db, $countryCode) . "',";
                        $sql .= "" . escapeSqlNum($rollingStartIntervalNumber) . ",";
                        $sql .= "" . escapeSqlNum($rollingPeriod) . ",";
                        $sql .= "" . escapeSqlNum($rollingDate) . ",";
                        $sql .= "" . escapeSqlNum($transmissionRiskLevel) . ",";
                        $sql .= "'" . escapeSql2($db, $dirName) . "',";
                        $sql .= "" . escapeSqlNum($batchStartUnix) . ",";
                        $sql .= "" . escapeSqlNum($batchEndUnix) . ",";
                        $sql .= "'" . escapeSql2($db, $dirName) . "',";
                        $sql .= "" . escapeSqlNum($batchStartUnix) . ",";
                        $sql .= "" . escapeSqlNum($batchEndUnix) . ",";
                        $sql .= "1";
                        $sql .= ")";
                        executeSql($db, $sql);

                        $nKeysNew++;
                    }
                    else
                    {                        
                        if( ($rowCurrent["k_batch_last_name"] != $dirName) ||
                            (intval($rowCurrent["k_batch_last_start_ts"]) != $batchStartUnix) ||
                            (intval($rowCurrent["k_batch_last_end_ts"]) != $batchEndUnix) || 
                            (intval($rowCurrent["k_transmission_risk_level"]) != $transmissionRiskLevel)
                            )
                        {
                            $sql = "update tek_keys set ";
                            $sql .= " k_batch_last_name='" . escapeSql2($db, $dirName) . "',";
                            $sql .= " k_batch_last_start_ts=" . escapeSqlNum($batchStartUnix) . ",";
                            $sql .= " k_batch_last_end_ts=" . escapeSqlNum($batchEndUnix) . ",";
                            $sql .= " k_transmission_risk_level=" . escapeSqlNum($transmissionRiskLevel) . "";                            
                            $sql .= " where " . $sqlWhere;
                            executeSql($db, $sql);
                        }

                        if($config["compute_keys_hit"])
                        {
                            // Already exists, nothing to do?
                            $sql = "update tek_keys set k_hit=k_hit+1 where " . $sqlWhere;
                            executeSql($db, $sql);
                        }
                    }
                }
            }

            // Update batch
            {
                $sql = "update tek_batches set ";
                $sql .= " k_keys_min_rolling_date=" . escapeSqlNum($rollingDateMin) . ",";
                $sql .= " k_keys_max_rolling_date=" . escapeSqlNum($rollingDateMax) . ",";
                $sql .= " k_keys_count=" . escapeSqlNum($nKeysTotal) . "";
                $sql .= " where k_name='" . escapeSql2($db, $dirName) . "' and k_source='" . escapeSql2($db, $countryCode) . "'";
                executeSql($db, $sql);
            }

            mylog("Tek: File:" . $filename . ", BatchStartTime:" . date('r', $pbuf->getStartTimestamp()) . ", BatchEndTime:" . date('r', $pbuf->getEndTimestamp()) . ", Keys min time:" . date('r', $rollingDateMin) . ", Keys max time:" . date('r', $rollingDateMax) . ", Keys in file:" . $nKeysTotal . ", Keys new:" . $nKeysNew);
            
            if(isset($current["days"][$d][$countryCode]["nTek"]) === false)
                $current["days"][$d][$countryCode]["nTek"] = 0;
            $current["days"][$d][$countryCode]["nTek"] += count($pbuf->getKeys());
        }
    }

    // FullDB!
    if($db !== null)
    {
        $timeFrom = time()-60*60*24*30;
        $timeTo = time();

        $sql = "select ";
        $sql .= " DATE_FORMAT(from_unixtime(k_rolling_date), '%Y-%m-%d') as gdate,";
        $sql .= " k_source as country,";
        $sql .= " count(*) as n";
        $sql .= " from tek_keys";
        $sql .= " where";
        $sql .= " k_rolling_date>=" . escapeSqlNum($timeFrom) . " and k_rolling_date<" . escapeSqlNum($timeTo);
        $sql .= " group by Date_FORMAT(from_unixtime(k_rolling_date), '%Y-%m-%d'), k_source;";
        $keys = fetchSql($db, $sql);        
        foreach($keys as $key)
        {
            //$d = date('Y-m-d', $keys["gdate"];
            /*
            if(isset($current["days"][$key["gdate"]][$key["country"]]["n"]) === false)
                $current["days"][$key["gdate"]][$key["country"]]["n"] = 0;*/
            $current["days"][$key["gdate"]][$key["country"]]["nKeys"] = intval($key["n"]);
        }
    }

    // Ensure days sorted
    ksort($current["days"]);

    file_put_contents(getDataPath() . '/current.json', jsonEncode($current, true));

    //mylog("Total keys in DB: " . jsonEncode(fetchSql($db, "select k_source,count(*) from tek_keys group by k_source")));

    if($db !== null)
        mysqli_close($db);

    $timeEnd = microtime(true);
    $timeElapsed = $timeEnd-$timeStart;
    mylog("Done in " . $timeElapsed . " secs");
}

main();

?>
