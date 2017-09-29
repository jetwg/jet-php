<?php
if (!defined('JET_ROOT_DIR')) {
    define('JET_ROOT_DIR', dirname(__FILE__) . DIRECTORY_SEPARATOR . '..');
}

if (!defined('JET_LIB_DIR')) {
    define('JET_LIB_DIR', dirname(__FILE__));
}

/**
 * Class Jet
 * @Class
 */
class Jet_Util {
    private static $_log = array();
    private static $_warning = array();
    private static $_notice = array();

    /**
     * Apc缓存-设置缓存
     * 设置缓存key，value和缓存时间
     * @param  string $key   KEY值
     * @param  string $value 值
     * @param  string $time  缓存时间。单位s,  120s， 2分钟
     */
    public static function setApcCache($key, $value, $ttl) {
        if ($time == 0) $time = null; // 缓存时间为null情况下永久缓存
        $res = false;
        if (function_exists('apc_store')) {
            // 缓存时间为null情况下永久缓存
            $res = apc_store($key, $value, $ttl);
        }
        else {
            // throw new Exception("no apc_store");
        }
        return $res;
    }

    /**
     * Apc缓存-获取缓存
     * 通过KEY获取缓存数据
     * @param  string $key   KEY值
     */
    public static function getApcCache($key) {
        $res = false;
        if (function_exists('apc_fetch')) {
            $res = apc_fetch($key);
        }
        else {
            // throw new Exception("no apc_fetch");
        }
        return $res;
    }

    public static function addLog($key, $value='') {
        array_push(self::$_log, array($key, $value));
    }

    public static function flushLog() {
        $ret = self::$_log;
        self::$_log = array();
        return $ret;
    }

    public static function addNotice($key) {
        array_push(self::$_notice, $key);
    }

    public static function flushNotice() {
        $ret = self::$_notice;
        self::$_notice = array();
        return $ret;
    }


    /**
     * Apc缓存-获取缓存
     * 通过KEY获取缓存数据
     * @param  string $key   KEY值
     */
    public static function mergePack($packInfos, $addPackInfos) {
        if (empty($packInfos)) {
            $packInfos = array();
        }

        foreach ($addPackInfos as $thePackName => $thePackInfo) {
            if (!isset($packInfos[$thePackName])) {
                $packInfos[$thePackName] = array();
            }
            $packInfos[$thePackName] = array_merge($packInfos[$thePackName], $thePackInfo);
        }
        return $packInfos;
    }

    /**
     * curl请求
     * @param  string $key   KEY值
     */
    public static function curlRequest($param) {
        $url = $param['host'] . $param['path'] . '?' . http_build_query($param['query']);

        $cookie = '';
        // 不一定存在，需要判断
        if (isset($_SERVER['HTTP_COOKIE'])) {
            $cookie = $_SERVER['HTTP_COOKIE'];
        }

        // 设置选项，包括URL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // 返回字符串
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_COOKIE, $cookie);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, 1500); // 设置1.5s

        // 执行并获取HTML文档内容
        $output = curl_exec($ch);
        curl_close($ch); // 释放curl句柄
        if (!empty($output)) {
            return json_decode($output, true);
        }
        return false;
    }

    /**
     * curl请求
     * @param  string $key   KEY值
     */
    public static function request($param) {

        $arr = array_merge(array(
            "host" => "",
            "path" => "",
            "ralName" => "",
            "method"=> "get",
            "requestType" => "ral",
            "query" => array(),
            "logid" => '',
        ), $param);
        $arr['query']['requestType'] = $arr['requestType'];

        // 不存在ral，就只能用curl
        if (!function_exists('ral_set_pathinfo')) {
            $arr['requestType'] = 'curl';
        }

        // 设置选项，包括URL
        if ($arr['requestType'] === 'ral') {
            return self::ralRequest($arr);
        }
        return self::curlRequest($arr);
    }

    /**
     * ralRequest: RAL 资源访问一站式接口，与后端进行交互,支持负载均衡及多种交互协议与打包协议
     * @param $param
     * @return array
     */
    public static function ralRequest($param) {
        $referer = '';
        // 不一定存在，需要判断
        if (isset($_SERVER['HTTP_REFERER'])) {
            $referer = $_SERVER['HTTP_REFERER'];
        }

        $cookie = '';
        // 不一定存在，需要判断
        if (isset($_SERVER['HTTP_COOKIE'])) {
            $cookie = $_SERVER['HTTP_COOKIE'];
        }

        $header = array(
            "Content-Type" => "application/json",
            'Referer' => $referer,
            'Cookie' => $cookie,
            'User-Agent' => $_SERVER['HTTP_USER_AGENT'],
        );

        ral_set_pathinfo($param['path']);
        ral_set_querystring(http_build_query($param['query']));
        if ($param['logid']) {
            ral_set_logid($param['logid']);
        }

        // 1: ral配置名
        // 3: payload: 向后端发送的数据,根据配置的打包协议不同，将对输入数据做对应格式处理(详见下面注释部分)
        // 4: extra: 可以是long型的负载均衡key 或array类型控制信息(version >= 1.1.0)
        // 5: header: 指定请求数据的header
        $ret = ral($param['ralName'], $param['method'], array(), rand(), $header); // json

        $errorNo = ral_get_errno();
        if ($errorNo != 0){
            $ret = false;
        }

        if (is_string($ret)) {
            $ret = json_decode($ret, true);
        }

        return $ret;
    }
}
