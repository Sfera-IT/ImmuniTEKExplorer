<?php

// @clodo: Questo script fetcha tutte le fonti e popola ./data2/current.json, in modo da separare raccolta/computazione dati dal php che genera visualizzazione (e cacheizzo)

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
    return "./data2/";
}

// ----------------------------
// TEMP da sistemare
// ----------------------------



function downloadNewFilesCh() {

    $start_date = date_create('2020-09-19 00:00:00');
    $new_date = clone $start_date;
    $new_date_formatted = date_format($new_date, 'Ymd');

    // while not today
    while ($new_date_formatted != date_format((new DateTime())->modify('+1 day'), 'Ymd')) {
        $files[] = $new_date->getTimestamp()."000";
        $new_date->modify('+1 day');
        $new_date_formatted = date_format($new_date, 'Ymd');
    }

    foreach ($files as $remoteName) {        
        $binPath = getDataPath() . 'ch/bin/'.$remoteName.'/export.bin';

        $zipPath = getDataPath() . 'ch/zip/'.$remoteName.'.zip';
        if (!file_exists($zipPath))
        {
            $url = 'https://www.pt.bfs.admin.ch/v1/gaen/exposed/'.$remoteName;
            $file = file_get_contents($url);
            file_put_contents($zipPath, $file);
        
            if(file_exists($zipPath))
            {
                if(!file_exists(getDataPath() . 'ch/bin/'.$remoteName)) 
                {
                    $zip = new ZipArchive;
                    if ($zip->open(getDataPath() . 'ch/zip/'.$remoteName.'.zip') === TRUE) {
                        // Unzip Path
                        $zip->extractTo(getDataPath() . 'ch/bin/'.$remoteName);
                        $zip->close();
                    }
                    else
                    {
                        error("Unexpected: zip extract file");
                    }
                }
            }
        }
    }
}

function downloadNewFilesUk() {

    $start_date = date_create('2020-09-29');
    $new_date = clone $start_date;
    $new_date_formatted = date_format($new_date, 'Ymd');

    // while not today
    while ($new_date_formatted != date_format((new DateTime())->modify('+1 day'), 'Ymd')) {
        $files[] = $new_date_formatted."00";
        $new_date->modify('+1 day');
        $new_date_formatted = date_format($new_date, 'Ymd');
    }


    foreach ($files as $remoteName) {
        $binPath = getDataPath() . 'uk/bin/'.$remoteName.'/export.bin';

        $zipPath = getDataPath() . 'uk/zip/'.$remoteName.'.zip';
        if (!file_exists($zipPath))
        {

            $url = 'https://distribution-te-prod.prod.svc-test-trace.nhs.uk/distribution/daily/'.$remoteName.'.zip';                        
            $file = file_get_contents($url);
            file_put_contents($zipPath, $file);
        
            if(file_exists($zipPath))
            {
                if(!file_exists(getDataPath() . 'uk/bin/'.$remoteName)) 
                {
                    $zip = new ZipArchive;
                    if ($zip->open(getDataPath() . 'uk/zip/'.$remoteName.'.zip') === TRUE) {
                        // Unzip Path
                        $zip->extractTo(getDataPath() . 'uk/bin/'.$remoteName);
                        $zip->close();
                    }
                }
            }
        }
    }
}

function downloadFiles($countryCode, $countryData)
{
    // List of files
    $filesNames = array();
    if(isset($countryData["endpoint_list"]))
    {
        $filesNames = json_decode(file_get_contents($countryData["endpoint_list"]));
    }

    foreach($filesNames as $fileName)
    {
        mylog("Check " . $fileName);

        $zipPath = getDataPath() . $countryCode . "/zip/" . $fileName . ".zip";        
        if(file_exists($zipPath))
        {
            // Already exists
        }
        else
        {            
            $url = $countryData["endpoint_files"] . $fileName;
            mylog("Download " . $url);
            $fileData = file_get_contents($url);
            if($fileData !== FALSE)
            {
                file_put_contents($zipPath, $fileData);

                mylog("Extract " . $zipPath);

                $extractPath = getDataPath() . $countryCode . "/bin/" . $fileName;

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

function main()
{
    $timeStart = microtime(true);

    // Reset log
    file_put_contents(getDataPath() . "log.txt","");    

    // Read static JSON data
    $static = jsonDecode(file_get_contents("static.json"));

    // Build current JSON data
    $current = array();

    $current["static"] = $static;

    // 'days' will contain a map YYYYMMDD=>data, 
    $current["days"] = array();
    
    // ----------------------------
    // Load OWID
    // ----------------------------

    $dataOwidCovidPath = getDataPath() . "owid_" . date("Y-m-d") . ".json";
    $dataOwidCovidRaw = "";
    if(file_exists($dataOwidCovidPath))
    {
        $dataOwidCovidRaw = file_get_contents($dataOwidCovidPath);
    }
    else
    {
        $dataOwidCovidRaw = file_get_contents("https://covid.ourworldindata.org/data/owid-covid-data.json");
        file_put_contents($dataOwidCovidPath, $dataOwidCovidRaw);        
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
        foreach($dataOwidCovid[$countryAlpha3]["data"] as $item)
        {
            $d = $item["date"];
            $dn = date('Y-m-d', strtotime($d));
            $current["days"][$dn][$countryCode]["new_cases"] = $item["new_cases"];
        }            

        // ----------------------------
        // Fetch TEK
        // ----------------------------

        mylog("Build data for " . $countryCode);

        @mkdir(getDataPath() . $countryCode . '/zip',0777,true);
        @mkdir(getDataPath() . $countryCode . '/bin',0777,true);

        downloadFiles($countryCode, $countryData);
        // Exceptions
        if($countryCode === "ch")
        {
            downloadNewFilesCh();
        }
        else if($countryCode === "uk")
        {
            downloadNewFilesUk();
        }

        // Enum step
        // Attenzione: presuppone che siano ordinabili
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
        foreach ($dirNames as $dirName) 
        {
            $filename = getDataPath() . $countryCode . '/bin/'.$dirName.'/export.bin';

            mylog("Process file " . $filename);

            $data = "";

            $fp = fopen($filename,"rb");
            if($fp === FALSE)
                error("Unexpected fail to read " . $filename);

            $discard = fread($fp, 12);
            while (!feof($fp)) {
                // Read the file, in chunks of 16 byte
                $data .= fread($fp,16);
            }

            $pbuf = new TemporaryExposureKeyExport();

            $stream = new \Google\Protobuf\Internal\CodedInputStream($data);
            $res = $pbuf->parseFromStream($stream);

            $dateStart = date('Y-m-d', $pbuf->getStartTimestamp()); 
            $dateEnd = date('Y-m-d', $pbuf->getEndTimestamp());
            $d = $dateStart;
            
            if(isset($current["days"][$d][$countryCode]["nTek"]) === false)
                $current["days"][$d][$countryCode]["nTek"] = 0;
            $current["days"][$d][$countryCode]["nTek"] += count($pbuf->getKeys());
        }
    }

    // Ensure days sorted
    ksort($current["days"]);

    file_put_contents(getDataPath() . '/current.json', jsonEncode($current));

    /*
    echo "Final:\n";
    echo jsonEncode($current);
    echo "\n\n";
    */
    $timeEnd = microtime(true);
    $timeElapsed = $timeEnd-$timeStart;
    mylog("Done in " . $timeElapsed . " secs");
}

main();

?>
