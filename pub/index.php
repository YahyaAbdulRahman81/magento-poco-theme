<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use Magento\Framework\App\Bootstrap;

try {
    require __DIR__ . '/../app/bootstrap.php';
} catch (\Exception $e) {
    echo "<h3>Bootstrap Error:</h3><pre>";
    echo $e->getMessage() . "\n\n";
    echo $e->getTraceAsString();
    echo "</pre>";
    exit(1);
}

$bootstrap = Bootstrap::create(BP, $_SERVER);
$app = $bootstrap->createApplication(\Magento\Framework\App\Http::class);
$bootstrap->run($app);
