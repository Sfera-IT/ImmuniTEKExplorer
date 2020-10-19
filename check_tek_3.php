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


$fileNames = getDataFileNames('./data2/uk/bin/');

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
    $fileCollection[date(DATE_RFC2822, $pbuf->getEndTimestamp())] =  $md5Collection;
    $md5Collection = [];
}




$mediaTotale = 0;
$giriTotali = 0;
foreach ($fileCollection as $kk => $md5Collection) {
    $tot = 0;
    $first = 0;
    foreach ($md5Collection as $k => $v) {
        if ($first == 0)
            $first = $v;
        echo $kk.":".date(DATE_RFC2822, $k*600).": ".$v."\n";
        $tot += $v;
    }
    echo "media:".$tot/$first."\n";
    echo "\n\n";
    $giriTotali++;
    $mediaTotale += $tot / $first;
}

echo "Media totale: ". $mediaTotale / $giriTotali;


