<?php
$content = file_get_contents('index.php');
preg_match_all('/<script>(.*?)<\/script>/s', $content, $matches);
foreach($matches[1] as $i => $js) {
    $js = preg_replace('/<\?php.*?\?>/s', 'null', $js);
    $js = preg_replace('/<\?=.*?\?>/s', 'null', $js);
    file_put_contents('temp_check_'.$i.'.js', $js);
    passthru('node -c temp_check_'.$i.'.js 2>&1', $ret);
    if($ret !== 0) echo 'ERROR in block '.$i.PHP_EOL;
    unlink('temp_check_'.$i.'.js');
}
echo "Done";
