<?php

namespace Config;

use CodeIgniter\Events\Events;

Events::on('pre_system', static function () {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
});
