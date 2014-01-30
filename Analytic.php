<?php

/**
 * Created by PhpStorm.
 * User: andkon
 * Date: 29.01.14
 * Time: 11:03
 */
class Analytic extends analyticBase
{
    /**
     * Начальная дата отчета
     *
     * @var  DateTime
     */
    private $begin_date;

    /**
     * Конечная дата отчета
     *
     * @var  DateTime
     */
    private $end_date;

    /**
     * id сайта
     *
     * @var  int
     */
    private $site_id;

    /**
     * Массив с полями выбранного сайта
     *
     * @var  array
     */
    private $site;

    /**
     * Сортировка
     *
     * @var array
     */
    private $order = array(
        'pages',
        'asc',
    );

    /** @var bool|DateTime[] */
    private $_byDates = false;

    /**
     * Авторизация
     *
     * @return bool
     */
    static public function auth()
    {
        if (!empty($_GET['project']) && !empty($_GET['token'])) {
            $pdo = self::getSql();
            $sql = $pdo->prepare('select count(*) as count from ' . self::$tab['Site'] . ' where id=? and token=?');
            $sql->execute(array($_GET['project'], $_GET['token']));
            $sql = $sql->fetch(PDO::FETCH_ASSOC);
            if (isset($sql['count']) && $sql['count'] == "1") {
                return true;
            }
        }

        return false;
    }

    /**
     * Возвращает данные для отчета
     *
     * @param array $param
     *
     * @return array
     */
    public function getData($param = array())
    {
        $dates         = array();
        $this->site_id = $param['project'];

        if (!empty($param['up'])) {
            $sql = self::getSql()->prepare('SELECT date FROM ' . self::$tab['PositionsUpDates'] . ' ORDER BY date DESC LIMIT ' . intval($param['up']));
            $sql->execute();
            $this->_byDates = $sql->fetchAll(PDO::FETCH_ASSOC);
            if (count($this->_byDates)) {
                foreach ($this->_byDates as $key => $row) {
                    $dates[]              = new DateTime($row['date']);
                    $this->_byDates[$key] = '"' . $row['date'] . '"';
                }
            }
        } else if (!empty($param['check'])) {
            $sql = '
                SELECT * FROM (
                SELECT DISTINCT p.checkdate FROM ' . self::$tab['Positions'] . ' p
                JOIN site_pages sp ON p.page_id=sp.id
                JOIN site_block sb ON sp.block_id=sb.id
                WHERE sb.site_id = ?
                ORDER BY p.checkdate DESC
                LIMIT ' . $param['check'] . '
                ) AS t ORDER BY checkdate asc
            ';
            $sql = self::getSql()->prepare($sql);
            $sql->execute(array($this->site_id));
            $this->_byDates = $sql->fetchAll();
            if (count($this->_byDates)) {
                foreach ($this->_byDates as $key => $row) {
                    $dates[]              = new DateTime($row['checkdate']);
                    $this->_byDates[$key] = '"' . $row['checkdate'] . '"';
                }
            } else {
                $this->_byDates = true;
            }
        } else if (empty($param['period'])) {
            $this->end_date   = new DateTime();
            $this->begin_date = new DateTime();
            $this->begin_date->sub(new DateInterval('P7D'));
        } else {
            $m = array();
            preg_match_all('/\d{1,2}.\d{1,2}.\d{4}/', $param['period'], $m);
            if (count($m[0]) != 2) {
                return Array();
            }

            $this->begin_date = new DateTime($m[0][0]);
            $this->end_date   = new DateTime($m[0][1]);
            if ($param['per_sel'] == 'period') {
                $this->end_date->add(new DateInterval('P1D'));
            }
        }

        if (!empty($param['order']) && in_array($param['order'], array('pages', 'pos'))) {
            $this->order[0] = $param['order'];
            $this->order[1] = ($param['order_dir'] == 1) ? 'desc' : 'asc';
            if ($this->order[0] == 'pos') {
                $this->order[2] = new DateTime($param['order_day']);
                $this->order[2] = ($this->order[2]) ? $this->order[2]->format('Y-m-d') : false;
            }
        }

        $sql = self::getSql()->prepare('select * from ' . self::$tab['Site'] . ' where id=?');
        $sql->execute(array($this->site_id));
        $this->site = $sql->fetch(PDO::FETCH_ASSOC);
        if (!$this->_byDates) {
            /** @var DateTime[] $period */
            $period = new DatePeriod($this->begin_date, new DateInterval('P1D'), $this->end_date);
            foreach ($period as $date) {
                $dates[] = $date;
            }
        }

        $blocks = $this->_getBlock();
        if (!isset($dates[0])) {
            $dates[] = new DateTime();
        }

        return array(
            'blocks' => $blocks,
            'dates'  => $dates,
            'site'   => $this->site,
        );
    }

