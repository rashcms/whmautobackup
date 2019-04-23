<?php

/**
 * WHM Auto Backup v3.0
 * Lists and backs up cPanel accounts owned by a WHM reseller.
 *
 * @author Liam Demafelix <liamdemafelix.n@gmail.com>
 * @url https://github.com/liamdemafelix/whmautobackup
 */

namespace Classes;

class Ftp
{
    protected $host, $user, $pass, $variant, $directory, $port;

    public function __construct($config)
    {
        $this->host = $config['host'];
        $this->user = $config['user'];
        $this->pass = $config['password'];

    }
}