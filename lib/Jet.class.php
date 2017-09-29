<?php
if (!defined('JET_ROOT_DIR')) {
    define('JET_ROOT_DIR', dirname(__FILE__) . DIRECTORY_SEPARATOR . '..');
}

if (!defined('JET_LIB_DIR')) {
    define('JET_LIB_DIR', dirname(__FILE__));
}

require_once(JET_LIB_DIR . DIRECTORY_SEPARATOR . 'JetUtil.class.php');
require_once(JET_LIB_DIR . DIRECTORY_SEPARATOR . 'JetAnalyzer.class.php');

/**
 * Class Jet
 * @Class
 */
class Jet {
    private $deps = array();
    private $loaders = array();
    private $opt = array();
    private $ids = array();

    function __construct($opt = array()) {
        $this->opt = $opt;

        // 创建
        if (isset($this->opt['jetMapDir']) && !is_dir($this->opt['jetMapDir'])) {
            mkdir($this->opt['jetMapDir'], 0777, true);
        }
    }

    /**
     * 增加一个id的依赖关系
     * @param $id {string} js模块ID
     */
    public function addDep($id) {
        if (empty($id)) {
            return;
        }
        array_push($this->ids, $id);
        // $packInfos = $this->getCache($id);
        // if (!empty($packInfos)) {
        //
        //     // $outDepObj = Jet_Analyzer::analyze($id);
        //     // 同步依赖
        //     $this->deps = array_merge($this->deps, $outDepObj);
        //
        //     // 异步依赖作为新的入口
        //     foreach ($packInfos as $i => $asyncId) {
        //         $this->addEntry($asyncId);
        //     }
        // }
    }

    public function setPackCache($cache) {
        // 防止： 做为一个锁，防止其它进程去请求相同id
        // 缓存到apc
        Jet_Util::setApcCache('jetmap', $cache, $this->opt['apcCacheTime']);

        // 缓存到文件，不直接写文件，避免高并发下 多进程读写数据不一致
        $tempfile = tempnam(null, 'jetmap');
        file_put_contents($tempfile, json_encode($cache));
        // 读取文件缓存，兜底, 文件缓存是兜底，不是缓存层级关系，二者取其一
        $absPath = $this->opt['jetMapDir'] . DIRECTORY_SEPARATOR . 'jetmap.cache.json';
        if (file_exists($absPath)) {
            unlink($absPath);
        }
        rename($tempfile, $absPath);
    }

    public function getPackCache() {
        $cache = Jet_Util::getApcCache('jetmap');

        // 读取文件缓存，兜底, 文件缓存是兜底，不是缓存层级关系，二者取其一
        $absPath = $this->opt['jetMapDir'] . DIRECTORY_SEPARATOR . 'jetmap.cache.json';
        $time = filemtime($absPath); // 单位 s

        if ($cache === false) { // apc还没有做缓存，就从文件缓存里读
            // 读取文件缓存，兜底, 文件缓存是兜底，不是缓存层级关系，二者取其一
            $absPath = $this->opt['jetMapDir'] . DIRECTORY_SEPARATOR . 'jetmap.cache.json';
            if (file_exists($absPath)) {
                $time = filemtime($absPath); // 单位 s
                // 文件还在有效期内， 都转成ms 来计算
                if (time() - $time <= $this->opt['updateInterval']) { // time() 单位是 s
                    $cont = file_get_contents($absPath);
                    $cache = json_decode($cont, true); // 解析成数组，而不是 对象
                    Jet_Util::addNotice('cacheFile');
                }
                else {
                    Jet_Util::addNotice('cacheFileExpire');
                }
            }
            else {
                Jet_Util::addNotice('noCacheFile');
            }
        }
        else {
            Jet_Util::addNotice('cacheApc');
        }
        return $cache;
    }

    public function setLoadingPack($loadingPack) {
        return Jet_Util::setApcCache('loadingPackName', $loadingPack, $this->opt['apcCacheTime']);
    }

    public function getLoadingPack() {
        $res = Jet_Util::getApcCache('loadingPackName');
        if (empty($res)) {
            $res = array();
        }
        return $res;
    }


    public function resetApcPackExpire() {
        Jet_Util::setApcCache('lastLoadTime', time(), $this->opt['apcCacheTime']); // 保存的单位是 s
    }