    /**
     * Возвращает блоки с системами, страницами и позициями (block[systems => [pages => position=>...]])
     *
     * @return array
     */
    private function _getBlock()
    {
        $sql = 'select * from ' . self::$tab['SiteBlock'] . ' where site_id = ?';
        $sql = self::getSql()->prepare($sql);
        $sql->execute(array($this->site_id));
        $blocks = $sql->fetchAll(PDO::FETCH_ASSOC);
        foreach ($blocks as $key => $block) {
            $block['systems'] = $this->_getSystem($block['id']);
            $blocks[$key]     = $block;
        }

        return $blocks;
    }

    /**
     * Возврашает поисковые системы для блока (systems => [pages => positions=>...])
     *
     * @param int $block_id
     *
     * @return array
     */
    private function _getSystem($block_id)
    {
        $pdo = self::getSql();
        $sql = '
            SELECT ss.* FROM ' . self::$tab['SearchSystems'] . ' ss
            JOIN ' . self::$tab['SiteBlockSystems'] . ' sbs ON sbs.system_id=ss.id
            WHERE sbs.block_id=?
        ';
        $sql = $pdo->prepare($sql);
        $sql->execute(array($block_id));
        $systems = $sql->fetchAll(PDO::FETCH_ASSOC);
        foreach ($systems as $key => $system) {
            $system['pages'] = $this->_getPages($block_id, $system['id']);
            $system['top']   = $this->_getTop($system['pages']);
            $systems[$key]   = $system;
        }

        return $systems;
    }

    /**
     * Возвращает страницы входящие в блок для поисковой системы
     *
     * @param int $block_id
     * @param int $sys_id
     *
     * @return array
     */
    private function _getPages($block_id, $sys_id)
    {
        if ($this->order[0] == 'pos') {
            $sql = '
            SELECT sp.*, ss.`name` AS sys, ss.id AS sys_id, if(p.position, p.position, 1000) AS sort
            FROM ' . self::$tab['SitePages'] . ' sp
            JOIN ' . self::$tab['SiteBlockSystems'] . ' sbs ON sbs.block_id=sp.block_id
            JOIN ' . self::$tab['SearchSystems'] . ' ss ON sbs.system_id=ss.id
            LEFT JOIN ' . self::$tab['Positions'] . ' p ON p.page_id=sp.id AND p.system_id=ss.id
            ';
            if ($this->order[2]) {
                $sql .= ' and p.checkdate = "' . $this->order[2] . '" ';
            }

            $order = ' order by sort ' . $this->order[1];
        } else {
            $sql = '
            SELECT sp.*, ss.`name` AS sys, ss.id AS sys_id FROM ' . self::$tab['SitePages'] . ' sp
            JOIN ' . self::$tab['SiteBlockSystems'] . ' sbs ON sbs.block_id=sp.block_id
            JOIN ' . self::$tab['SearchSystems'] . ' ss ON sbs.system_id=ss.id
            ';
            if ($this->order[0] == 'pages') {
                $order = ' order by sp.name ' . $this->order[1];
            }
        }

        $sql .= ' WHERE sp.block_id=? AND ss.id=?';
        $sql .= $order;
        $sql = self::getSql()->prepare($sql);
        $sql->execute(array($block_id, $sys_id));
        $pages = array();
        while ($page = $sql->fetch(PDO::FETCH_ASSOC)) {
            $page['positions']  = array();
            $pages[$page['id']] = $page;
        }

        $pages = $this->_getPositions($pages, $sys_id);

        return $pages;
    }

