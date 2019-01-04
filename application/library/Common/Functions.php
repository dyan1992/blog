<?php
/**
 * 公共函数库
 */

use Yaf\Dispatcher;
use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\WhatFailureGroupHandler;
use Monolog\Handler\BrowserConsoleHandler;
use Monolog\Processor\IntrospectionProcessor;
use Monolog\Processor\PsrLogMessageProcessor;
#use Buzz\Browser;
#use Buzz\Message\Response;

/**
 * 向swoole task server 投递任务
 *
 * @param  mixed  $data       数据包
 * @param  string $addr       地址
 * @param  int    $port       端口
 * @param  float  $timeout    超时
 * @throws \Exception
 */
function sendTask($data, $addr = '127.0.0.1', $port = 9666, $timeout = 0.5)
{
    $client = new Client(SWOOLE_SOCK_TCP);
    if (!@$client->connect($addr, $port, $timeout)) {
        throw new \Exception('connect swoole task server failed.');
    }

    $content = is_array($data) ? json_encode($data) : $data;
    if (!$client->send($content)) {
        throw new \Exception('send data failed.');
    }

    if ($client->recv() != 'SUCCESS') {
        throw new \Exception('receive data invalid.');
    }

    $client->close();
}

/**
 * 文件日志
 * 如： logger()->error($e, ['extra msg..']);
 *
 * @param string $channel
 * @param null   $fileName
 *
 * @return Logger
 */
function logger($channel = 'normal', $fileName = null)
{
    $group[] = new RotatingFileHandler($fileName ?? LOG_PATH . DS . ENV . '.log', 5, Logger::INFO);

    if (ENV === 'develop' && !Dispatcher::getInstance()->getRequest()->isCli()) {
        array_push($group, new BrowserConsoleHandler());
    }

    $log = new Logger($channel);
    $log->pushHandler(new WhatFailureGroupHandler($group));
    $log->pushProcessor(new IntrospectionProcessor());
    $log->pushProcessor(new PsrLogMessageProcessor());

    return $log;
}

/**
 * HTTP方式请求
 *
 * @param  string $gateway
 * @param  array  $data
 * @param  string $method
 *
 * @return string
 * @throws \Exceptions\ServiceException
 */
function processHTTP($gateway, $data, $method = 'GET')
{
    $buzz = new \Buzz\Browser();

    /**
     * @var $response Response
     */
    if ($method == 'GET') {
        $response = $buzz->get(
            $gateway . '?' . http_build_query($data),
            []
        );
    } else if ($method == 'POST') {
        $response = $buzz->post(
            $gateway,
            [],
            http_build_query($data)
        );
    } else {
        $response = $buzz->post(
            $gateway,
            ['Content-Type' => 'application/json'],
            json_encode($data)
        );
    }

    $statusCode   = $response->getStatusCode();
    $reasonPhrase = $response->getReasonPhrase();

    if ($statusCode == 200) {
        return $response->getContent();
    } else {
        throw new \Exceptions\ServiceException("api request exception: $statusCode $reasonPhrase");
    }
}

function p($data)
{
    echo '<pre>';
    print_r($data);
}

function v($data)
{
    echo '<pre>';
    var_dump($data);
}

function L($data, $fileName = '', $dir = '')
{
    if (empty($fileName)) {
        $fileName = CUR_DATE . ".log";
    }

    if (empty($dir)) {
        $dir = LOG_PATH;
    }

    if (is_array($data)) {
        $data = var_export($data, true);
    } elseif (!is_string($data)) {
        $data = json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $fileName = $dir . DS . $fileName;

    $data = date('[Y-m-d H:i:s] ') . $data . PHP_EOL;
    file_put_contents($fileName, $data, FILE_APPEND);
}

/**
 * 是否为微信请求
 *
 * @return bool|int
 */
function isWeChatAgent()
{
    return preg_match('/MicroMessenger/i', $_SERVER['HTTP_USER_AGENT']);
}

/**
 * 数组转XML
 *
 * @param  array    $arr
 * @param  string   $rootNode
 * @return string
 */
function array2Xml($arr, $rootNode = 'xml')
{
    $dom  = new DOMDocument("1.0");
    $item = $dom->createElement($rootNode);
    $dom->appendChild($item);

    foreach ($arr as $key => $val) {
        $itemX = $dom->createElement(is_string($key) ? $key : "item");
        $item->appendChild($itemX);
        if (!is_array($val)) {
            $text = $dom->createTextNode($val);
            $itemX->appendChild($text);
        } else {
            array2Xml($val, $rootNode);
        }
    }

    return $dom->saveXML();
}

/**
 * XML转数组
 *
 * @param $xml
 * @return mixed
 */
function xml2Array($xml)
{
    //禁止引用外部xml实体
    libxml_disable_entity_loader(true);
    $object = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
    return Common_Tools::object2array($object);
}

/**
 * 密码加密
 *
 * @param  string $password
 * @param  string $salt
 * @return string
 */
function encryptPassword($password, $salt = null)
{
    $salt === null && $salt = \Yaf\Registry::get('config')['security']['admin']['salt'];

    return sha1(md5($password . $salt));
}

/**
 * 获取客户端IP地址 FROM ThinkPHP 系统函数库
 *
 * @param integer $type 返回类型 0 返回IP地址 1 返回IPV4地址数字
 * @param boolean $adv  是否进行高级模式获取（有可能被伪装）
 * @return mixed
 */
function getClientIP($type = 0, $adv = false)
{
    $type = $type ? 1 : 0;
    static $ip = null;
    if ($ip !== null) {
        return $ip[$type];
    }
    if ($adv) {
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $arr = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $pos = array_search('unknown', $arr);
            if (false !== $pos) {
                unset($arr[$pos]);
            }
            $ip = trim($arr[0]);
        } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
    } elseif (isset($_SERVER['REMOTE_ADDR'])) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    // IP地址合法验证
    $long = sprintf("%u", ip2long($ip));
    $ip   = $long ? [$ip, $long] : ['0.0.0.0', 0];

    return $ip[$type];
}

