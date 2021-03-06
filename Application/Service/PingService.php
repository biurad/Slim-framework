<?php

namespace Service\Api;

use function time;

class PingService
{
    public function handle()
    {
        $hi = json_encode(['rade-api-ping' => time()]);
        echo $hi;
        header('Access-Control-Allow-Origin: *');
        header('Content-Type: application/json');
    }
}
