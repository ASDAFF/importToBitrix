importToBitrix
==============

Импорт сео статистики в битрикс

в папку доступную из вне необходимо положить 2 файла
index.php (для просмотра отчетов)
<?
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
$APPLICATION->SetTitle("Аналитика");
?>

<?
$APPLICATION->IncludeComponent("md:analytic", "", array());
?>
<? require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php"); ?>

import.php (для синхронизации)
<?
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");
?>
<?
$APPLICATION->IncludeComponent("md:analytic", "", array('import' => true));
?>
