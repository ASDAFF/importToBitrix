<?php

/**
 * Created by PhpStorm.
 * User: andkon
 * Date: 29.01.14
 * Time: 11:07
 */
class Import extends analyticBase
{
    /**
     * Ключ для авторизации при импорте с другово хоста
     *
     * @var string
     */
    static private $token = '111';

    /**
     * Возвращает md5 токена
     *
     * @param string $token
     *
     * @return bool
     */
    static public function checkToken($token)
    {
        return ($token == md5(self::$token));
    }

    /**
     * Возвращает последнюю дату обновления таблицы
     *
     * @param array $param
     *
     * @throws Exception
     * @return string
     */
    public function getLastUpdate($param)
    {
        if (empty($param['table'])) {
            throw new Exception('table name is empty');
        }

        $sql = self::getSql();
        $sql = $sql->query('select * from ' . self::$tab[$param['table']] . ' order by updated desc limit 1');
        $sql->execute();
        if (!$sql->rowCount()) {
            return '';
        }

        return $result = $sql->fetch(PDO::FETCH_OBJ)->updated;
    }

    /**
     * Запускает заливку дампа. Возвращает количество обработанных строк
     *
     * @param array $param
     *
     * @throws Exception
     * @return int
     */
    public function execDump($param = array())
    {
        $file = 'dump.json';
        if (!file_exists($file)) {
            throw new Exception('file ' . $file . ' not found');
        }

        $data  = json_decode(file_get_contents($file), true);
        $i     = 0;
        $total = 0;
        foreach ($data as $class => $rows) {
            $total += count($rows);
            $i += $this->insertData(self::$tab[$class], $rows);
        }

        if ($i == $total) {
            unlink('dump.json');
        }

        return $i;
    }

    /**
     * Чистим параметры что б небыло иньекций
     *
     * @param array $param
     *
     * @return array
     */
    public function stripParam($param)
    {
        $result = array();

        foreach ($param as $key => $val) {
            $key          = htmlentities($key);
            $val          = htmlentities($val);
            $result[$key] = $val;
        }

        return $result;
    }

    /**
     * Записывает данные в БД, если id уже есть то обновляет инаще вставляет новую запись
     *
     * @param string $table
     * @param array  $rows
     *
     * @return bool|int
     */
    protected function insertData($table, $rows)
    {
        if (!count($rows)) {
            return false;
        }

        $fields = $this->_getFields($table);
        $diff   = array_diff(array_keys($rows[0]), $fields);
        $i      = 0;
        foreach ($rows as $row) {
            foreach ($diff as $field) {
                unset($row[$field]);
            }

            $updated        = str_replace(array('0000-00-00 00:00:00'), '', $row['updated']);
            $row['updated'] = (empty($updated)) ? $row['created'] : $updated;
            $sql            = "insert into $table (`" . implode('`, `', array_keys($row)) . '`)';
            $sql .= " values ('" . implode("', '", $row) . "')";
            $sql .= ' on duplicate key update ';
            unset($row['id']);
            $tmp = array();
            foreach ($row as $field => $val) {
                $tmp[] = '`' . $field . '`' . '="' . $val . '"';
            }

            $tmp = implode(', ', $tmp);
            $sql .= $tmp;

            $sql = self::getSql()->prepare($sql);
            if ($sql->execute()) {
                $i++;
            } else {
                echo 'PDO Error: ' . $tmp = $sql->errorCode() . ' Query: ' . $sql->queryString . PHP_EOL;
            }
        }

        return $i;
    }

    /**
     * Возвращает поля таблицы
     *
     * @param string $table
     *
     * @return array
     */
    private function _getFields($table)
    {
        $res = self::getSql()->prepare('SHOW COLUMNS FROM ' . $table);
        $res->execute();
        $fields = array();
        while ($field = $res->fetchColumn()) {
            $fields[] = $field;
        }

        return $fields;
    }
}