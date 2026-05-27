<?php

namespace Godric\DbBackup;

use \Exception;
use \MySQLDump;
use \mysqli;

class DbBackup {

    private
        $db,
        $fileSuffix,
        $ftp,
        $numberOfBackups = 7;

    /**
     * Prepare backup with configuration taken from associative array $params.
     *
     * This method also creates database connection.
     */
    function __construct($params) {
        $this->db = new mysqli(
            $params['sourceDb']['server'],
            $params['sourceDb']['user'],
            $params['sourceDb']['password'],
            $params['sourceDb']['database']
        );

        $this->fileSuffix = $params['sourceDb']['database'] . '.sql.gz';

        if(is_array($params['targetFtp'])) {
            $this->ftp = (
                'ftp://' .
                $params['targetFtp']['user'] .
                ':' .
                $params['targetFtp']['password'] .
                '@' .
                $params['targetFtp']['server'] .
                '/' .
                $params['targetFtp']['directory']
            );
        } else {
            $this->ftp = $params['targetFtp'];
        }
    }

    /**
     * Remove old backups from FTP.
     */
    private function cleanup() {
        $files = $this->getBackupsList();
        rsort($files); // oldest files last

        while(count($files) > $this->numberOfBackups) {
            $oldestFile = array_pop($files);
            unlink($this->ftp . '/' . $oldestFile);
        }
    }

    /**
     * @return array of filenames matching $this->fileSuffix in target directory
     */
    private function getBackupsList() {
        $files = [];

        $d = dir($this->ftp);
        while(false !== ($entry = $d->read())) {
            if(str_ends_with($entry, $this->fileSuffix)) {
                $files[] = $entry;
            }
        }
        $d->close();

        return $files;
    }

    /**
     * Run backup - do actual backup of database to selected FTP.
     *
     * Avoid dots and colons in filenames to preserve compatibility in
     * Windows™.
     */
    function run() {
        $time = date('Y-m-d_H-i-s');
        $file = $time . '_' . $this->fileSuffix;

        $dump = new MySQLDump($this->db);
        $tmpfile = 'temp_' . mt_rand() . '.sql.gz';
        touch($tmpfile);
        chmod($tmpfile, 0600);
        try {
            $dump->save($tmpfile);
            $this->db->close();
            copy($tmpfile, $this->ftp . '/' . $file);
        } finally {
            unlink($tmpfile);
        }

        $this->cleanup();
    }

}

function str_ends_with($str, $suffix) {
    return substr($str, -strlen($suffix)) === $suffix;
}
