<?php

/**
 * @author Ilya Dashevsky <il.dashevsky@gmail.com>
 * @license The MIT License (MIT), http://opensource.org/licenses/MIT
 * @link https://github.com/edevelops/magic-spa-backend
 */
declare(strict_types = 1);

namespace MagicSpa;

class DbDeployer {

    private $conn;
    private $prefix;
    private $dumpDir;
    private static $ext = '.sql';

    public function __construct($host, $user, $password, $name, $prefix, $dumpDir) {
        $this->conn = mysqli_connect($host, $user, $password, $name) or die(mysqli_connect_error());
        mysqli_set_charset($this->conn, 'utf8mb4') or die(mysqli_error($this->conn));
        $this->prefix = $prefix;
        $this->dumpDir = $dumpDir;
    }

    private function makeStructureFileName($tabName) {
        return $this->makeStructureDirName() . '/' . $tabName . self::$ext;
    }

    private function makeStructureDirName() {
        return $this->dumpDir . '/structure';
    }

    private function makeDataDirName() {
        return $this->dumpDir . '/data';
    }

    private function makeDataFileName($tabName) {
        return $this->makeDataDirName() . '/' . $tabName . self::$ext;
    }

    public function export() {
        foreach ($this->getAllTableNames() as $tabName) {

            $result1 = mysqli_query($this->conn, 'SHOW CREATE TABLE `' . $tabName . '`') or die(mysqli_error($this->conn));
            //var_dump(mysqli_fetch_assoc($result));
            $createTableDump = preg_replace([
                '/\s+AUTO_INCREMENT=\d+\s+/'
                    ], [
                ' '
                    ], mysqli_fetch_assoc($result1)['Create Table']);

            $result2 = mysqli_query($this->conn, 'SELECT * FROM ' . $tabName) or die(mysqli_error($this->conn));

            $dataDump = '';
            while ($row = mysqli_fetch_assoc($result2)) {
                $valArr = [];
                foreach ($row as $fieldName => $value) {
                    $valArr[] = (($value === null) ? 'NULL' : '\'' . mysqli_real_escape_string($this->conn, $value) . '\'');
                }
                $dataDump .= 'INSERT INTO ' . $tabName . ' VALUES (' . implode(',', $valArr) . ");\n";
            }

            file_put_contents($this->makeStructureFileName($tabName), $createTableDump);
            file_put_contents($this->makeDataFileName($tabName), $dataDump);
        }
    }

    public function import() {

        mysqli_query($this->conn, 'SET FOREIGN_KEY_CHECKS=0') or die(mysqli_error($this->conn));

        // remove all old tables first
        foreach ($this->getAllTableNames() as $tabName) {
            mysqli_query($this->conn, 'DROP TABLE IF EXISTS `' . $tabName . '`') or die(mysqli_error($this->conn));
        }

        foreach (scandir($this->makeStructureDirName()) as $dumpFile) {
            $tabName = substr($dumpFile, 0, -strlen(self::$ext));
            $structureFileName = $this->makeStructureFileName($tabName);

            if ($structureFileName && file_exists($structureFileName)) { // endsWith .sql
                $createTableDump = file_get_contents($structureFileName);
                mysqli_query($this->conn, $createTableDump) or die(mysqli_error($this->conn));

                $dataFileName = $this->makeDataFileName($tabName);
                if (file_exists($dataFileName)) {
                    $dataDump = file_get_contents($this->makeDataFileName($tabName));

                    foreach (preg_split('/;\s*\n/', $dataDump) as $querySql) {
                        $querySql = trim($querySql);
                        if ($querySql) {
                            mysqli_query($this->conn, $querySql) or die(mysqli_error($this->conn));
                        }
                    }
                }
            }
        }

        mysqli_query($this->conn, 'SET FOREIGN_KEY_CHECKS=1') or die(mysqli_error($this->conn));
    }

    private function getAllTableNames() {
        $ret = [];
        $result = mysqli_query($this->conn, 'SHOW TABLES') or die(mysqli_error($this->conn));
        while ($row = mysqli_fetch_row($result)) {
            $tabName = $row[0];
            if (strpos($tabName, $this->prefix) === 0) { // starts with
                $ret[] = $tabName;
            }
        }
        return $ret;
    }

}
