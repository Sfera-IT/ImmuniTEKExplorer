<?php
require __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/functions-nhs.php';

downloadNewFiles();

$dirNames = getDataFileNames();
sort($dirNames);
$objects = [];

$hit = $_SERVER['REMOTE_ADDR'];
file_put_contents('./charthitsnhs.txt', $hit."\n", FILE_APPEND);
$totKeys = 0;

$file = file_get_contents('./cache.json');
$content = json_decode($file, true);

if (!$content || ((new DateTime())->getTimestamp()) - $content['timestamp'] > 86400) {

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
            'y' => count($pbuf->getKeys()),
            'x' => date('Y-m-d', $pbuf->getStartTimestamp()),
        ];

        $totKeys += count($pbuf->getKeys());

        $objects[] = $finalObj;
    }

    $cache['timestamp'] = (new DateTime())->getTimestamp();
    $cache['objects'] = $objects;
    $json = json_encode($cache);
    file_put_contents('./cache.json', $json);
} else {
    $objects = $content['objects'];
}

$groupObjects = [];
foreach ($objects as $object) {
    if (array_key_exists($object['x'], $groupObjects)) {
        $groupObjects[$object['x']] += $object['y'];
    } else {
        $groupObjects[$object['x']] = $object['y'];
    }
}

$finalArray = [];
foreach ($groupObjects as $k => $v) {
    $finalArray[] = ['y' => $v, 'x' => $k];
}

//header('Content-Type: application/json');


?>
<html>
<head>

</head>
<body>
<h1>Dati delle TEK dell'app NHS-Covid-19 Inglese</h1>

I dati di oggi non vanno considerati definitivi fino alla mezzanotte <br />
Totale TEK caricate dal 29 settembre ad oggi: <?php echo "DA FIXARE" ?> <br />
Stima positivi dal 29 settembre ad oggi (TEK/14): <?php echo "DA FIXARE" ?> <br />
Visualizzazioni di questa pagina: <?php echo explode(" ", exec('wc -l ./charthitsnhs.txt'))[0]; ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.8.0/Chart.bundle.js"></script>
<div class="container">
    <canvas id="examChart"></canvas>
</div>
<script>
    var ctx = document.getElementById("examChart").getContext("2d");

    var myChart = new Chart(ctx, {
        type: 'line',
        data: {
            datasets: [{
                label: 'TEK per day',
                data: <?php echo json_encode($finalArray); ?> ,
                backgroundColor: [
                    'rgba(255, 99, 132, 0.2)',
                    'rgba(54, 162, 235, 0.2)',
                    'rgba(255, 206, 86, 0.2)',
                    'rgba(75, 192, 192, 0.2)',
                    'rgba(153, 102, 255, 0.2)',
                    'rgba(255, 159, 64, 0.2)'
                ],
                borderColor: [
                    'rgba(255,99,132,1)',
                    'rgba(54, 162, 235, 1)',
                    'rgba(255, 206, 86, 1)',
                    'rgba(75, 192, 192, 1)',
                    'rgba(153, 102, 255, 1)',
                    'rgba(255, 159, 64, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            scales: {
                xAxes: [{
                    type: 'time',
                    time: {
                        unit: 'day'
                    }
                }]
            }
        }
    });
</script>
</body>
</html>
