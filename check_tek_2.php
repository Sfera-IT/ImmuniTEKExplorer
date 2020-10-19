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


$fileNames = getDataFileNames('./data2/ch/bin/');

$md5Collection = [];
$revisedCollection = [];
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
        $md5 = md5($singleKey->getKeyData());
        if (!array_key_exists($md5,$md5Collection)) {
            $md5Collection[$md5] = [
                'count' => 1,
                'file' => explode('/', $filename)[4],
                'startTimestamp' => $pbuf->getStartTimestamp(),
                'endTimestamp' => $pbuf->getEndTimestamp(),
            ];
        } else {
            $md5Collection[$md5]['count']++;
        }
    }

    /* @var $singleKey TemporaryExposureKey */
    foreach ($pbuf->getRevisedKeys() as $singleKey) {
        $md5 = md5($singleKey->getKeyData());
        if (!array_key_exists($md5,$md5Collection)) {
            $revisedCollection[$md5] = [
                'count' => 1,
                'file' => explode('/', $filename)[4],
                'startTimestamp' => $pbuf->getStartTimestamp(),
                'endTimestamp' => $pbuf->getEndTimestamp(),
            ];
        } else {
            $revisedCollection[$md5]['count']++;
        }
    }
}

$duplicated = array_filter($md5Collection, function($v) {
   if ($v['count'] > 1)
       return true;
   return false;
});

$duplicatedRevised = array_filter($revisedCollection, function($v) {
    if ($v['count'] > 1)
        return true;
    return false;
});




$fileNames = getDataFileNames('./datach/');

$md5Collection2 = [];
$revisedCollection2 = [];
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
        $md5 = md5($singleKey->getKeyData());
        if (!array_key_exists($md5,$md5Collection2)) {
            $md5Collection2[$md5] = [
                'count' => 1,
                'file' => explode('/', $filename)[2],
                'startTimestamp' => $pbuf->getStartTimestamp(),
                'endTimestamp' => $pbuf->getEndTimestamp(),
            ];
        } else {
            $md5Collection2[$md5]['count']++;
        }
    }

    /* @var $singleKey TemporaryExposureKey */
    foreach ($pbuf->getRevisedKeys() as $singleKey) {
        $md5 = md5($singleKey->getKeyData());
        if (!array_key_exists($md5,$md5Collection2)) {
            $revisedCollection2[$md5] = [
                'count' => 1,
                'file' => explode('/', $filename)[2],
                'startTimestamp' => $pbuf->getStartTimestamp(),
                'endTimestamp' => $pbuf->getEndTimestamp(),
            ];
        } else {
            $revisedCollection2[$md5]['count']++;
        }
    }
}

$duplicated2 = array_filter($md5Collection2, function($v) {
    if ($v['count'] > 1)
        return true;
    return false;
});

$duplicatedRevised2 = array_filter($revisedCollection2, function($v) {
    if ($v['count'] > 1)
        return true;
    return false;
});




echo "Numero di TEK analizzate: ".count($md5Collection)."\n";
echo "Numero di TEK duplicate: ".count($duplicated)."\n";
echo "Numero di TEK revised: ".count($revisedCollection)."\n";
echo "Numero di TEK revides duplicated: ".count($duplicatedRevised)."\n";

echo "Numero di TEK analizzate: ".count($md5Collection2)."\n";
echo "Numero di TEK duplicate: ".count($duplicated2)."\n";
echo "Numero di TEK revised: ".count($revisedCollection2)."\n";
echo "Numero di TEK revides duplicated: ".count($duplicatedRevised2)."\n";


$tekDiff1 = array_diff(array_keys($md5Collection), array_keys($md5Collection2)); // tek not present in newest export
$tekDiff2 = array_diff(array_keys($md5Collection2), array_keys($md5Collection)); // tek not present in OLDEST export

echo "Numero di TEK presenti nel vecchio export ma non nel nuovo: ".count($tekDiff2)."\n";
echo "Numero di TEK presenti nel nuovo export ma non nel vecchio: ".count($tekDiff1)."\n";

$tekWithDifferentDays = [];
foreach ($md5Collection as $k => $newTEK) {
    $oldTEK = $md5Collection2[$k];
    if ($newTEK['startTimestamp'] != $oldTEK['startTimestamp'])
    {
        $tekWithDifferentDays[] = $newTEK;
    }
}
echo "Numero di TEK spostate di giorni: ".count($tekWithDifferentDays)."\n";


$tekWithDifferentFiles = [];
foreach ($md5Collection as $k => $newTEK) {
    $oldTEK = $md5Collection2[$k];
    if ($newTEK['file'] != $oldTEK['file'])
    {
        $tekWithDifferentFiles[] = $newTEK;
    }
}
echo "Numero di TEK spostate di file: ".count($tekWithDifferentFiles)."\n";


