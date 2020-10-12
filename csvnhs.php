<?php
require __DIR__ . '/vendor/autoload.php';


function getDataFileNames() {
    $dirs = scandir('./datanhs');
    $dataFileNames = [];
    foreach ($dirs as $dir) {
        if ($dir == '..' || $dir == '.') continue;

        if (file_exists('./datanhs/'.$dir.'/export.bin')) {
            $dataFileNames[] = intval($dir);
        }
    }

    return $dataFileNames;
}



$files = [
'2020092900',
'2020093000',
'2020100100',
'2020100200',
'2020100300',
'2020100400',
'2020100500',
'2020100600',
'2020100700',
'2020100800',
'2020100900',
'2020101000',
'2020101100',
'2020101200',


];


foreach ($files as $remoteName) {
$filename = './datanhs/'.$remoteName.'/export.bin';

        $file = file_get_contents('https://distribution-te-prod.prod.svc-test-trace.nhs.uk/distribution/daily/'.$remoteName.'.zip');

        file_put_contents('./zipnhs/'.$remoteName.'.zip', $file);

        if (file_exists('./zipnhs/'.$remoteName.'.zip') && !file_exists('./datanhs/'.$remoteName)) {
            $zip = new ZipArchive;
            if ($zip->open('./zipnhs/'.$remoteName.'.zip') === TRUE) {
                // Unzip Path
                $zip->extractTo('./datanhs/'.$remoteName);
                $zip->close();
            } else {
            }
        }
}


$dirNames = getDataFileNames();
sort($dirNames);
$objects = [];

foreach ($dirNames as $dirName) {
    $filename = './datanhs/'.$dirName.'/export.bin';
    $data = "";

    $fp = fopen($filename,"rb");
    $discard = fread($fp, 12);
    while (!feof($fp)) {
        // Read the file, in chunks of 16 byte
        $data .= fread($fp,16);
    }


    $pbuf = new TemporaryExposureKeyExport();

    $stream = new \Google\Protobuf\Internal\CodedInputStream($data);
    $res = $pbuf->parseFromStream($stream);

    $finalObj = [
        'start_timestamp' => $pbuf->getStartTimestamp(),
        'end_timestamp' => $pbuf->getEndTimestamp(),
        'start_date' => date('Y-m-d H:i:s', $pbuf->getStartTimestamp()),
        'end_date' => date('Y-m-d H:i:s', $pbuf->getEndTimestamp()),
        'keys_count' => count($pbuf->getKeys())
    ];

    $objects[] = $finalObj;
}



foreach ($objects as $obj) {
    echo implode(';', array_values($obj));
    echo "<br />";
}


