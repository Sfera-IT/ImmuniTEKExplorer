<?php
require __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/functions.php';

$file = $_GET['batch'];

// sanitize
$filename = './data/'.$file.'/export.bin';

downloadNewFiles();

if (
    !is_numeric($file) ||
    strlen($file) > 3 ||
    !file_exists($filename)
) {
    header('Content-Type: application/json');
    $names = getDataFileNames();

    sort($names);

    echo json_encode(['batches_from' => $names[0], 'batches_to' => $names[count($names)-1]]);
    die();
}


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

header('Content-Type: application/json');
echo json_encode($finalObj);
