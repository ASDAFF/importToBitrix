<?if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();
$path = $_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR . 'bitrix' . DIRECTORY_SEPARATOR . 'components'
    . DIRECTORY_SEPARATOR . 'md' . DIRECTORY_SEPARATOR . 'analytic' . DIRECTORY_SEPARATOR . 'analyticBase.php';

if (file_exists($path)) {
    include_once $path;
} else {
    echo $path . ' NOT FOUND';
}

$arResult = array();
if (isset($arParams['import'])) {
    include_once __DIR__ . '/Import.php';
    if (!empty($_GET['action']) && !empty($_GET['token']) && Import::checkToken($_GET['token'])) {
        $import = new Import();
        $action = $_GET['action'];
        if (method_exists($import, $action)) {
            echo $import->$action($import->stripParam($_GET));
        }
    }
} else {
    include_once __DIR__ . '/Analytic.php';
    if (Analytic::auth()) {
        $arResult['auth'] = true;
        $analytic         = new Analytic();
        $arResult         = array_merge($arResult, $analytic->getData($_GET));
    } else {
        $arResult['auth'] = false;
    }

    $this->IncludeComponentTemplate($componentPage);
}
?>