<?php

ini_set('display_errors', 1);

use OkayLicense\License;
use phpseclib\Crypt\Blowfish;

include 'vendor/autoload.php';

$licenseString = 'l7dngrodfs fxichwthwj ypjnplmqpt muotnruyxe whrbkkdq2t hz48mglary aq9czykspi jqqnwooxmq swpyqum4tm w4raxyxwoz ladxqu8xqw vnnztijoal ltnifsntym sxttqhr2ml vrn9tcqdql ratg9etgkn xfgqvnpimv pkosdtkwyu hosto5lkmz oewmxcwbre zputttgumm eigrgmku erwwltrvvz frhamvsckt vjrqphsjzc tvwqrluivv zhoykuiszy 6z8pgomokx xqu6lamwna ikrjrprvmp wnphvspwxo xjwyioxk7m 7vwruxt0gi uhtxkbfzis wugugqkuqf pxoslumnym qixqrvzozl xvoo';

$license = new License();
$license->check($licenseString);
die;
$test = new Blowfish();
//print $test->_decryptBlock('test string sss');

//var_dump(base64_encode($test->encrypt('test string sss test string sss test string sss ')));

//var_dump($test->encrypt('test string sss'));
//var_dump($test->decrypt(base64_decode('2yY6asGEycJdQ7AiXaffVHXHqEQ5ESSo1PvvSwisH9r++eXxU+eL/ogH/X0OyecVHG7x+wXKGfg=')));
//$license->check();

print generate_license('localhost', 'test', $test->encrypt('test string localhost'))['license'];
$license = 'l7dngrodfs fxichwthwj ypjnplmqpt muotnruyxe whrbkkdq2t hz48mglary aq9czykspi jqqnwooxmq swpyqum4tm w4raxyxwoz ladxqu8xqw vnnztijoal ltnifsntym sxttqhr2ml vrn9tcqdql ratg9etgkn xfgqvnpimv pkosdtkwyu hosto5lkmz oewmxcwbre zputttgumm eigrgmku erwwltrvvz frhamvsckt vjrqphsjzc tvwqrluivv zhoykuiszy 6z8pgomokx xqu6lamwna ikrjrprvmp wnphvspwxo xjwyioxk7m 7vwruxt0gi uhtxkbfzis wugugqkuqf pxoslumnym qixqrvzozl xvoo';

checkLicense($license);

function checkLicense($license)
{
    global $test;
    $p=13; $g=3; $x=5; $r = ''; $s = $x;
    $bs = explode(' ', $license);
    foreach($bs as $bl){
        for($i=0, $m=''; $i<strlen($bl)&&isset($bl[$i+1]); $i+=2){
            $a = base_convert($bl[$i], 36, 10)-($i/2+$s)%27;
            $b = base_convert($bl[$i+1], 36, 10)-($i/2+$s)%24;
            $m .= ($b * (pow($a,$p-$x-5) )) % $p;}
        $m = base_convert($m, 10, 16); $s+=$x;
        for ($a=0; $a<strlen($m); $a+=2) $r .= @chr(hexdec($m{$a}.$m{($a+1)}));}

    @list($l->domains, $l->expiration, $l->comment, $l->newLicense) = explode('#', $r, 4);

    $l->domains = explode(',', $l->domains);

    $l->newLicense = $test->decrypt(base64_decode($l->newLicense));
    
    
    print_r($l);
}


function generate_license($d, $comment='', $newLicense = '') {
    $part_index = 17;
    $license = '';
    if (!empty($d)) {
        if (strpos($d, ',') === false && strpos($d, '*.') === false) {
            $d .= ',www.'.$d.',*.'.$d;
        }
        $comment = (empty($comment) ? uniqid() : $comment);
        $newLicense = base64_encode($newLicense);
        $src = "$d#*#$comment#$newLicense";
        $p=13;
        $g=3;
        $x=5;
        $s=$x;
        for ($a=0; $a<strlen($src); $a+=2) {
            $ord = ord($src{$a});
            $hex = dechex($ord);
            if (isset($src{$a+1})) {
                $ord = ord($src{$a+1});
                $hex .= dechex($ord);
            }
            $dec = base_convert($hex, 16, 10);
            for ($i=0; $i<strlen($dec); $i++) {
                $parts = array();
                for($j=0;$j<36;$j++) {
                    for($k=0;$k<36;$k++) {
                        $aa = $j-($i+$s)%27;
                        $bb = $k-($i+$s)%24;
                        if ($aa < 0 || $bb < 0/* || $aa > 10 || $bb > 10*/) continue;
                        $res = ($bb * (pow($aa,$p-$x-5) )) % $p;
                        if ($res == $dec{$i}) {
                            $parts[] = base_convert($j, 10, 36).base_convert($k, 10, 36);
                        }
                    }
                }
                if (empty($parts)) {
                    die('some part can be encrypt...');
                }
                $part_index = rand(0, count($parts)-1);
                $license .= $parts[$part_index];
            }
            $license .= ' ';
            $s+=$x;
        }
        $license = trim($license);
    }
    return array('license'=>$license, 'comment'=>$comment);
}
