<?php

/**
 * Created by PhpStorm.
 * User: andkon
 * Date: 24.02.14
 * Time: 14:52
 */

class YiiCommand extends MyConsoleCommand
{
    /**
     * Таблици для импорта
     *
     * @var array
     */
    protected $tables = [];
    /**
     * Адрес где расположен принимающий файл
     *
     * @var string
     */
    private $url = '';
    /**
     * Пароль для заливки
     *
     * @var string
     */
    private $token = '';
    private $ftp_server = '';
    private $ftp_login = '';
    private $ftp_pass = '';
    /**
     * Папка в которую заливается файл
     *
     * @var string
     */
    private $ftp_dir = '/';

    private $_itemForExport = 0;

    /**
     * Присваивает пременные для фтп-соединения
     *
     * @param string $url
     * @param string $token
     * @param string $ftp_server
     * @param string $ftp_login
     * @param string $ftp_pass
     * @param string $ftp_dir
     */
    protected function setFtpParam($url, $token, $ftp_server, $ftp_login, $ftp_pass, $ftp_dir)
    {
        $this->url        = $url;
        $this->token      = $token;
        $this->ftp_server = $ftp_server;
        $this->ftp_login  = $ftp_login;
        $this->ftp_pass   = $ftp_pass;
        $this->ftp_dir    = $ftp_dir;
    }

    /**
     * Проверяет изменения в таблицах/создет файл/заливаетна сервер/запускает распаковку данных на сервере
     *
     * @return bool
     */
    public function actionIndex()
    {
        $file = $this->getDump();
        if (!$this->_itemForExport) {
            return true;
        }

        $ftp = $this->getFtp();
        if (!$file || !$ftp) {
            return false;
        }

        if (!$this->uploadFile($ftp, $file)) {
            return false;
        }

        if ($this->execDump()) {
            if (YII_DEBUG) {
                MyLog::notice(date("Y-m-d H:i:s") . ' обработано записей: ' . $this->_itemForExport);
            }

            $this->actionIndex();
        }
    }

    /**
     * Возвращает путь к файлу с дампом
     *
     * @return string|bool
     */
    public function getDump()
    {
        $this->_itemForExport = 0;
        $json                 = [];
        foreach ($this->tables as $class) {
            $lastUpdate = $this->getLastUpdate($class);
            $criteria   = new CDbCriteria();
            if ($lastUpdate) {
                $criteria->addCondition('created > TIMESTAMP("' . $lastUpdate . '")');
                $criteria->addCondition('updated > TIMESTAMP("' . $lastUpdate . '") and updated != "0000-00-00 00:00:00"', 'or');
            }

            /** @var MyActiveRecord $models */
            $models          = new $class();
            $count           = $models->count($criteria);
            $criteria->limit = 1000;
            $criteria->order = 'created, updated';
            $models          = $models->resetScope()->findAll($criteria);
            /** @var $models MyActiveRecord[] */
            if ($count) {
                MyLog::notice('Таблица ' . $class . ' записей для обновления ' . $count);
            }

            foreach ($models as $model) {
                $json[$class][] = $model->getAttributes();
                $this->_itemForExport++;
            }
        }

        $file = __DIR__ . '/../../tmp/dump.json';
        $res  = file_put_contents($file, json_encode($json));

        return ($res) ? $file : false;
    }

    /**
     * Возвращает уже залогиненый фтп
     *
     * @return bool|resource
     */
    public function getFtp()
    {
        $ftp = ftp_connect($this->ftp_server, 21);
        if (!ftp_login($ftp, $this->ftp_login, $this->ftp_pass)) {
            MyLog::warning('Не удалось соединится по фтп с сервером ' . $this->ftp_server);

            return false;
        }

        return $ftp;
    }

    /**
     * Возвращает дату посленднего обновления таблицы
     *
     * @param string $table
     *
     * @return bool|string
     */
    protected function getLastUpdate($table)
    {
        $res = $this->_request('getLastUpdate', ['table' => $table]);
        if (preg_match('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $res)) {
            return $res;
        }

        return false;
    }

    /**
     * Запускает комманду на удаленном сервере
     *
     * @param string $action
     * @param array  $data
     *
     * @return string
     */
    protected function _request($action, $data = array())
    {
        $url   = $this->url . '?action=' . $action . '&token=' . md5($this->token) . '&';
        $param = [];
        foreach ($data as $par => $val) {
            $param[] = $par . '=' . $val;
        }

        $url .= implode('&', $param);
        $res = @file_get_contents($url);

        return $res;
    }

    /**
     * Заливает вайл на удаленный сервер
     *
     * @param resource $ftp
     * @param string   $file
     *
     * @return bool
     */
    protected function uploadFile($ftp, $file)
    {
        ftp_chdir($ftp, $this->ftp_dir);
        $files = ftp_nlist($ftp, ".");
        if (in_array('dump.json', $files)) {
            if (!ftp_delete($ftp, './dump.json')) {
                MyLog::warning('Не удалось удалить файл с сервера ' . $this->ftp_server);

                return false;
            }
        }

        if (!ftp_put($ftp, 'dump.json', $file, FTP_BINARY)) {
            MyLog::warning('Не удалось загрузить файл на сервер ' . $this->ftp_server);

            return false;
        }

        return true;
    }

    /**
     * Запускает распаковку дампа на удаленном сервере
     *
     * @return bool
     */
    protected function execDump()
    {
        $res = $this->_request('execDump');
        if (!intval($res)) {
            MyLog::warning($res);

            return false;
        }

        if (intval($res) != $this->_itemForExport) {
            MyLog::warning('Для обновления было отправлено ' . $this->_itemForExport . ' записей, обновлено ' . intval($res) . ' записей');

            return false;
        }

        return true;
    }
} 