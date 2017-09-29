<?php
if (!defined('JET_ROOT_DIR')) {
    define('JET_ROOT_DIR', dirname(__FILE__) . DIRECTORY_SEPARATOR . '..');
}

if (!defined('JET_LIB_DIR')) {
    define('JET_LIB_DIR', dirname(__FILE__));
}

require_once(JET_LIB_DIR . DIRECTORY_SEPARATOR . 'JetUtil.class.php');
require_once(JET_LIB_DIR . DIRECTORY_SEPARATOR . 'Jet.class.php');
require_once(JET_LIB_DIR . DIRECTORY_SEPARATOR . 'JetAnalyzer.class.php');

/**
 * Jet类封装，单实例
 * @class
 */
class Jet_Singleton {
    private static $_instance = NULL;
    private static $_opt = NULL;

    /**
     * 静态工厂方法，返还此类的唯一实例， 也可以自己new，维护实例
     */
    public static function getInstance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new Jet(self::$_opt);
        }
        return self::$_instance;
    }

    /**
     * 默认的loader函数，用于找到模块ID的依赖关系图，在startPage传入moduleMap配置时，才会注册该loader
     */
    public static function defaultLoader($ctx) {

    }

    /**
     * 初始化Jet，并传入配置项， 必须调用该方式，才能执行其它方法
     * @param $id {string}
     * @param $phpFilePath {string} 编译后的php文件路径
     */
    public static function startPage($opt = array()) {
        // todo，这个moduleMap怎么存储，存储到哪，谁来读取
        $defaultOpt = array(
            'depHost' => 'http://jet.baidu.com', // curl请求地址，不能使用外网域名，在线上机器请求不到
            'depPath' => '/deps',
            'requestType' => 'ral',
            'ralName' => 'jet',
            'logid' => '',
            'apcCacheTime' => 10, // apc缓存时间 600 s  10min
            'updateInterval' => 10, // 包更新的间隔 300 s  5min
            'logid' => '',
            'jetMapDir' => JET_ROOT_DIR . DIRECTORY_SEPARATOR . 'jetmap'
        );
        self::$_opt = array_merge($defaultOpt, $opt);

        // 有moduleMap的话，默认注册一个loader
        self::registerLoader('Jet_Singleton::defaultLoader');
    }

    /**
     * 增加一份依赖关系图，用于分析指定模块的依赖关系
     * @param $addPackInfos {Array} 依赖关系数组
     */
    public static function addPackages($addPackInfos) {
        Jet_Analyzer::addPackages($addPackInfos);
    }

    /**
     * 注册一个Jet的loader，用于处理模块ID，默认情况，会注册一个默认loader
     * @param $callback {callable} 处理模块Id的函数
     */
    public static function registerLoader(callable $callback) {
        Jet_Analyzer::registerLoader($callback);
    }

    /**
     * 增加一个id的依赖关系
     * @param $id {string} js模块ID
     */
    public static function addDep($id) {
        $_instance = self::getInstance();
        $_instance->addDep($id);
    }

    /**
     * 输出当前所有ID依赖关系，同时重置当前实例的依赖关系为空
     * @return 依赖关系数组
     */
    public static function flushDeps() {
        $_instance = self::getInstance();
        return $_instance->flushDeps();
    }

    /**
     * 输出当前依赖关系
     * @return 依赖关系数组
     */
    public static function getDeps() {
        $_instance = self::getInstance();
        return $_instance->getDeps();
    }

    /**
     * 终止Jet处理，清空实例
     */
    public static function endPage() {
        self::$_instance = NULL;
    }
}
