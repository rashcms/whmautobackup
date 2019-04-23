<?php

/**
 * WHM Auto Backup
 * Lists and backs up cPanel accounts owned by a WHM reseller.
 *
 * @version 3.0.0
 * @author Liam Demafelix <liamdemafelix.n@gmail.com>
 * @url https://github.com/liamdemafelix/whmautobackup
 */

define('BASEPATH', __DIR__ . DIRECTORY_SEPARATOR);

/**
 * Require the boot script.
 * The boot script initializes all pre-execution operations for WHM Auto Backup.
 * This is useful for setting globals, overriding configurations and the like.
 */
require BASEPATH . "app/boot.php";

/**
 * Begin.
 */
$cli->arguments->add([
    'destination' => [
        'prefix' => 'd',
        'longPrefix' => 'destination',
        'description' => 'The name of the remote destination as specified in the configuration file.',
        'required' => true
    ],
    'servers' => [
        'prefix' => 's',
        'longPrefix' => 'servers',
        'description' => 'A comma-separated list of server names that should make a backup. Leave empty to make backups for all servers.',
        'required' => true,
        'defaultValue' => '*'
    ],
    'email' => [
        'prefix' => 'e',
        'longPrefix' => 'email',
        'description' => 'Used in the cPanel API. cPanel will send an e-mail to this address for every account that finishes a backup.',
        'required' => false,
    ]
]);

// Parse the arguments
try {
    $cli->arguments->parse();
} catch (\League\CLImate\Exceptions\InvalidArgumentException $e) {
    $cli->error($e->getMessage());
    $cli->usage();
}

//