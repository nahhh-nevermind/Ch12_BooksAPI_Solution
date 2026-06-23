<?php
use Dotenv\Dotenv;
use Slim\Factory\AppFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

header("Access-Control-Allow-Origin: https://ch13-full-stack-solution-85klzadqw-utms-tudent.vercel.app");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

require __DIR__ . '/../vendor/autoload.php';

Dotenv::createImmutable(__DIR__ . '/..')->safeLoad();

$app = AppFactory::create();

$app->options('/{routes:.+}', function ($request, $response, $args) {
    return $response;
});

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
