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

class Scp
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
     * Scp constructor.
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
     * Establish a connection to the SCP/SFTP server.
     */
    public function connect()
    {
        $this->connection = @ssh2_connect($this->host, $this->port);
        if (!@ssh2_auth_password($this->connection, $this->username, $this->password)) {
            throw new \Exception("Failed to connect to remote SCP server: invalid username/password.");
        }
        $this->sftp = ssh2_sftp($this->connection);
    }

    /**
     * Creates a directory for the current runtime timestamp.
     *
     * @param $timestamp
     * @return string|bool
     */
    public function makeRuntimeDirectory($timestamp)
    {
        $path = $this->directory . $timestamp;
        $s = ssh2_sftp_mkdir($this->sftp, $path, 0700, true);
        if ($s)
            return $path;
        return false;
    }
}