    public function isApcPackExpire() {
        $lastLoadTime = Jet_Util::getApcCache('lastLoadTime');
        Jet_Util::addLog('lastLoadTime', date("Y-m-d H:i:s",$lastLoadTime));
        Jet_Util::addLog('curTime', date("Y-m-d H:i:s"));
        if (empty($lastLoadTime)) {
            return true;
        }
        // 时间超过5min，认为过期， time() 是 秒
        if (time() - $lastLoadTime >= $this->opt['updateInterval']) { // 该更新了
            return true;
        }
        return false;
    }

    // 获取本地包里所有依赖包中里缺少的依赖包，优先用本地包，但是本地包会依赖公共包，如果缺失，需要去加载
    public function getLackedDepPackages($packInfos) {
        $packs = array();
        foreach ($packInfos as $packName => $packInfo) {
            // 在包里判断
            $packMap = $packInfo['map'];
            foreach ($packMap as $moduleId => $moduleInfo) {
                foreach ($moduleInfo['d'] as $key => $depId) {
                    // 自己的包里没有，就认为是公共包，
                    if (!isset($packMap[$depId])) {
                        $pack = explode('/', $depId)[0];
                        array_push($packs, $pack);
                    }
                }
                foreach ($moduleInfo['a'] as $key => $depId) {
                    // 自己的包里没有，就认为是公共包，
                    if (!isset($packMap[$depId])) {
                        $pack = explode('/', $depId)[0];
                        array_push($packs, $pack);
                    }
                }
            }
        }
        $packs = array_unique($packs); // 去重
        return $packs;
    }

    /**
     * loadRemotePackages, 有锁机制，防止多个进程请求同一个包
     * @param $id {string} js模块ID
     */
    public function loadRemotePackages($needLoadPacks) {
        $retPackInfos = array();

        // 缺少包，就去请求
        if (count($needLoadPacks)) {
            // 标记为 加载中的，防止： 加一个锁，防止其它进程去请求相同id
            $loadingPackNames = $this->getLoadingPack(); //（加载中的） // 防止： 一台机器，同时多个进程去请求相同的id
            foreach ($needLoadPacks as $key => $packName) {
                $loadingPackNames[$packName] = 1; // 标记为 加载中的
            }
            $this->setLoadingPack($loadingPackNames);
            Jet_Util::addNotice('load: ' . implode(',', $needLoadPacks));
            // 请求
            $res = Jet_Util::request(array(
                'requestType' => $this->opt['requestType'],
                'ralName' => $this->opt['ralName'],
                'logid' => $this->opt['logid'],
                'host' =>  $this->opt['depHost'],
                'path' => $this->opt['depPath'],
                'query' => array(
                    // 依赖模块所在包下所有模块都返回，好处就是要分析别的id，不用再请求了
                    'packs' => implode(',', $needLoadPacks),
                    'logid' => $this->opt['logid'], // 带上日志
                )
            ));
            // $res = false;

            // 成功返回，并且返回状态正确
            if (!empty($res) && !$res['status']) {
                $retPackInfos = $res['data'];
            }

            // 立即写入到内存和缓存
            if (!empty($retPackInfos)) {
                Jet_Util::addNotice('reqOk');
                Jet_Analyzer::addPackages($retPackInfos);
                $packInfos = Jet_Analyzer::getPackInfos();
                // 把内存数据保存到缓存，供其它php进程使用请求下来的包
                $this->setPackCache($packInfos);
            }
            else {
                Jet_Util::addNotice('reqFail');
            }

            // 更新过期为现在，也就是缓存5min， 即使失败也认为更新，因为一旦失败，如果进程多次去尝试的话，那么服务器压力累加得很大，可能挂掉
            $this->resetApcPackExpire();

            // 注意：需要请求的返回值保证，即使jet服务没有包信息，也需要返回空数组，否则每次用户访问都会请求jet该包信息
            // 重置id，解除正在加载的锁
            // 必须从 apc公共缓存再次读，因为请求期间可能加载中包状态有更新
            $loadingPackNames = $this->getLoadingPack();
            foreach ($needLoadPacks as $key => $packName) {
                // Todo： unset一个不存在$packName是否会报错
                unset($loadingPackNames[$packName]);
            }
            $this->setLoadingPack($loadingPackNames);
        }

        // Todo: 这里会强制延时，原因是：高并发下，为了减少请求jet，等待别的php进程获取
        // 到正在请求的id信息，
        // 缺点：会导致页面出现延迟，通过sleep方式容易导致资源占用，不利于速度和稳定性
        // 正在请求的id，等待一段时间，从缓存里读取,不管能不能读到
        // 该逻辑会经常性触发，一旦apc缓存没了，就可能有触发
        // if (count($curLoadingPackNames)) {
        //     // 等待锁的解除， 最多500ms
        //     for ($i = 0; $i < 100; $i++) {
        //         $loadingPackNames = $this->getLoadingPack();
        //         $ready = true;
        //         foreach ($curLoadingPackNames as $key => $packName) {
        //             // 一旦该包还存在在 $loadingPackNames数组里，那么说明还没加载完
        //             if (array_key_exists($packName, $loadingPackNames)) {
        //                 $ready = false;
        //             }
        //         }
        //         if (!$ready) {
        //             // usleep 单位微妙  1000 * 1 = 1ms
        //             usleep(1000 * 5); // 5ms
        //         }
        //         else {
        //             break;
        //         }
        //     }
        //
        //     // 不管锁有没有解除，加载是否成功完成了，从缓存再取出来
        //     // 从缓存里， 不需要根上面的请求回来的包合并，原因是它会写入到apc
        //     $cache = $this->getPackCache();
        //
        //     // 增加到内存，不需要增加到缓存了，因为从缓存取的
        //     foreach ($curLoadingPackNames as $key => $packName) {
        //         if (isset($cache[$packName])) {
        //             $addPackInfos = array();
        //             $addPackInfos[$packName] = $cache[$packName];
        //             Jet_Analyzer::addPackages($addPackInfos);
        //         }
        //     }
        // }
    }

