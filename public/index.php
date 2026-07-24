<?php

require __DIR__ . '/../vendor/autoload.php';

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Factory\AppFactory;
use Slim\Psr7\Response as SlimResponse;

/*  DATABASE CONNECTION (PDO)*/

function getDbConnection(): PDO
{
    $host    = '127.0.0.1';
    $db      = 'filipino_cookbook_api';
    $user    = 'root';
    $pass    = '';          
    $charset = 'utf8mb4';

    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    return new PDO($dsn, $user, $pass, $options);
}

/*  APP SETUP */

$app = AppFactory::create();
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$app->addErrorMiddleware(true, true, true);

const API_TOKEN = 'dmmmsu-cookbook-token-2026';

/* Helper: send a JSON response with a status code */
function jsonResponse(Response $response, $data, int $status = 200): Response
{
    $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
}

/* Helper: attach the ingredient list to a food row */
function attachIngredients(PDO $pdo, array $food): array
{
    $stmt = $pdo->prepare("
        SELECT i.ingredient_name
        FROM food_ingredients fi
        JOIN ingredients i ON fi.ingredient_id = i.ingredient_id
        WHERE fi.food_id = ?
        ORDER BY i.ingredient_name
    ");
    $stmt->execute([$food['food_id']]);
    $food['ingredients'] = array_column($stmt->fetchAll(), 'ingredient_name');
    return $food;
}

/*TOKEN-BASED AUTH MIDDLEWARE*/

$authMiddleware = function (Request $request, RequestHandler $handler): Response {
    $authHeader = $request->getHeaderLine('Authorization');

    if (!preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches) || $matches[1] !== API_TOKEN) {
        return jsonResponse(new SlimResponse(), [
            'status'  => 'error',
            'message' => 'Unauthorized access. Valid API token is required.',
        ], 401);
    }

    return $handler->handle($request);
};

/* PUBLIC WELCOME ROUTE (no token required) */

$app->get('/', function (Request $request, Response $response) {
    return jsonResponse($response, [
        'message' => 'Welcome to the Secured Filipino Cookbook API',
        'note'    => 'Use a valid Bearer token to access /api endpoints.',
    ]);
});

/* SECURED /api ROUTES */

$app->group('/api', function ($group) {

    /*  GET ALL FOODS */
    $group->get('/foods', function (Request $request, Response $response) {
        $pdo = getDbConnection();

        $stmt = $pdo->query("
            SELECT f.food_id, f.food_name, c.category_name, o.origin_name, f.instructions
            FROM foods f
            JOIN categories c ON f.category_id = c.category_id
            JOIN origins o ON f.origin_id = o.origin_id
            ORDER BY f.food_id
        ");
        $foods = $stmt->fetchAll();

        $foods = array_map(fn($food) => attachIngredients($pdo, $food), $foods);

        return jsonResponse($response, $foods);
    });

    /*GET FOOD BY ID*/
    $group->get('/foods/{id}', function (Request $request, Response $response, array $args) {
        $pdo = getDbConnection();
        $id  = (int) $args['id'];

        $stmt = $pdo->prepare("
            SELECT f.food_id, f.food_name, c.category_name, o.origin_name, f.instructions
            FROM foods f
            JOIN categories c ON f.category_id = c.category_id
            JOIN origins o ON f.origin_id = o.origin_id
            WHERE f.food_id = ?
        ");
        $stmt->execute([$id]);
        $food = $stmt->fetch();

        if (!$food) {
            return jsonResponse($response, [
                'status'  => 'error',
                'message' => 'Food not found',
            ], 404);
        }

        $food = attachIngredients($pdo, $food);

        return jsonResponse($response, $food);
    });

    /*SEARCH FOOD BY NAME*/
    $group->get('/foods/search/{name}', function (Request $request, Response $response, array $args) {
        $pdo  = getDbConnection();
        $name = $args['name'];

        $stmt = $pdo->prepare("
            SELECT f.food_id, f.food_name, c.category_name, o.origin_name, f.instructions
            FROM foods f
            JOIN categories c ON f.category_id = c.category_id
            JOIN origins o ON f.origin_id = o.origin_id
            WHERE f.food_name LIKE ?
            ORDER BY f.food_name
        ");
        $stmt->execute(["%{$name}%"]);
        $foods = $stmt->fetchAll();

        if (!$foods) {
            return jsonResponse($response, [
                'status'  => 'error',
                'message' => 'No matching food found',
            ], 404);
        }

        $foods = array_map(fn($food) => attachIngredients($pdo, $food), $foods);

        return jsonResponse($response, $foods);
    });

    /*  GET ALL CATEGORIES */
    $group->get('/categories', function (Request $request, Response $response) {
        $pdo  = getDbConnection();
        $stmt = $pdo->query("SELECT * FROM categories ORDER BY category_id");

        return jsonResponse($response, $stmt->fetchAll());
    });

    /* GET ALL INGREDIENTS */
    $group->get('/ingredients', function (Request $request, Response $response) {
        $pdo  = getDbConnection();
        $stmt = $pdo->query("SELECT * FROM ingredients ORDER BY ingredient_id");

        return jsonResponse($response, $stmt->fetchAll());
    });

    /* ADD NEW FOOD*/
    $group->post('/foods', function (Request $request, Response $response) {
        $data = $request->getParsedBody();

        $required = ['food_name', 'category_id', 'origin_id', 'instructions', 'ingredient_ids'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                return jsonResponse($response, [
                    'status'  => 'error',
                    'message' => "Missing required field: {$field}",
                ], 400);
            }
        }

        $pdo = getDbConnection();

        try {
            $pdo->beginTransaction();

            // foods.food_id is a plain INT primary key (not AUTO_INCREMENT),
            // so the next id is calculated manually.
            $nextId = (int) $pdo->query("SELECT COALESCE(MAX(food_id), 0) + 1 AS next_id FROM foods")
                                 ->fetch()['next_id'];

            $insertFood = $pdo->prepare("
                INSERT INTO foods (food_id, food_name, category_id, origin_id, instructions)
                VALUES (?, ?, ?, ?, ?)
            ");
            $insertFood->execute([
                $nextId,
                $data['food_name'],
                $data['category_id'],
                $data['origin_id'],
                $data['instructions'],
            ]);

            $insertIngredient = $pdo->prepare("
                INSERT INTO food_ingredients (food_id, ingredient_id) VALUES (?, ?)
            ");
            foreach ($data['ingredient_ids'] as $ingredientId) {
                $insertIngredient->execute([$nextId, (int) $ingredientId]);
            }

            $pdo->commit();

            return jsonResponse($response, [
                'status'  => 'success',
                'message' => 'Food added successfully.',
            ], 201);
        } catch (PDOException $e) {
            $pdo->rollBack();
            return jsonResponse($response, [
                'status'  => 'error',
                'message' => 'Failed to add food: ' . $e->getMessage(),
            ], 500);
        }
    });

})->add($authMiddleware);

$app->run();
