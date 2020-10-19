<?php
require __DIR__.'/vendor/autoload.php';

function getDataFileNames($dirToCheck) {
    $dirs = scandir($dirToCheck);
    $dataFileNames = [];
    foreach ($dirs as $dir) {
        if ($dir == '..' || $dir == '.') continue;

        if (file_exists($dirToCheck.$dir.'/export.bin')) {
            $dataFileNames[] = $dirToCheck.$dir.'/export.bin';
        }
    }

    return $dataFileNames;
}


$fileNames = getDataFileNames('./data/');

$md5Collection = [];
$fileCollection = [];
foreach ($fileNames as $filename) {
    echo "Processing ".$filename."\n";
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
    echo "    startTimestamp: ". $pbuf->getStartTimestamp()." - ".date(DATE_RFC2822, $pbuf->getStartTimestamp())."\n";
    echo "    endTimestamp: ". $pbuf->getEndTimestamp()." - ".date(DATE_RFC2822, $pbuf->getEndTimestamp())."\n";

    /* @var $singleKey TemporaryExposureKey */
    foreach ($pbuf->getKeys() as $singleKey) {
        if (!array_key_exists($singleKey->getRollingStartIntervalNumber(),$md5Collection)) {
            $md5Collection[$singleKey->getRollingStartIntervalNumber()] = 1;
        } else {
            $md5Collection[$singleKey->getRollingStartIntervalNumber()]++;
        }
    }

    krsort($md5Collection);
    $fileCollection[$filename] =  $md5Collection;
    $md5Collection = [];
}





foreach ($fileCollection as $kk => $md5Collection) {
    foreach ($md5Collection as $k => $v) {
        echo $kk.":".date(DATE_RFC2822, $k*600).": ".$v."\n";
    }
    echo "\n\n";
}


