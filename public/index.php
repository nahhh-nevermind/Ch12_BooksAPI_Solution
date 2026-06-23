<?php
use Dotenv\Dotenv;
use Slim\Factory\AppFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

require __DIR__ . '/../vendor/autoload.php';

Dotenv::createImmutable(__DIR__ . '/..')->safeLoad();

$app = AppFactory::create();

$app->options('/{routes:.+}', function ($request, $response, $args) {
    return $response;
});

$app->add(function ($request, $handler) {
    $response = $handler->handle($request);

    // Explicitly grab your new Vercel origin dynamically from the incoming request headers
    $origin = $request->getHeaderLine('Origin') ?: '*';

    return $response
        ->withHeader('Access-Control-Allow-Origin', $origin)
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
        ->withHeader('Access-Control-Allow-Credentials', 'true');
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
