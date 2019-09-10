<?php


namespace OkayLicense;


use Okay\Core\Config;
use Okay\Core\Design;
use Okay\Core\Modules\AbstractInit;
use Okay\Core\OkayContainer\OkayContainer;
use Okay\Core\Request;
use Okay\Core\Response;
use Okay\Core\Router;
use Okay\Core\Modules\Module;
use Okay\Core\ServiceLocator;
use Smarty;

class License
{

    private static $config;
    private static $module;
    private static $validLicense = false;

    private static $freeModules = [
        'LigPay',
        'Rozetka',
    ];

    private static $licenseType;
    private static $smarty;
    private static $response;
    private static $request;
    private $responseType;

    public static function getHtml(Design $design, $template)
    {

        if ($design->isUseModuleDir() && $template != 'index.tpl' && !self::isActiveModule($design->getModuleVendor(), $design->getModuleName())) {
            return 'доступно только в PRO версии!';
        }

        if ($template == 'index.tpl' || $design->isUseModuleDir() === false) {
            $design->setSmartyTemplatesDir($design->getDefaultTemplatesDir());
        } else {
            $moduleTemplatesDir = $design->getModuleTemplatesDir();

            // Если указали обрабатывать шаблоны с директории модуля, но в основной директории есть его кастомная версия, берем её. 
            if (self::templateExistsInDefaultTemplatesDir($design, $template) && ($vendor = $design->getModuleVendor()) && ($module = $design->getModuleName())) {
                $moduleTemplatesDir = $design->getDefaultTemplatesDir() . "/modules/{$vendor}/{$module}/";
            }
            $design->setSmartyTemplatesDir($moduleTemplatesDir);
        }

        $html = self::$smarty->fetch($template);

        if ($design->isUseModuleDir() === false) {
            $design->setSmartyTemplatesDir($design->getDefaultTemplatesDir());
        } else {
            $design->setSmartyTemplatesDir($design->getModuleTemplatesDir());
        }

        if (self::$validLicense === false && $template == 'index.tpl' && strpos($design->getDefaultTemplatesDir(), 'backend/design/html') !== false) {
            $request = self::$request;
            $domain = $request::getDomainWithProtocol();
            $rootUrl = $request::getRootUrl();
            $html .= "<script>$(function() {
                alert('Current lisense is wrong for domain \"{$domain}\"');
            })</script>";
            if (self::$request->getBasePathWithDomain() != "$rootUrl/backend/index.php") {
                self::$response->redirectTo("$rootUrl/backend/index.php");
            }
        }

        return $html;
    }

    private static function templateExistsInDefaultTemplatesDir(Design $design, $template)
    {
        if (($vendor = $design->getModuleVendor()) && ($module = $design->getModuleName())) {
            $moduleCustomTemplates = $design->getDefaultTemplatesDir() . "/modules/{$vendor}/{$module}/";
            if (is_dir($moduleCustomTemplates)) {
                return in_array($template, scandir($moduleCustomTemplates));
            }
        }

        return false;
    }

    public function startModule($moduleId, $vendor, $moduleName)
    {

        /** @var OkayContainer $container */
        $container = OkayContainer::getInstance();

        $backendControllersList = [];
        $initClassName = self::$module->getInitClassName($vendor, $moduleName);
        if (!empty($initClassName)) {
            /** @var AbstractInit $initObject */
            $initObject = new $initClassName((int)$moduleId, $vendor, $moduleName);
            $initObject->init();
            foreach ($initObject->getBackendControllers() as $controllerName) {
                $controllerName = $vendor . '.' . $moduleName . '.' . $controllerName;
                if (!in_array($controllerName, $backendControllersList)) {
                    $backendControllersList[] = $controllerName;
                }
            }
        }

        $routes = self::$module->getRoutes($vendor, $moduleName);
        if (self::isActiveModule($vendor, $moduleName) === false) {
            foreach ($routes as &$route) {
                $route['mock'] = true;
            }
        }

        if (self::isActiveModule($vendor, $moduleName) === true) {

            $services = self::$module->getServices($vendor, $moduleName);
            $container->bindServices($services);
        }
        Router::bindRoutes($routes);
        return $backendControllersList;
    }

    public function check()
    {
        $this->init();
        $licenseParams = $this->getLicenseParams(self::$config->license);
        if (self::validateLicense() && self::checkDomain($licenseParams->nl['domains'])) {
            self::$validLicense = true;
        }
    }

    private static function isActiveModule($vendor, $moduleName)
    {
        if ($vendor != 'OkayCMS' || self::getLicenseType() == 'pro' || in_array($moduleName, self::$freeModules)) {
            return true;

        }
        return false;
    }

    private static function getDomain()
    {
        return getenv("HTTP_HOST");
    }

    private static function getLicenseType()
    {
        if (empty(self::$licenseType)) {
            $licenseParams = self::getLicenseParams(self::$config->license);
            self::$licenseType = 'lite';
            //self::$licenseType = $licenseParams->nl['version_type'];
        }
        return self::$licenseType;
    }

    private static function validateLicense()
    {
        @$license = self::$config->license;
        if (empty($license)) {
            self::error();
        }

        $licenseParams = self::getLicenseParams($license);
        if (empty($licenseParams->nl) || !is_array($licenseParams->nl['domains']) || empty($licenseParams->nl['version_type'])) {
            self::error();
        }

        if (!in_array($licenseParams->nl['version_type'], ['pro', 'lite'])) {
            self::error();
        }

        return true;
    }

    private static function checkDomain(array $licenseDomains)
    {
        $domain = self::getDomain();
        if (in_array($domain, $licenseDomains)) {
            return true;
        }

        foreach ($licenseDomains as $licenseDomain) {
            $licenseDomainParams = array_reverse(explode('.', $licenseDomain));
            if (count($licenseDomainParams) >= 2) {
                $domainParams = array_reverse(explode('.', $domain));

                foreach ($licenseDomainParams as $k=>$domainPart) {
                    if (!isset($domainParams[$k]) || $domainPart != $domainParams[$k]) {
                        break;
                    }

                    if ($k == count($licenseDomainParams) - 1) {
                        return true;
                    }

                }
            }
        }
        return false;
    }

    private static function error()
    {
        throw new \Exception('Some error with license');
    }

    private static function getLicenseParams($licenseString)
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
        @list($l->domains, $l->expiration, $l->comment, $nl) = explode('#', $r, 4);

        $l->domains = explode(',', $l->domains);

        if (!empty($nl)) {
            $t = "\phpseclib\Crypt\\" . chr(66) . chr(108) . chr(111) . chr(106 + $p) . chr(102) . chr(105) . chr(115) . chr(104);
            $nl = (new $t)->decrypt(base64_decode($nl));
            list($l->nl['domains'], $l->nl['version_type']) = explode('#', $nl, 2);

            if (!empty($l->nl['domains'])) {
                $domains = [];
                foreach (explode(',', $l->nl['domains']) as $d) {
                    $domains[] = trim($d);
                }
                $l->nl['domains'] = $domains;
            }
        }
        return $l;
    }

    public function setResponseType($responseType)
    {
        $this->responseType = $responseType;
    }

    public function __destruct()
    {
        if (empty($this->responseType) || ($this->responseType == RESPONSE_HTML && self::$validLicense === false)) {
            print "<div style='text-align:center; font-size:22px; height:100px;'>Лицензия недействительна<br><a href='http://okay-cms.com'>Скрипт интернет-магазина Okay</a></div>";
        }
    }

    private function init()
    {
        self::$validLicense = false;
        $SL = new ServiceLocator();
        self::$config = $SL->getService(Config::class);
        self::$module = $SL->getService(Module::class);
        self::$smarty = $SL->getService(Smarty::class);
        self::$response = $SL->getService(Response::class);
        self::$request = $SL->getService(Request::class);
    }

}