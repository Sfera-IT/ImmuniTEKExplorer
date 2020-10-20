<?php

// @clodo: Questo script fetcha tutte le fonti e popola ./data/current.json, in modo da separare raccolta/computazione dati dal php che genera visualizzazione
// @clodo: Questo script gira ogni ora (riga in /etc/crontab). Usate una copia per esperimenti.

require __DIR__ . '/vendor/autoload.php';

ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// ----------------------------
// Utils
// ----------------------------

function error($desc) 
{ 
    throw new Exception($desc);
}

function jsonEncode($data)
{
    return json_encode($data, JSON_PRETTY_PRINT);
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
                for($i=$dataList["oldest"];$i<$dataList["newest"];$i++)
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
                $result[] = $new_date_formatted . "00.zip";
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

            $url = $countryData["tek"]["files"] . $fileName;

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

    // Reset log    
    file_put_contents(getDataPath() . "log.txt","");    
    mylog("Log path:" . getDataPath() . "log.txt");

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

        file_put_contents(getDataPath() . "owid_" . $countryCode . ".json", jsonEncode($dataOwidCovid[$countryData["alpha3"]]));

        $countryAlpha3 = $countryData["alpha3"];
        if(isset($dataOwidCovid[$countryAlpha3]) === false)
            error("Fatal error: " . $countryAlpha3 . " not found in OWID data");
        foreach($dataOwidCovid[$countryAlpha3]["data"] as $item)
        {
            $d = $item["date"];
            $dn = date('Y-m-d', strtotime($d));
            $current["days"][$dn][$countryCode]["new_cases"] = $item["new_cases"];
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

            $dateStart = date('Y-m-d', $pbuf->getStartTimestamp()); 
            $dateEnd = date('Y-m-d', $pbuf->getEndTimestamp());
            $d = date('Y-m-d', $pbuf->getEndTimestamp());

            mylog("Tek: File:" . $filename . ", TimeStart:" . date('r', $pbuf->getStartTimestamp()) . ", TimeEnd:" . date('r', $pbuf->getEndTimestamp()) . ", Keys:" . count($pbuf->getKeys()));

            foreach ($pbuf->getKeys() as $singleKey) {                
                $sha256 = hash('sha256',$singleKey->getKeyData());                

                $rollingStartIntervalNumber = $singleKey->getRollingStartIntervalNumber();
                $rollingPeriod = $singleKey->getRollingPeriod();

                //echo jsonEncode($sha256,true) . "\n";
                //echo jsonEncode($rollingStartIntervalNumber,true) . "\n";
                //echo jsonEncode($rollingPeriod,true) . "\n";
                
                mylog("Key: " . $sha256 . " - Period: " . $rollingPeriod . " - Time: " . date('r', $keyTime));

                $keyTime = $rollingStartIntervalNumber*10*60;
            }
            
            if(isset($current["days"][$d][$countryCode]["nTek"]) === false)
                $current["days"][$d][$countryCode]["nTek"] = 0;
            $current["days"][$d][$countryCode]["nTek"] += count($pbuf->getKeys());
        }
    }

    // Ensure days sorted
    ksort($current["days"]);

    file_put_contents(getDataPath() . '/current.json', jsonEncode($current));

    $timeEnd = microtime(true);
    $timeElapsed = $timeEnd-$timeStart;
    mylog("Done in " . $timeElapsed . " secs");
}

main();

?>
