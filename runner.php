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
 * Have a handler of all messages.
 * This makes it easy to make logs that can be sent to admins via e-mail.
 */
//$message = "";
$status = [];

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
    exit(1);
}

// Check if the destination exists in the config file
$destination = $cli->arguments->get('destination');
if (!array_key_exists($destination, $config['destinations'])) {
    $cli->error("Destination `{$destination}` does not exist.");
    exit(1);
}
$destinationData = $config['destinations'][$destination];

// Loop through the servers
$servers = [];
if ($cli->arguments->defined('servers')) {
    // Get the servers defined in the command
    $argServers = $cli->arguments->get('servers');
    foreach (explode(",", $argServers) as $server) {
        $servers[] = $server;
    }
} else {
    foreach ($config['servers'] as $serverName => $serverDetails) {
        $servers[] = $serverName;
    }
}
foreach ($servers as $server) {
    if (!array_key_exists($server, $config['servers'])) {
        $cli->error("Server `{$server}` does not exist.");
        exit(1);
    }
}

$cli->backgroundGreen('WHM AutoBackup v3.0.0');
$cli->out("Initializing...");

// Prepare destination directory if SCP/FTP
if ($destinationData['type'] == 'scp-pass') {
    try {
        $scp = new \Classes\Scp($destinationData);
        $path = $scp->makeRuntimeDirectory($timestamp);
        if (!$path) {
            $cli->error("Failed to create backup destination directory via SCP.");
            exit(1);
        }
        $cli->green("Using path {$path} on remote");
    } catch (Exception $e) {
        $cli->error($e->getMessage());
        exit(1);
    }
}

// Begin transactions
foreach ($servers as $server) {
    $meta = $config['servers'][$server];
    $requiredArrayKeys = ['host', 'username', 'token', 'port'];
    foreach ($requiredArrayKeys as $requiredKey) {
        if (!array_key_exists($requiredKey, $meta) || empty($meta[$requiredKey])) {
            $cli->error("Bad config for `{$requiredKey}` in `{$server}`.");
            exit(1);
        }
    }
    $cli->flank($server . ' - ' . $meta['host']);
    $status[$server] = [
        'success' => 0,
        'fail' => 0,
        'skip' => 0
    ];
    // Attempt to connect
    $cli->yellow("Attempting to connect to server `{$server}`...");
    try {
        $cpanel = new \Gufy\CpanelPhp\Cpanel([
            'host'        =>  "https://{$meta['host']}:{$meta['port']}",
            'username'    =>  $meta['username'],
            'auth_type'   =>  'hash', // set 'hash' or 'password'
            'password'    =>  $meta['token']
        ]);
        $cpanel->setTimeout(30);
        $cpanel->setConnectionTimeout(30);
        $accounts = $cpanel->listAccounts();
    } catch (Exception $e) {
        $cli->error($e->getMessage());
        continue;
    }

    $accountsJSON = @json_decode($accounts);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $cli->error('Server error: ' . substr($accounts, 0, strpos($accounts, 'response:')));
        $cli->error($accounts);
        continue;
    }

    $accountObjects = $accountsJSON->acct;
    $accountObjectsCount = count($accountObjects);

    // Count accounts
    $cli->line("- Found {$accountObjectsCount} accounts");
    foreach ($accountObjects as $object) {
        if ($object->suspended) {
            $cli->red("- Account {$object->user} is suspended. Skipping...");
            continue;
        }
        $cli->out("- Processing account {$object->user}...");
        if ($destinationData['type'] == 'scp-pass') {
            $dd = [
                'host' => $destinationData['host'],
                'port' => $destinationData['port'],
                'username' => $destinationData['username'],
                'password' => $destinationData['password'],
                'directory' => $path,
                'cpanel_jsonapi_user' => $object->user
            ];
            if ($cli->arguments->defined('email')) {
                $email = $cli->arguments->get('email');
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $cli->error("Invalid e-mail address specified on runtime for alerts. Skipping e-mail...");
                    $status[$server]['skip']++;
                } else {
                    $dd['email'] = $cli->arguments->get('email');
                }
            }
            $action = $cpanel->execute_action('3', 'Backup', 'fullbackup_to_scp_with_password', $object->user, $dd);
            $actionJSON = @json_decode($action);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $cli->error('Server error: ' . substr($accounts, 0, strpos($accounts, 'response:')));
                $status[$server]['fail']++;
                $cli->error($accounts);
                continue;
            }
            if ($actionJSON->result->status) {
                $cli->green("\tSuccess! PID {$actionJSON->result->data->pid}");
                $status[$server]['success']++;
            } else {
                $cli->red("\tFailed.");
                $status[$server]['fail']++;
            }
        }
    }
    $cli->br();
    $cli->green("Backup requests sent.");
}

// Done
$cli->br();
$cli->green("Operation complete.");
$table = [
    ['Server', 'Successful', 'Failed', 'Skipped']
];
foreach ($status as $server) {
    $table[] = [
        key($server), $server['success'], $server['fail'], $server['skip']
    ];
}
$cli->table($table);