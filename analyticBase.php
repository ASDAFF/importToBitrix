<?php
date_default_timezone_set('Europe/Moscow');

/**
 * Created by PhpStorm.
 * User: andkon
 * Date: 23.01.14
 * Time: 15:43
 */
class analyticBase
{
    /** @var PDO */
    static private $sql;

    /**
     * Именя таблиц, для того что можно было добавить префикс или переименовать
     *
     * @var array
     */
    static protected $tab = array(
        'Site'             => 'site',
        'SiteBlock'        => 'site_block',
        'SiteBlockSystems' => 'site_block_systems',
        'SitePages'        => 'site_pages',
        'Positions'        => 'positions',
        'PositionsUpDates' => 'positions_up_dates',
        'SearchSystems'    => 'search_systems',
    );

    /**
     * Функция для вывода названия компании
     *
     * В шаблон вставляется:
     * <pre>
     * $APPLICATION->IncludeComponent("bitrix:main.include", "", Array(
     *      "AREA_FILE_SHOW"      => "file",
     *      "PATH"                => '/bitrix/components/md/analytic/companyName.php',
     *      "AREA_FILE_SUFFIX"    => "inc",
     *      "AREA_FILE_RECURSIVE" => "Y",
     *      "EDIT_TEMPLATE"       => "standard.php"
     *      )
     * );
     * </pre>
     *
     * @return string
     */
    public static function getCompanyName()
    {
        $name = '';
        if (!empty($_GET['project'])) {
            $sql = 'select domain from ' . self::$tab['Site'] . ' where id = ?';
            $sql = self::getSql()->prepare($sql);
            $sql->execute(array($_GET['project']));
            $name = $sql->fetchColumn();
        }

        return $name;
    }

    /**
     * Возвращает класс подключения к БД
     *
     * @throws Exception
     * @return PDO
     */
    protected function getSql()
    {
        if (!self::$sql) {
            global $DBHost, $DBName, $DBLogin, $DBPassword;
            /**
             * @var $DBName     string
             * @var $DBHost     string
             * @var $DBLogin    string
             * @var $DBPassword string
             */
            if (isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] == '127.0.0.1') {
                $DBName     = 'test';
                $DBHost     = 'localhost';
                $DBLogin    = 'root';
                $DBPassword = '123';
            }

            $dsn = 'mysql:dbname=' . $DBName . ';host=' . $DBHost;
            try {
                self::$sql = new PDO($dsn, $DBLogin, $DBPassword);
            } catch (PDOException $e) {
                echo 'Connection failed: ' . $e->getMessage();

                throw new Exception('Error DB Connect');
            }

            $sql = self::$sql->prepare('SET NAMES utf8');
            $sql->execute();
        }

        return self::$sql;
    }
} 