    public function loadLocalPackages() {
        $dir = $this->opt['jetMapDir'];
        $loalPackInfos = array();

        if (!is_dir($dir)) {
            return $loalPackInfos;
        }

        Jet_Util::addLog('load local');
        // 获取某目录下所有文件、目录名（不包括子目录下文件、目录名）
        $handler = opendir($dir);

        if ($handler == false) { // 打开失败
            Jet_Util::addLog('open local fail');
            return $loalPackInfos;
        }

        while (($filename = readdir($handler)) !== false) {
            if (strstr($filename, '.conf.php') !== false) {
                Jet_Util::addLog('local package', $filename);
                $filepath = $dir . DIRECTORY_SEPARATOR . $filename;
                $packs = require($filepath);
                $loalPackInfos = array_merge($loalPackInfos, $packs);
            }
        }
        closedir($handler);

        // 会合并和覆盖内存
        if (!empty($loalPackInfos)) {
            Jet_Util::addLog('add local');
            Jet_Analyzer::addPackages($loalPackInfos);
        }
        else {
            Jet_Util::addWarning('empty local');
        }
        // 本地包不写入缓存
        // $this->setCache(Jet_Analyzer::getPackInfos());
        return $loalPackInfos;
    }

    public function loadCachePackages() {
        $cache = $this->getPackCache();

        // 会合并和覆盖内存
        if (!empty($cache)) {
            Jet_Analyzer::addPackages($cache);
        }
        else {
            $cache = array();
        }

        return $cache;
    }

    public function getNeedLoadPacks($ids, $cache, $localPackInfos) {
        $packInfos = Jet_Analyzer::getPackInfos(); // 缓存里有的包（已经加载好的）
        $packNames = array();
        // 本地包缺失的公共包
        $localLackedPackNames = $this->getLackedDepPackages($localPackInfos);
        Jet_Util::addLog('localLackedPackNames ', $localLackedPackNames);
        foreach ($localLackedPackNames as $key => $packName) {
            // 缓存里也没有
            if (!isset($packInfos[$packName])) {
                array_push($packNames, $packName);
            }
        }

        // 本次分析的公共包，去除本地包
        foreach ($ids as $key => $id) {
            $packName = explode('/', $id)[0];
            // 不是本地包，缓存里也没有
            if (!isset($localPackInfos[$packName]) && !isset($packInfos[$packName])) {
                array_push($packNames, $packName);
            }
        }

        // 缓存更新的公共包，去除本地包
        // 缓存是过期的数据, 就把缓存里的包放过待请求的包队列里
        if ($this->isApcPackExpire() && !empty($cache)) {
            Jet_Util::addNotice('expire');
            foreach ($cache as $packName => $packInfo) {
                // 不是本地包，这个时候就不管缓存里有没有了，因为你要更新缓存
                if (!isset($localPackInfos[$packName])) {
                    array_push($packNames, $packName);
                }
            }
        }
        $packNames = array_unique($packNames);

        $loadingPackNames = $this->getLoadingPack(); //（加载中的） // 防止： 一台机器，同时多个进程去请求相同的id
        $needLoadPacks = array(); // 指定的ids中需要加载的包 （要加载的）

        // 找到需要加载的包
        $count = 0;
        foreach ($packNames as $key => $packName) {
            if (in_array($packName, $loadingPackNames)) {  // 加载中的
                Jet_Util::addLog('requesting package ', $packName);
                $count++;
                continue;
            }
            array_push($needLoadPacks, $packName);
        }
        Jet_Util::addNotice('loadingNum: ' . $count);
        return $needLoadPacks;
    }

