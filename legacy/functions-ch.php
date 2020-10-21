<?php


function downloadNewFiles() {

    $start_date = date_create('2020-09-19 00:00:00');
    $new_date = clone $start_date;
    $new_date_formatted = date_format($new_date, 'Ymd');

    // while not today
    while ($new_date_formatted != date_format((new DateTime())->modify('+1 day'), 'Ymd')) {
        $files[] = $new_date->getTimestamp()."000";
        $new_date->modify('+1 day');
        $new_date_formatted = date_format($new_date, 'Ymd');
    }


    foreach ($files as $remoteName) {
        $filename = './datach/'.$remoteName.'/export.bin';

        $exists = './zipch/'.$remoteName.'.zip';
        if (!file_exists($exists))
        {
            $file = file_get_contents('https://www.pt.bfs.admin.ch/v1/gaen/exposed/'.$remoteName);
            file_put_contents('./zipch/'.$remoteName.'.zip', $file);

            if (file_exists('./zipch/'.$remoteName.'.zip') && !file_exists('./datach/'.$remoteName)) {
                $zip = new ZipArchive;
                if ($zip->open('./zipch/'.$remoteName.'.zip') === TRUE) {
                    // Unzip Path
                    $zip->extractTo('./datach/'.$remoteName);
                    $zip->close();
                }
            }
        }
    }

}
function getDataFileNames() {
    $dirs = scandir('./datach');
    $dataFileNames = [];
    foreach ($dirs as $dir) {
        if ($dir == '..' || $dir == '.') continue;

        if (file_exists('./datach/'.$dir.'/export.bin')) {
            $dataFileNames[] = intval($dir);
        }
    }

    return $dataFileNames;
}
