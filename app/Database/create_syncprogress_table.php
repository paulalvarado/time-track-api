<?php
require __DIR__ . '/../../vendor/autoload.php';

$db = \Config\Database::connect();
$db->query('CREATE TABLE IF NOT EXISTS public.syncprogress (
    id VARCHAR(50) PRIMARY KEY,
    "userId" VARCHAR(50) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT \'pending\',
    progress INT NOT NULL DEFAULT 0,
    log TEXT,
    "createdAt" TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    "updatedAt" TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)');
echo "Table 'syncprogress' created successfully.\n";
