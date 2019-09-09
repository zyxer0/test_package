<?php


namespace OkayLicense;


class License
{

    public function check($license)
    {
        $test = $this->getLicenseParams($license);
        print_r($test);
    }
    
    private function getDomain()
    {
        return getenv("HTTP_HOST");
    }
    
    private function getLicenseParams($licenseString)
    {
        $p=13; $g=3; $x=5; $r = ''; $s = $x;
        $bs = explode(' ', $licenseString);
        foreach($bs as $bl){
            for($i=0, $m=''; $i<strlen($bl)&&isset($bl[$i+1]); $i+=2){
                $a = base_convert($bl[$i], 36, 10)-($i/2+$s)%27;
                $b = base_convert($bl[$i+1], 36, 10)-($i/2+$s)%24;
                $m .= ($b * (pow($a,$p-$x-5) )) % $p;}
            $m = base_convert($m, 10, 16); $s+=$x;
            for ($a=0; $a<strlen($m); $a+=2) $r .= @chr(hexdec($m{$a}.$m{($a+1)}));}

        $l = new \stdClass();
        @list($l->domains, $l->expiration, $l->comment, $l->nl) = explode('#', $r, 4);

        $l->domains = explode(',', $l->domains);
        $t = "\phpseclib\Crypt\\" . chr(66).chr(108).chr(111).chr(106+$p).chr(102).chr(105).chr(115).chr(104);
        $l->nl = (new $t)->decrypt(base64_decode($l->nl));
        return $l;
    }
    
}