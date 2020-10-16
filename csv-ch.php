<?php
require __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/functions-ch.php';

downloadNewFiles();

$dirNames = getDataFileNames();
sort($dirNames);
$objects = [];

foreach ($dirNames as $dirName) {
    $filename = './datach/'.$dirName.'/export.bin';
    $data = "";

    $fp = fopen($filename,"rb");
    $discard = fread($fp, 12);
    while (!feof($fp)) {
        // Read the file, in chunks of 16 byte
        $data .= fread($fp,16);
    }

    if ($dirName == '1602633600000'){
        echo "";
    }

    $pbuf = new TemporaryExposureKeyExport();

    $stream = new \Google\Protobuf\Internal\CodedInputStream($data);
    $res = $pbuf->parseFromStream($stream);

    $finalObj = [
        'filename' => $dirName,
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


