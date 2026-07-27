<?php

require __DIR__ . '/../vendor/autoload.php';

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Factory\AppFactory;
use Slim\Psr7\Response as SlimResponse;

/* CONFIGURATION (kept out of version control) */

// config.php holds real local credentials and is listed in .gitignore.
// config.example.php is the committed template — copy it to config.php and
// fill in your own values before running the API.
$configFile = __DIR__ . '/../config.php';
$config = file_exists($configFile)
    ? require $configFile
    : require __DIR__ . '/../config.example.php';

/* DATABASE CONNECTION (PDO) */

function getDbConnection(): PDO
{
    global $config;

    $dsn = "mysql:host={$config['db_host']};dbname={$config['db_name']};charset={$config['db_charset']}";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    return new PDO($dsn, $config['db_user'], $config['db_pass'], $options);
}

/* APP SETUP */

$app = AppFactory::create();
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$app->addErrorMiddleware(true, true, true);

define('API_TOKEN', $config['api_token']);

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

/* RATE LIMITING MIDDLEWARE (sliding window, per client IP) */

const RATE_LIMIT_MAX_REQUESTS = 30;   // max requests...
const RATE_LIMIT_WINDOW_SECONDS = 60; // ...per this many seconds
const RATE_LIMIT_DIR = __DIR__ . '/../storage/rate_limit';

/**
 * Very small file-based sliding-window rate limiter.
 * One JSON file per client IP stores recent request timestamps.
 * Safe for a single-server student project; not meant for production scale.
 */
function isRateLimited(string $clientIp): bool
{
    if (!is_dir(RATE_LIMIT_DIR)) {
        mkdir(RATE_LIMIT_DIR, 0777, true);
    }

    $safeName = preg_replace('/[^a-zA-Z0-9_.]/', '_', $clientIp);
    $file = RATE_LIMIT_DIR . '/' . $safeName . '.json';

    $handle = fopen($file, 'c+');
    if (!$handle) {
        // If we can't open the tracking file, fail open rather than blocking all traffic
        return false;
    }

    flock($handle, LOCK_EX);

    $raw = stream_get_contents($handle);
    $timestamps = $raw ? (json_decode($raw, true) ?: []) : [];

    $now = time();
    $windowStart = $now - RATE_LIMIT_WINDOW_SECONDS;

    // Keep only timestamps within the current window
    $timestamps = array_values(array_filter($timestamps, fn($t) => $t > $windowStart));

    $limited = count($timestamps) >= RATE_LIMIT_MAX_REQUESTS;

    if (!$limited) {
        $timestamps[] = $now;
    }

    ftruncate($handle, 0);
    rewind($handle);
    fwrite($handle, json_encode($timestamps));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);

    return $limited;
}

$rateLimitMiddleware = function (Request $request, RequestHandler $handler): Response {
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    if (isRateLimited($clientIp)) {
        return jsonResponse(new SlimResponse(), [
            'status'  => 'error',
            'message' => 'Too many requests. Please wait a moment and try again.',
        ], 429);
    }

    return $handler->handle($request);
};

/* TOKEN-BASED AUTH MIDDLEWARE */

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

/* PUBLIC ROUTES (no token required) */

$app->get('/', function (Request $request, Response $response) {
    return jsonResponse($response, [
        'message' => 'Welcome to the Secured Filipino Cookbook API',
        'note'    => 'Use a valid Bearer token to access /api endpoints.',
    ]);
});

// Health check — used by the client to detect if the API is reachable
$app->get('/api/status', function (Request $request, Response $response) {
    return jsonResponse($response, [
        'status'  => 'ok',
        'message' => 'Filipino Cookbook API is running',
    ]);
});

/* SECURED /api ROUTES */

$app->group('/api', function ($group) {

    /* GET ALL FOODS */
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

    /* GET A RANDOM FOOD */
    // Registered before /foods/{id} so "random" is never mistaken for a numeric id
    $group->get('/foods/random', function (Request $request, Response $response) {
        $pdo = getDbConnection();

        $stmt = $pdo->query("
            SELECT f.food_id, f.food_name, c.category_name, o.origin_name, f.instructions
            FROM foods f
            JOIN categories c ON f.category_id = c.category_id
            JOIN origins o ON f.origin_id = o.origin_id
            ORDER BY RAND()
            LIMIT 1
        ");
        $food = $stmt->fetch();

        if (!$food) {
            return jsonResponse($response, [
                'status'  => 'error',
                'message' => 'No foods available in the database.',
            ], 404);
        }

        $food = attachIngredients($pdo, $food);

        return jsonResponse($response, $food);
    });

    /* GET FOOD BY ID */
    $group->get('/foods/{id:[0-9]+}', function (Request $request, Response $response, array $args) {
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

    /* SEARCH FOOD BY NAME */
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

    /* GET ALL CATEGORIES */
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

    /* ADD NEW FOOD */
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
                'food_id' => $nextId,
            ], 201);
        } catch (PDOException $e) {
            $pdo->rollBack();
            return jsonResponse($response, [
                'status'  => 'error',
                'message' => 'Failed to add food: ' . $e->getMessage(),
            ], 500);
        }
    });

})->add($authMiddleware)->add($rateLimitMiddleware);

/* CORS MIDDLEWARE — must be added last so it runs first */

$app->add(function (Request $request, RequestHandler $handler): Response {
    // Handle preflight OPTIONS requests immediately — before routing or auth
    if ($request->getMethod() === 'OPTIONS') {
        $response = new SlimResponse();
        return $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', 'Authorization, Content-Type')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
            ->withHeader('Access-Control-Max-Age', '86400')
            ->withStatus(200);
    }

    // Pass through all other requests and add CORS headers to the response
    $response = $handler->handle($request);
    return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'Authorization, Content-Type')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
});

$app->run();
