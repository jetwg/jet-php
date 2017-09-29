<?php

require_once(JET_LIB_DIR . DIRECTORY_SEPARATOR . 'JetUtil.class.php');

class Jet_Analyzer {
    private static $packInfos = array();
    private static $loaders = array();

    /**
     * getPackInfos
     */
    public static function getPackInfos() {
        return self::$packInfos;
    }

    /**
     * 增加一份依赖关系图，用于分析指定模块的依赖关系
     * @param $depConf {Array} 依赖关系数组
     */
    public static function addPackages($addPackInfos) {
        if (!is_array($addPackInfos)) {
            return;
        }
        self::$packInfos = Jet_Util::mergePack(self::$packInfos, $addPackInfos);
    }

    /**
     * 增加一份依赖关系图，用于分析指定模块的依赖关系
     * @param $depConf {Array} 依赖关系数组
     */
    public static function loadPackages($ids) {
        $packNames = array();
        $lackPackNames = array();
        foreach ($ids as $key => $id) {
            $idPackName = explode('/', $id)[0];
            if (!isset(self::$packInfos[$idPackName])) {
                array_push($idPackName, $lackPackNames);
            }
        }
    }

    /**
     * 注册一个Jet的loader，用于处理模块ID，默认情况，会注册一个默认loader
     * @param $callback {callable} 处理模块Id的函数
     */
    public static function registerLoader(callable $callback) {
        if (empty($callback) || in_array($callback, self::$loaders)) {
            return;
        }
        array_push(self::$loaders, $callback);
    }

    /**
     * 运行所有的loader
     * @param $ids {Array} 要处理模块ID
     */
    public static function runLoaders($ids) {
        $ctx = array(
            'ids' => $ids
        );
        foreach (self::$loaders as $key => $loader) {
            call_user_func($loader, $ctx);
        }
    }

    /**
     * findId
     * @param $id {string} 要处理模块ID
     */
    public static function findId($id, $packName) {
        if (!empty($packName)) {
            // 内部包存在
            if (isset(self::$packInfos[$packName])) {
                $packInfo = self::$packInfos[$packName];
                // 模块存在，返回
                if (isset($packInfo['map'][$id])) {
                    return array(
                        'modInfo' => $packInfo['map'][$id],
                        'packName' => $packName
                    );
                }
            }
        }

        // 内部包没找到，找id包
        $packName = explode('/', $id)[0];
        // id包存在
        if (isset(self::$packInfos[$packName])) {
            $packInfo = self::$packInfos[$packName];
            // 模块存在，返回
            if (isset($packInfo['map'][$id])) {
                return array(
                    'modInfo' => $packInfo['map'][$id],
                    'packName' => $packName
                );
            }
            else {
                Jet_Util::addNotice('lackid-' . $id);
            }
        }
        else {
            Jet_Util::addNotice('lackid-' . $id);
        }

        // 都没找到，兜底
        return array(
            'modInfo' => array(),
            'packName' => $packName
        );
    }

    public static function existId($id, &$outDep) {
        foreach ($outDep as $key => $packInfo) {
            if (isset($packInfo['map'][$id])) {
                return true;
            }
        }
        return false;
    }

    /**
     * 分析指定模块id依赖
     * @param $id {string} 模块ID
     * @param $outDepObj {Array} 当前已找到的依赖关系，用引用方式，避免传值引用消耗
     * @return void
     */
    public static function analyzeDependency($id, $thePackName, &$outDep) {
        $res = self::findId($id, $thePackName);
        $packName = $res['packName'];
        $modInfo = $res['modInfo'];

        // 初始化
        if (!isset($outDep[$packName])) {
            $outDep[$packName] = array(
                'map' => array()
            );
        }

        // // 非数组，退出，id的直接依赖是数组形式的
        // if (empty($modInfo)) {
        //     $outDep[$packName]['map'][$id] = array();
        //     return;
        // }

        // 处理同步依赖
        if (isset($modInfo['d']) && !empty($modInfo['d'])) {
            foreach ($modInfo['d'] as $i => $nextid) {
                if (!self::existId($nextid, $outDep)) { // 该id还没加入依赖里
                    Jet_Util::addLog('analyzeid', $nextid);
                    self::analyzeDependency($nextid, $packName, $outDep);
                }
            }
        }

        // 处理异步依赖，和同步依赖一样
        if (isset($modInfo['a']) && !empty($modInfo['a'])) {
            foreach ($modInfo['a'] as $i => $nextid) {
                if (!self::existId($nextid, $outDep)) { // 该id还没加入依赖里
                    Jet_Util::addLog('analyzeid', $nextid);
                    self::analyzeDependency($nextid, $packName, $outDep);
                }
            }
        }

        $outDep[$packName]['map'][$id] = $modInfo;
    }


    /**
     * 分析并获取指定模块的依赖关系
     * @param $id {string} 模块ID
     * @return {Array}
     */
    public static function analyze($ids) {
        $outDep = array();
        foreach ($ids as $key => $id) {
            self::analyzeDependency($id, null, $outDep);
        }
        Jet_Util::addLog('analyzed', implode(',', array_keys($outDep)));
        return $outDep;
    }
}
