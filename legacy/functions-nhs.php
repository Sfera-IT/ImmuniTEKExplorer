<?php


function downloadNewFiles() {

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
        $filename = './datanhs/'.$remoteName.'/export.bin';

        $exists = './zipnhs/'.$remoteName.'.zip';
        if (!file_exists($exists))
        {
            $file = file_get_contents('https://distribution-te-prod.prod.svc-test-trace.nhs.uk/distribution/daily/'.$remoteName.'.zip');
            file_put_contents('./zipnhs/'.$remoteName.'.zip', $file);

            if (file_exists('./zipnhs/'.$remoteName.'.zip') && !file_exists('./datanhs/'.$remoteName)) {
                $zip = new ZipArchive;
                if ($zip->open('./zipnhs/'.$remoteName.'.zip') === TRUE) {
                    // Unzip Path
                    $zip->extractTo('./datanhs/'.$remoteName);
                    $zip->close();
                }
            }
        }
    }

}
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
