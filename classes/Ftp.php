<?php

/**
 * WHM Auto Backup v3.0
 * Lists and backs up cPanel accounts owned by a WHM reseller.
 *
 * @author Liam Demafelix <liamdemafelix.n@gmail.com>
 * @url https://github.com/liamdemafelix/whmautobackup
 */

namespace Classes;

use Stringy\Stringy as S;

class Ftp
{
    /**
     * Initialize holders for configuration data.
     *
     * @var string
     */
    protected $host, $username, $password, $port, $directory;

    /**
     * Holds the SFTP connection.
     *
     * @var resource
     */
    protected $connection, $sftp;

    /**
     * Ftp constructor.
     *
     * @param $config
     * @throws \Exception
     */
    public function __construct($config)
    {
        $this->host = $config['host'];
        $this->username = $config['username'];
        $this->password = $config['password'];
        if (!is_int($config['port'])) {
            throw new \Exception("The port for this destination must be an integer.");
        }
        if ($config['port'] <= 0 || $config['port'] > 65535) {
            throw new \Exception("The port for this destination must be in the range of 1-65535.");
        }
        $this->port = $config['port'];
        $this->directory = S::create($config['directory'])->ensureRight('/');

        // Attempt to connect
        $this->connect();
    }

    /**
     * Establish a connection to the FTP server.
     */
    public function connect()
    {
        $this->connection = @ftp_connect($this->host, $this->port);
        if (!$this->connection) {
            throw new \Exception("Failed to connect to remote FTP server: server unresponsive.");
        }
        $login = @ftp_login($this->connection, $this->username, $this->password);
        if (!$login) {
            throw new \Exception("Failed to connect to remote FTP server: invalid username/password.");
        }
    }

    /**
     * Creates a folder on the remote FTP server recursively.
     *
     * @param $ftpBaseDir
     * @param $ftpPath
     * @return bool
     */
    public function mkdir($ftpBaseDir, $ftpPath)
    {
        @ftp_chdir($this->connection, $ftpBaseDir);
        $parts = explode('/', $ftpPath);
        foreach($parts as $part){
            if(!@ftp_chdir($this->connection, $part)){
                $m1 = @ftp_mkdir($this->connection, $part);
                if (!$m1) {
                    return false;
                }
                $m2 = @ftp_chdir($this->connection, $part);
                if (!$m2) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Creates a directory for the current runtime timestamp.
     *
     * @param $timestamp
     * @param $servers
     * @throws \Exception
     * @return string
     */
    public function makeRuntimeDirectory($timestamp, $servers)
    {
        $path = $this->directory . $timestamp;
        foreach ($servers as $server) {
            $s = $this->mkdir("/", $path . "/" . $server);
            if (!$s) {
                throw new \Exception("Failed to create remote directory {$path}/{$server}");
            }
        }
        return $path;
    }
}