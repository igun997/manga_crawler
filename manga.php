<?php
require "vendor/autoload.php";
use Igun997\Core\ZipPDF;
use Curl\Curl;
use PHPHtmlParser\Dom;

function rm_rec($cachePath){
     foreach(scandir($cachePath) as $file) {
         if ('.' === $file || '..' === $file) continue;
         if (is_dir("$cachePath/$file")) rm_rec("$cachePath/$file");
         else unlink("$cachePath/$file");
     }
        rmdir($cachePath);
}

$domain = $argv[1];
$link_path = $argv[2];

ZipPDF::log("Set Domain : ".$domain);
ZipPDF::log("Set Path Link Chapter : ".$link_path);
ZipPDF::log("Sleep 5 Seconds");
sleep(5);
$curl = new Curl();
$curl->setUserAgent('Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)');
$curl->setHeader("Accept","text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3");
$curl->setHeader("Accept-Language","en-US,en;q=0.9");
$curl->setReferrer('https://www.google.com/url?url=https%3A%2F%2Fwww.bacamanga.co%2F');
//$curl->setHeader('X-Requested-With', 'XMLHttpRequest');
$curl->get($domain);
$cookies = $curl->getResponseCookies();
foreach ($cookies as $index => $cookie) {
    $curl->setCookie($index,$cookie);
}
$curl->setCookieJar("cookie.txt");
$curl->setCookie("bmvisits",1);
if ($curl->error) {
    ZipPDF::log('Error: ' . $curl->errorCode . ': ' . $curl->errorMessage);
} else {
   $curl->get($link_path);
    $res = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', "", $curl->response);
    $dom = new Dom;
    $dom->loadStr($res);
    $html = $dom->find("a[class=dload]");
    $new = [];
    foreach ($html as $index => $item) {
        $link = $item->getAttribute("href");
        if ($link){
            $new[] = $link;
        }
    }
    ZipPDF::log("Has ".count($new)." Link");
    $s = time();
    $path = __DIR__."/download/";
    ZipPDF::log("Path : ".$path);
    if (!is_dir($path)){
        mkdir($path);
    }
    mkdir($path.$s);
    ZipPDF::log("Downloading  . . .");
    krsort($new,1);
    $i = 0;
    foreach ($new as $index => $item) {
        $name = $i++.".zip";
        ZipPDF::log("Progress (".$index."/".count($new).")");
        ZipPDF::log("URL : ".$item);
        shell_exec("curl -L -o ".$path.$s."/$name ".$item);
        ZipPDF::log("Done ...");
    }

    $toPDF = new ZipPDF();
    $dpath = $path.$s;
    mkdir($dpath."/pdf");
    $dir = scandir($dpath);
    $dir = array_diff($dir,[".",".."]);
    foreach ($dir as $index => $item) {
        $is = explode(".",$item);
        if (count($is) != 2){
            unset($dir[$index]);
        }
    }
    $i = 0;
    foreach ($dir as $index => $item) {
        $cachePath = __DIR__."/cache/".time();
        if (!is_dir($dpath."/pdf/")){
            mkdir($dpath."/pdf/");
        }
        try {
            $toPDF->zip($dpath."/$item",$dpath."/pdf/".$i++.".pdf",$cachePath);
        }catch (\PhpZip\Exception\ZipException $e){
            ZipPDF::log("Kebanyakan Fetch !");
            rm_rec($dpath);
            exit();
        }

        rm_rec($cachePath);
    }
    $toPDF->merger($dpath."/pdf/",$dpath."/pdf");

//    ZipPDF::log($new);
}
