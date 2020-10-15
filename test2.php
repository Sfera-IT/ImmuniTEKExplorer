<?php
$n = 1602460800;

for ($i = 0; $i < 10000; $i = $i+1) {
    echo "loop";
    $name = $n+$i;
    $name .= "000";
    $file = file_get_contents('https://www.pt.bfs.admin.ch/v1/gaen/exposed/'.$name);
    if ($file)
        file_put_contents('./test/'.$name.'.zip', $file);
}
echo "boh";