    /**
     * 输出当前所有ID依赖关系，同时重置当前实例的依赖关系为空
     * @return 依赖关系数组
     */
    public function analyze() {
        // 加载缓存, 写入到内存$packInfos
        $cache = $this->loadCachePackages();

        // 加载本地包（第一优先级，保持和模板一致），覆盖缓存，写入缓存，写入到内存$packInfos
        $localPackInfos = $this->loadLocalPackages();
        if (!empty($localPackInfos)) {
            Jet_Util::addNotice('localOk');
        }
        else {
            Jet_Util::addNotice('localFail');
        }

        $needLoadPacks = $this->getNeedLoadPacks($this->ids, $cache, $localPackInfos);

        // var_dump('$needLoadPacks');
        // var_dump($needLoadPacks);
        // var_dump($needLoadPacks);
        // 从jet加载 内存里没有的包(只会是公共包)，写入缓存, 写入到内存$packInfos
        $this->loadRemotePackages($needLoadPacks);

        // 从内存$packInfos 做分析
        $outDep = Jet_Analyzer::analyze($this->ids);
        return $outDep;
    }

    /**
     * 输出当前所有ID依赖关系，同时重置当前实例的依赖关系为空
     * @return 依赖关系数组
     */
    public function flushDeps() {
        $deps = $this->getDeps();
        $this->ids = array(); // 同时重置当前实例的依赖关系为空
        return $deps;
    }

    /**
     * 输出当前依赖关系
     * @return 依赖关系数组
     */
    public function getDeps() {

        // apc_store('testjet', apc_fetch('jetmap'), 10);

        // if (apc_fetch('jetmap') != false) {
        //     Jet_Util::addNotice('jetmap' . implode(',', array_keys(apc_fetch('jetmap'))));
        // }
        // else {
        //     Jet_Util::addNotice('jetmap false');
        // }
        // Jet_Util::addNotice('testjet: ' . apc_fetch('testjet'));
        // Sm_Base_AELog::addNotice('jet', implode(' | ', Jet_Util::flushNotice()));
        // return array();
        // apc_clear_cache(); // 测试用的

        $startTime = microtime(true);
        $deps = $this->analyze();
        $timeSpan = round((microtime(true) - $startTime) * 1000);

        if (apc_fetch('jetmap') != false) {
            Jet_Util::addNotice('jetmap' . implode(',', array_keys(apc_fetch('jetmap'))));
        }
        else {
            Jet_Util::addNotice('jetmap false');
        }

        Jet_Util::addNotice(json_encode(apc_fetch('lastLoadTime')));
        // Jet_Util::addNotice(Jet_Util::APC_CACHE_TIME);
        // self::addNotice(APC_CACHE_TIME);
        Jet_Util::addNotice(json_encode(apc_fetch('loadingPackName')));
        // var_dump(Jet_Util::flushLog());
        // var_dump(apc_sma_info());
        // var_dump(apc_fetch('jetmap'));
        // var_dump(apc_fetch('lastLoadTime'));
        // var_dump(apc_fetch('loadingPackName'));
        // print_r(apc_cache_info());


        // 打印到日志文件里
        if (class_exists('Sm_Base_AELog')) {
            Sm_Base_AELog::addNotice('jet', implode(' | ', Jet_Util::flushNotice()));
            Sm_Base_AELog::addNotice('jet_cost', $timeSpan); // ms
        }

        return $deps;
    }
}
