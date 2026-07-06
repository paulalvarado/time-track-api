<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Gemini extends BaseConfig
{
    public string $model = 'gemini-2.5-flash';
    public int $maxAudioSize = 10485760;
}
