<?php


function downloadNewFiles() {
    $index = json_decode(file_get_contents('https://get.immuni.gov.it/v1/keys/index'));

    $start = $index->oldest;
    $end = $index->newest;

    for ($i = $start; $i <= $end; $i++) {

        if (file_exists('./data/'.$i)) continue;

        $file = file_get_contents('https://get.immuni.gov.it/v1/keys/'.$i);

        file_put_contents('./zip/'.$i.'.zip', $file);

        if (file_exists('./zip/'.$i.'.zip') && !file_exists('./data/'.$i)) {
            $zip = new ZipArchive;
            if ($zip->open('./zip/'.$i.'.zip') === TRUE) {
                // Unzip Path
                $zip->extractTo('./data/'.$i);
                $zip->close();
            } else {
            }
        }
    }
}

function getDataFileNames() {
    $dirs = scandir('./data');
    $dataFileNames = [];
    foreach ($dirs as $dir) {
        if ($dir == '..' || $dir == '.') continue;

        if (file_exists('./data/'.$dir.'/export.bin')) {
            $dataFileNames[] = intval($dir);
        }
    }

    return $dataFileNames;
}
