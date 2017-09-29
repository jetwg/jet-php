<?php
if (!class_exists("Jet_Singleton", false)) {
    if (!defined('JET_ROOT_DIR')) {
        define('JET_ROOT_DIR', dirname(__FILE__) . DIRECTORY_SEPARATOR . '..');
    }

    // jet核心库
    require(JET_ROOT_DIR . '/lib/JetSingleton.class.php');
}

function smarty_function_jet_add_dep($params,  Smarty_Internal_Template $template) {
    $id = $params['id'];
    if (!is_string($id)) {
        return;
    }

    $ids = explode(',', $id);

    foreach ($ids as $i => $id) {
        Jet_Singleton::addDep($id);
    }
}