/**
 * 对字符串等进行过滤
 *
 * @param $arr
 * @return null|string
 */
function filterStr($arr)
{
    if (!isset($arr)) {
        return null;
    }
    if (is_array($arr)) {
        filterArray($arr);
    } else {
        $arr = addslashes(removeXSS(stripHTML(trim($arr), true)));
    }

    return $arr;
}

/**
 * 对数组进行过滤
 *
 * @param $arr
 * @return array|null
 */
function filterArray($arr)
{
    if (!is_array($arr)) {
        return null;
    }
    foreach ($arr as $k => $v) {
        if (is_array($v)) {
            filterArray($v);
        } else {
            $arr[$k] = addslashes(removeXSS(stripHTML(trim($v), true)));
        }
    }

    return $arr;
}

/**
 * 移除HTML
 *
 * @param  string $content
 * @param  bool $xss
 * @return string
 */
function stripHTML($content, $xss = true)
{
    $search = [
        "@<script(.*?)</script>@is",
        "@<iframe(.*?)</iframe>@is",
        "@<style(.*?)</style>@is",
        "@<(.*?)>@is"
    ];

    $content = preg_replace($search, '', $content);

    if ($xss) {
        $ra1 = [
            'javascript',
            'vbscript',
            'applet',
            'meta',
            'blink',
            'script',
            'embed',
            'iframe',
            'frameset',
            'ilayer',
        ];

        $ra2 = [
            'onabort',
            'onactivate',
            'onafterprint',
            'onafterupdate',
            'onbeforeactivate',
            'onbeforecopy',
            'onbeforecut',
            'onbeforedeactivate',
            'onbeforeeditfocus',
            'onbeforepaste',
            'onbeforeprint',
            'onbeforeunload',
            'onbeforeupdate',
            'onblur',
            'onbounce',
            'oncellchange',
            'onchange',
            'onclick',
            'oncontextmenu',
            'oncontrolselect',
            'oncopy',
            'oncut',
            'ondataavailable',
            'ondatasetchanged',
            'ondatasetcomplete',
            'ondblclick',
            'ondeactivate',
            'ondrag',
            'ondragend',
            'ondragenter',
            'ondragleave',
            'ondragover',
            'ondragstart',
            'ondrop',
            'onerror',
            'onerrorupdate',
            'onfilterchange',
            'onfinish',
            'onfocus',
            'onfocusin',
            'onfocusout',
            'onhelp',
            'onkeydown',
            'onkeypress',
            'onkeyup',
            'onlayoutcomplete',
            'onload',
            'onlosecapture',
            'onmousedown',
            'onmouseenter',
            'onmouseleave',
            'onmousemove',
            'onmouseout',
            'onmouseover',
            'onmouseup',
            'onmousewheel',
            'onmove',
            'onmoveend',
            'onmovestart',
            'onpaste',
            'onpropertychange',
            'onreadystatechange',
            'onreset',
            'onresize',
            'onresizeend',
            'onresizestart',
            'onrowenter',
            'onrowexit',
            'onrowsdelete',
            'onrowsinserted',
            'onscroll',
            'onselect',
            'onselectionchange',
            'onselectstart',
            'onstart',
            'onstop',
            'onsubmit',
            'onunload'
        ];
        $ra  = array_merge($ra1, $ra2);

        $content = str_ireplace($ra, '', $content);
    }

    return strip_tags($content);
}

/**
 * 移除XSS
 *
 * @param $val
 * @return mixed
 */
