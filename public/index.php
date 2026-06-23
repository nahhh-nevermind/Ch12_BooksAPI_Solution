<?php
use Dotenv\Dotenv;
use Slim\Factory\AppFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

require __DIR__ . '/../vendor/autoload.php';

Dotenv::createImmutable(__DIR__ . '/..')->safeLoad();

$app = AppFactory::create();

$app->get('/', function (Request $request, Response $response, array $args) {
    $response->getBody()->write("Welcome to the Books API!");
    return $response;
});
$app->add(new App\Middleware\SecurityHeaders());
$app->add(new App\Middleware\JsonBodyParser());
$app->add(new App\Middleware\Cors());
$app->addRoutingMiddleware();
$app->addErrorMiddleware(true, true, true);
(require __DIR__ . '/../src/routes.php')($app);
$app->run();
