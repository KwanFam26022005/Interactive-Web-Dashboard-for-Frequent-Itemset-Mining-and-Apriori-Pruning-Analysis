<?php

declare(strict_types=1);

use App\Http\ApiResponse;
use App\Http\JsonResponder;
use App\Http\MiningController;
use App\Http\MiningResponseAssembler;
use App\Http\RequestValidator;
use App\Mining\AprioriEngine;
use App\Mining\AssociationRuleGenerator;
use App\Mining\HeatmapBuilder;
use App\Persistence\ConnectionFactory;
use App\Persistence\DatasetRepository;
use App\Persistence\ExperimentRunRepository;

require_once dirname(__DIR__, 2) . '/src/Bootstrap.php';

$responder = new JsonResponder();

try {
    $config = require APP_ROOT . '/config/app.php';
    $pdo = ConnectionFactory::create($config['db']);
    $datasets = new DatasetRepository($pdo);
    $controller = new MiningController(
        new RequestValidator($config['upload']['max_bytes']),
        $datasets,
        new AprioriEngine(),
        new AssociationRuleGenerator(),
        new HeatmapBuilder(),
        new ExperimentRunRepository($pdo),
        new MiningResponseAssembler(),
        $responder,
        $config['mining']['max_candidates'],
        $config['mining']['timeout_seconds'],
        $config['mining']['max_rules']
    );

    $rawBody = file_get_contents('php://input');
    if (!is_string($rawBody)) {
        throw new RuntimeException('Request body could not be read.');
    }

    $response = $controller->handle(
        $_SERVER['REQUEST_METHOD'] ?? '',
        $_SERVER['CONTENT_TYPE'] ?? null,
        $_GET,
        $rawBody
    );
} catch (Throwable) {
    $response = ApiResponse::error(
        500,
        'INTERNAL_ERROR',
        'An internal server error occurred.'
    );
}

$responder->emit($response);