    /**
     * Возвращает позиции для страницы по системе
     *
     * @param array $pages
     * @param int   $sys_id
     *
     * @return array
     */
    private function _getPositions($pages, $sys_id)
    {
        if (!count($pages)) {
            return $pages;
        }

        $sql = '
            SELECT *, if((@diff:=position-prev_position)>0,
                if(prev_position=0, "", CONCAT("+", @diff)),
                if(@diff=0,"" ,@diff)
            ) AS diff FROM (
                SELECT p.id, p.page_id, p.system_id, p.position, p.checkdate, p.link, pp.position as prev_position
                FROM ' . self::$tab['Positions'] . ' p
                left join ' . self::$tab['Positions'] . ' pp on pp.page_id=p.page_id and pp.system_id=p.system_id
                    and pp.checkdate BETWEEN DATE_SUB(p.checkdate,INTERVAL 1 MONTH) and DATE_SUB(p.checkdate, INTERVAL 1 DAY)
                WHERE p.page_id IN (' . implode(', ', array_keys($pages)) . ') AND p.system_id = :sys_id
        ';


        if (!$this->_byDates) {
            $sql .= '
                AND p.checkdate BETWEEN "' . $this->begin_date->format('Y-m-d') . '" AND "' . $this->end_date->format('Y-m-d') . '"
            ';
        } else {
            $sql .= '
                AND p.checkdate IN (' . implode(', ', $this->_byDates) . ')
            ';
        }

        $sql .= '
            ORDER BY pp.checkdate desc
            ) as t
            GROUP BY id
        ';

        $sql = self::getSql()->prepare($sql);
        $sql->execute(
            array(
                ':sys_id' => $sys_id,
            )
        );
        $data = $row = $sql->fetchAll(PDO::FETCH_ASSOC);
        foreach ($data as $row) {
            $pages[$row['page_id']]['positions'][$row['checkdate']] = $row;
        }

        return $pages;
    }

    /**
     * Возвращает массив с топ-10 топ-50 топ-100
     *
     * @param array $pages
     *
     * @return array
     */
    private function _getTop($pages)
    {
        $top = array();
        foreach ($pages as $page) {
            if (!count($page['positions'])) {
                continue;
            }

            foreach ($page['positions'] as $date => $position) {
                if (!array_key_exists($date, $top)) {
                    $top[$date] = array(10 => 0, 50 => 0, 100 => 0);
                }

                if (intval($position['position']) <= 10) {
                    $top[$date][10]++;
                } else if (intval($position['position']) <= 50) {
                    $top[$date][50]++;
                } else if (intval($position['position']) <= 100) {
                    $top[$date][100]++;
                }
            }
        }

        return $top;
    }

    public function getCsv($arResult)
    {
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

        $csv = array();
        foreach ($arResult['blocks'] as $block) :
            $csv[] = array('', $block['name']);
            foreach ($block['systems'] as $system) :
                $csv[] = array('', $system['name']);
                $tmp   = array('№', $months[$arResult['dates'][0]->format('n')]);
                foreach ($arResult['dates'] as $date) :
                    $tmp[] = $date->format('d.m.Y');
                endforeach; // dates

                $csv[] = $tmp;
                $i     = 1;
                foreach ($system['pages'] as $page) :
                    $tmp   = array();
                    $tmp[] = $i;
                    $tmp[] = $page['name'];

                    foreach ($arResult['dates'] as $date) :
                        $tmp[] = (!empty($page['positions'][$date->format('Y-m-d')]['position'])) ? $page['positions'][$date->format('Y-m-d')]['position'] : '-';
                    endforeach; // position.dates

                    $i++;
                    $csv[] = $tmp;
                endforeach; // pages

                $top_row = array(10, 50, 100);
                foreach ($top_row as $top) :
                    $tmp   = array();
                    $tmp[] = 'ТОП-' . $top;
                    foreach ($arResult['dates'] as $date) :
                        $tmp[] = $system['top'][$date->format("Y-m-d")][$top];
                    endforeach; // top.date
                    $csv[] = $tmp;
                endforeach; // top_row
            endforeach; // system
        endforeach; // blocks

        $file = fopen('tmp.csv', 'w');
        foreach ($csv as $line) {
            $this->_csvPutStr($file, $line);
        }

        header("Content-type: application/csv");
        header("Content-Disposition: attachment; filename=report.csv");
        echo file_get_contents('tmp.csv');
        unlink('tmp.csv');

        exit;
    }

    private function _csvPutStr($fh, $arr)
    {
        if (!is_array($arr)) {
            $arr = array($arr);
        }

        foreach ($arr as $key => $str) {
            $arr[$key] = mb_convert_encoding($str, 'windows-1251');
        }

        fputcsv($fh, $arr, ';');
    }
}