function removeXSS($val)
{
    // remove all non-printable characters. CR(0a) and LF(0b) and TAB(9) are allowed
    // this prevents some character re-spacing such as <javaΘscript>
    // note that you have to handle splits with \n, \r, and \t later since they *are* allowed in some inputs
    $val = preg_replace('/([\x00-\x08][\x0b-\x0c][\x0e-\x20])/', '', $val);

    // straight replacements, the user should never need these since they're normal characters
    // this prevents like <IMG SRC=&#X40&#X61&#X76&#X61&#X73&#X63&#X72&#X69&#X70&#X74&#X3A&#X61&#X6C&#X65&#X72&#X74&#X28&#X27&#X58&#X53&#X53&#X27&#X29>
    $search = 'abcdefghijklmnopqrstuvwxyz';
    $search .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $search .= '1234567890!@#$%^&*()';
    $search .= '~`";:?+/={}[]-_|\'\\';
    for ($i = 0; $i < strlen($search); $i++) {
        // ;? matches the ;, which is optional
        // 0{0,7} matches any padded zeros, which are optional and go up to 8 chars

        // &#x0040 @ search for the hex values
        $val = preg_replace(
            '/(&#[x|X]0{0,8}' . dechex(ord($search[$i])) . ';?)/i', $search[$i],
            $val
        ); // with a ;
        // @ @ 0{0,7} matches '0' zero to seven times
        $val = preg_replace(
            '/(&#0{0,8}' . ord($search[$i]) . ';?)/', $search[$i], $val
        ); // with a ;
    }

    // now the only remaining whitespace attacks are \t, \n, and \r
    $ra1 = [
        'javascript',
        'vbscript',
        'applet',
        'meta',
        'blink',
        'script',
        'embed',
        'iframe',
        'frameset',
        'ilayer',
    ];

    $ra2 = [
        'onabort',
        'onactivate',
        'onafterprint',
        'onafterupdate',
        'onbeforeactivate',
        'onbeforecopy',
        'onbeforecut',
        'onbeforedeactivate',
        'onbeforeeditfocus',
        'onbeforepaste',
        'onbeforeprint',
        'onbeforeunload',
        'onbeforeupdate',
        'onblur',
        'onbounce',
        'oncellchange',
        'onchange',
        'onclick',
        'oncontextmenu',
        'oncontrolselect',
        'oncopy',
        'oncut',
        'ondataavailable',
        'ondatasetchanged',
        'ondatasetcomplete',
        'ondblclick',
        'ondeactivate',
        'ondrag',
        'ondragend',
        'ondragenter',
        'ondragleave',
        'ondragover',
        'ondragstart',
        'ondrop',
        'onerror',
        'onerrorupdate',
        'onfilterchange',
        'onfinish',
        'onfocus',
        'onfocusin',
        'onfocusout',
        'onhelp',
        'onkeydown',
        'onkeypress',
        'onkeyup',
        'onlayoutcomplete',
        'onload',
        'onlosecapture',
        'onmousedown',
        'onmouseenter',
        'onmouseleave',
        'onmousemove',
        'onmouseout',
        'onmouseover',
        'onmouseup',
        'onmousewheel',
        'onmove',
        'onmoveend',
        'onmovestart',
        'onpaste',
        'onpropertychange',
        'onreadystatechange',
        'onreset',
        'onresize',
        'onresizeend',
        'onresizestart',
        'onrowenter',
        'onrowexit',
        'onrowsdelete',
        'onrowsinserted',
        'onscroll',
        'onselect',
        'onselectionchange',
        'onselectstart',
        'onstart',
        'onstop',
        'onsubmit',
        'onunload'
    ];
    $ra  = array_merge($ra1, $ra2);

    $found
        = true; // keep replacing as long as the previous round replaced something
    while ($found == true) {
        $val_before = $val;
        for ($i = 0; $i < sizeof($ra); $i++) {
            $pattern = '/';
            for ($j = 0; $j < strlen($ra[$i]); $j++) {
                if ($j > 0) {
                    $pattern .= '(';
                    $pattern .= '(&#[x|X]0{0,8}([9][a][b]);?)?';
                    $pattern .= '|(&#0{0,8}([9][10][13]);?)?';
                    $pattern .= ')?';
                }
                $pattern .= $ra[$i][$j];
            }
            $pattern     .= '/i';
            $replacement = substr($ra[$i], 0, 2) . '<x>' . substr(
                    $ra[$i], 2
                ); // add in <> to nerf the tag
            $val         = preg_replace(
                $pattern, $replacement, $val
            ); // filter out the hex tags
            if ($val_before == $val) {
                // no replacements were made, so exit the loop
                $found = false;
            }
        }
    }

    return $val;
}

function formatDateTimeWhere($where, $key = 'create_time', $from = 'from', $to = "to")
{
    if (!empty($where[$key])) {
        if (!empty($where[$key][$from]) && empty($where[$key][$to])) {
            $where[$key][$to] = "2088-12-31 23:59:59";
        } else if (empty($where[$key][$from]) && !empty($where[$key][$to])) {
            $where[$key][$from] = "1900-01-01 00:00:00";
        } else if (empty($where[$key][$from]) && empty($where[$key][$to])) {
            unset($where[$key]);
        }
    } else if (isset($where[$key])) {
        unset($where[$key]);
    }
    return $where;
}

/**
 * 跳转
 *
 * @param string $URL
 * @param int    $second
 */
function redirect($URL = '', $second = 0)
{
    if (!isset($URL)) {
        $URL = $_SERVER['HTTP_REFERER'];
    }

    ob_start();
    ob_end_clean();
    header(
        "Location: " . $URL, true, 302
    );
    ob_flush();
    exit;
}


