<?php
// filepath: /c:/xampp/htdocs/5CS024-alpha-0-0-2/5CS024/garrison/backend/app/Http/Middleware/HandleCors.php
namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Fruitcake\Cors\HandleCors as Middleware;

class HandleCors extends Middleware
{
    protected $settings = [
        'paths' => ['api/*'],
        'allowed_methods' => ['*'],
        'allowed_origins' => ['*'],
        'allowed_origins_patterns' => [],
        'allowed_headers' => ['*'],
        'exposed_headers' => [],
        'max_age' => 0,
        'supports_credentials' => true,
    ];
}