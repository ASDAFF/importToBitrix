<? if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die(); ?>
<?
/**
 * @var Array      $arResult
 * @var DateTime[] $arResult ['dates']
 * @var CMain      $APPLICATION
 */
?>

<?
$APPLICATION->SetAdditionalCSS('/bitrix/components/md/analytic/css/analytic.css');
$APPLICATION->SetAdditionalCSS('http://code.jquery.com/ui/1.10.4/themes/smoothness/jquery-ui.css');
$APPLICATION->AddHeadScript('http://ajax.googleapis.com/ajax/libs/jquery/1.9.1/jquery.min.js');
$APPLICATION->AddHeadScript('http://code.jquery.com/ui/1.10.4/jquery-ui.js');
$APPLICATION->AddHeadScript('http://jquery-ui.googlecode.com/svn/tags/1.8.17/ui/i18n/jquery.ui.datepicker-ru.js');
$APPLICATION->AddHeadScript('/bitrix/components/md/analytic/analytic.js');

$months = array(
    1  => 'Январь',
    2  => 'Февраль',
    3  => 'Март',
    4  => 'Апрель',
    5  => 'Май',
    6  => 'Июнь',
    7  => 'Июль',
    8  => 'Август',
    9  => 'Сентябрь',
    10 => 'Октябрь',
    11 => 'Ноябрь',
    12 => 'Декабрь',
);
if (!$arResult['auth']) {
    echo GetMessage('SEO_AUTH_FAIL');
} else {
?>
<div id="analytic">
    Отчет за:
    <select name="period" class="select" id="Period" onchange="setSubmit(this);">
        <optgroup label="Отчеты по дням:">
            <option value="7">последние 7 дней</option>
            <option value="30">последние 30 дней</option>
            <option value="month">текущий месяц</option>
            <option value="-1">прошедший месяц</option>
        </optgroup>
        <optgroup label="Отчеты за последние апдейты:">
            <option value="up">последний 1 апдейт</option>
            <option value="up10">последние 10 апдейтов</option>
            <option value="up30">последние 30 апдейтов</option>
        </optgroup>
        <optgroup label="Отчеты за произвольный период:">
            <option value="period">произвольный период</option>
        </optgroup>
        <optgroup label="Отчеты за последние проверки:">
            <option value="check" selected="selected">
                последние 10 проверок
            </option>
        </optgroup>
    </select>

    <div id="fields_period" style="display: none;">
        <input type="text" id="begin"> - <input type="text" id="end">
        <button id="send" type="button">>>></button>
    </div>
    <div class="top_checkbox">
        <b>Подсветить:</b>
        <label><input type="checkbox" value="1">&nbsp;ТОП 10</label>
        <label><input type="checkbox" value="2">&nbsp;ТОП 50</label>
        <label><input type="checkbox" value="3">&nbsp;ТОП 51 и более</label>
        <label><input type="checkbox" value="4">&nbsp;Попал/выпал ТОП 10</label>
    </div>
    <?
    $link = $_SERVER['SCRIPT_NAME'] . '?';
    foreach ($_GET as $key => $val) {
        if (!in_array($key, array('order', 'order_day', 'order_dir'))) {
            $link .= '&' . $key . '=' . $val;
        }
    }
    ?>
    <?
    foreach ($arResult['blocks'] as $block) :
        ?>
        <h2><?= $block['name'] ?></h2>
        <? foreach ($block['systems'] as $system) : ?>
        <h3><?= $system['name'] ?></h3>

        <div class="seoReport">
            <table class="table">
                <tr>
                    <th>№</th>
                    <th>
                        <?= $months[$arResult['dates'][0]->format('n')] ?>
                        <a href="<?= $link ?>&order=pages&order_dir=0" class="sortAsc"></a>
                        <a href="<?= $link ?>&order=pages&order_dir=1" class="sortDesc"></a>
                    </th>
                    <? foreach ($arResult['dates'] as $date) : ?>
                        <th>
                            <? /** @var DateTime $date */ ?>
                            <?= $date->format('d.m.Y') ?>
                            <? $tmpLink = $link . '&order=pos&order_day=' . $date->format('Y-m-d'); ?>
                            <nobr>
                                <a href="<?= $tmpLink ?>&order_dir=0" class="sortAsc"></a>
                                <a href="<?= $tmpLink ?>&order_dir=1" class="sortDesc"></a>
                            </nobr>
                        </th>
                    <? endforeach; // dates ?>
                </tr>
                <? $i = 1; ?>
                <? foreach ($system['pages'] as $page) : ?>
                    <tr>
                        <td><?= $i; ?>.</td>
                        <td class="name">
                            <?= $page['name'] ?>
                            <a href="<?= $page['link'] ?>" target="_blank"><?= $page['linkText'] ?></a>
                        </td>
                        <? foreach ($arResult['dates'] as $date) : ?>
                            <?
                            $pos = (!empty($page['positions'][$date->format('Y-m-d')])) ? $page['positions'][$date->format('Y-m-d')]['position'] : '-';
                            if (intval($pos) <= 10 && $pos != '-') {
                                $class = 'high';
                            } else if (intval($pos) <= 50 && $pos != '-') {
                                $class = 'mid';
                            } else if (intval($pos) && $pos != '-') {
                                $class = 'low';
                            } else {
                                $class = '';
                            }

                            $prev       = ($pos != '-') ? $page['positions'][$date->format('Y-m-d')]['diff'] : '';
                            $prev_class = ($prev != '-') ? (intval($prev) < 0) ? 'upPos' : 'downPos' : '';
                            ?>
                            <td class="<?= $class; ?>"><?= $pos; ?><sup class="<?= $prev_class; ?>"><?= $prev; ?></sup>
                            </td>
                        <? endforeach; // position.dates ?>
                    </tr>
                    <? $i++; ?>
                <? endforeach; // pages ?>
                <?
                $top_row = array(10, 50, 100);
                foreach ($top_row as $top) : ?>
                    <tr class="top_<?= $top; ?>">
                        <td></td>
                        <td>ТОП-<?= $top; ?></td>
                        <? foreach ($arResult['dates'] as $date) : ?>
                            <td><?= $system['top'][$date->format("Y-m-d")][$top]; ?></td>
                        <? endforeach; // top.date ?>
                    </tr>
                <? endforeach; // top_row ?>
            </table>
        </div>
    <? endforeach; // system ?>
    <? endforeach; // blocks
    }
    ?>
</div>
<div style="width: 100%;clear: both;"></div>