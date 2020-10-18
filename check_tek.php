<?php
require __DIR__.'/vendor/autoload.php';

$dirToCheck = './data2/de/bin/';
function getDataFileNames() {
    GLOBAL $dirToCheck;
    $dirs = scandir($dirToCheck);
    $dataFileNames = [];
    foreach ($dirs as $dir) {
        if ($dir == '..' || $dir == '.') continue;

        if (file_exists($dirToCheck.$dir.'/export.bin')) {
            $dataFileNames[] = $dir;
        }
    }

    return $dataFileNames;
}


$dirNames = getDataFileNames();

$md5Collection = [];

foreach ($dirNames as $dirName) {
    $filename = $dirToCheck.$dirName.'/export.bin';
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
        $md5 = md5($singleKey->getKeyData());
        if (!array_key_exists($md5,$md5Collection)) {
            $md5Collection[$md5] = 1;
        } else {
            $md5Collection[$md5]++;
        }
    }
}

$duplicated = array_filter($md5Collection, function($v) {
   if ($v > 1)
       return true;
   return false;
});

echo "Numero di TEK analizzate: ".count($md5Collection)."\n";
echo "Numero di TEK duplicate: ".count($duplicated)."\n";
var_dump($duplicated);
