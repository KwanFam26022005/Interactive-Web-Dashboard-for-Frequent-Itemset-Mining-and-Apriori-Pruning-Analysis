<?php

declare(strict_types=1);

use App\Dataset\DatasetImportLimits;
use App\Dataset\DatasetImportService;
use App\Dataset\ParserRegistry;
use App\Http\ApiResponse;
use App\Http\DatasetController;
use App\Http\JsonResponder;
use App\Http\RequestValidator;
use App\Persistence\ConnectionFactory;
use App\Persistence\DatasetRepository;

require_once dirname(__DIR__, 2) . '/src/Bootstrap.php';

$responder = new JsonResponder();

try {
    $config = require APP_ROOT . '/config/app.php';
    $pdo = ConnectionFactory::create($config['db']);
    $datasets = new DatasetRepository($pdo);
    $imports = new DatasetImportService(
        new ParserRegistry(),
        $datasets,
        new DatasetImportLimits($config['upload']['max_bytes'])
    );
    $controller = new DatasetController(
        new RequestValidator(
            $config['upload']['max_bytes'],
            RequestValidator::parsePhpByteLimit(ini_get('post_max_size'))
        ),
        $datasets,
        $imports
    );

    $response = $controller->handle(
        $_SERVER['REQUEST_METHOD'] ?? '',
        $_SERVER['CONTENT_TYPE'] ?? null,
        $_GET,
        $_POST,
        $_FILES,
        $_SERVER['CONTENT_LENGTH'] ?? null
    );
} catch (Throwable) {
    $response = ApiResponse::error(
        500,
        'INTERNAL_ERROR',
        'An internal server error occurred.'
    );
}

$responder->emit($response);
