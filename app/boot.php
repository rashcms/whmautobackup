<?php

/**
 * WHM Auto Backup v3.0
 * Lists and backs up cPanel accounts owned by a WHM reseller.
 *
 * @author Liam Demafelix <liamdemafelix.n@gmail.com>
 * @url https://github.com/liamdemafelix/whmautobackup
 */

if (!defined('BASEPATH')) {
    echo "Please do not execute this file directly.";
    exit(1);
}

// Override PHP settings
ini_set('memory_limit', '-1');
set_time_limit(0);
ini_set('max_execution_time', '0');
ini_set('max_input_vars', '100000');

// Grab the configuration file
$config = require BASEPATH . "app/config.sample.php";

// Set the timestamp (start)
$timestamp = date('Y-m-d_H:i:s');

// Get composer
require BASEPATH . "vendor/autoload.php";

// Initialize CLImate
$cli = new \League\CLImate\CLImate();