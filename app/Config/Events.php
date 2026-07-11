<?php

namespace Config;

use CodeIgniter\Events\Events;

// Sesión PHP desactivada — la autenticación usa JWT, no session_start().
// Eliminar session_start() evita el file locking que serializa peticiones
// concurrentes del mismo usuario (causa de latencia ~1.3s en peticiones paralelas).
