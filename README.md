# Filipino Cookbook API

A secured REST API for browsing Filipino dishes, their categories, origins, and
ingredients — built with the Slim Framework, PDO (MySQL), and token-based
authentication.

## Description

- **Purpose:** Serve structured data about Filipino foods (categories, regional
  origins, ingredients, and preparation instructions) over a clean JSON REST API.
- **Type of information provided:** Foods, categories, origins, ingredients, and
  the many-to-many relationship between foods and their ingredients.
- **Intended users:** Fellow students building client/driver applications, and
  anyone wanting a browsable Filipino recipe dataset.
- **Main functions:** List foods, look up a single food, search foods by name,
  get a random food, list categories, list ingredients, and add a new food.
- **Technologies used:** PHP 8, Slim Framework 4, PDO/MySQL, Composer, JSON.

## Features

- Retrieve all Filipino foods, with category, origin, and ingredients attached
- Retrieve a single food by ID
- Search foods by name
- Get a randomly selected Filipino food
- Retrieve all food categories
- Retrieve all ingredients
- Add a new food (with ingredient list) via POST
- Token-based authentication on all `/api/*` routes
- Per-IP rate limiting on all `/api/*` routes
- JSON responses with consistent status codes throughout

## Technologies Used

- PHP (>= 8.0)
- Slim Framework 4
- MySQL / PDO
- Composer
- JSON
- Apache / XAMPP (or PHP's built-in server)
- Thunder Client / Postman for testing
- Git & GitHub

## Installation Instructions

```bash
git clone https://github.com/norylonacap/filipino-cookbook-api-pacano.git
cd filipino-cookbook-api-pacano
composer install

```

1. Copy the example config and fill in your local values:

   ```bash
   cp config.example.php config.php
   ```

   Edit `config.php`:

   ```php
   return [
       'db_host'    => '127.0.0.1',
       'db_name'    => 'filipino_cookbook_api',
       'db_user'    => 'localhost',
       'db_pass'    => 'none',
       'db_charset' => 'utf8mb4',
       'api_token'  => 'Bearer dmmmsu-cookbook-token-2026',
   ];
   ```

2. Start MySQL (e.g. via XAMPP) and import the database:

   ```bash
   mysql -u root -p < filipino_foods_relational.sql
   ```

3. Run the API:

   ```bash
   php -S localhost:8000 -t public
   ```

4. Test it — `GET http://localhost:8000/` should return a welcome message with
   no authentication required.

## Database Setup

- **Database name:** `filipino_cookbook_api`
- **SQL file:** `filipino_foods_relational.sql`
- **Tables:** `categories`, `origins`, `foods`, `ingredients`, `food_ingredients`
- **Relationships:**

  ```
  categories -> foods <- origins
  foods -> food_ingredients <- ingredients
  
  ```

  Each food belongs to one category and one origin. `food_ingredients` is a
  junction table implementing the many-to-many relationship between foods and
  ingredients.

## Base URL

```
http://localhost:8000/api
```

## Authentication

All `/api/*` routes require a Bearer token:

```
Authorization: Bearer dmmmsu-cookbook-token-2026
```

Missing or incorrect token → `401 Unauthorized`:

```json
{
  "status": "error",
  "message": "Unauthorized access. Valid API token is required."
}

```

## Endpoints

| Method | Endpoint | Token? | Purpose |
|---|---|---|---|
| GET | `/` | No | Welcome message |
| GET | `/api/status` | No | Health check |
| GET | `/api/foods` | Yes | All foods + category, origin, ingredients |
| GET | `/api/foods/random` | Yes | One randomly selected food |
| GET | `/api/foods/{id}` | Yes | One food by ID (404 if missing) |
| GET | `/api/foods/search/{name}` | Yes | Search foods by name |
| GET | `/api/categories` | Yes | All categories |
| GET | `/api/ingredients` | Yes | All ingredients |
| POST | `/api/foods` | Yes | Add a new food (201 Created) |

See `API_DOCUMENTATION.md` for full request/response examples for every
endpoint.

## HTTP Status Codes

| Status Code | Meaning |
|---|---|
| 200 | Request completed successfully |
| 201 | Resource created successfully |
| 400 | Invalid request or missing parameter |
| 401 | Missing or invalid authentication |
| 404 | Requested resource was not found |
| 429 | Too many requests |
| 500 | Internal server error |

## Project Structure

```
filipino-cookbook-api-pacano/
├── composer.json
├── composer.lock
├── config.example.php      (template — commit this)
├── config.php               (real local values — gitignored)
├── images                    (images for the README.md)
├── .gitignore
├── filipino_foods_relational.sql
├── public/
│   └── index.php
├── storage/
│   └── rate_limit/          (rate limiter runtime data — gitignored contents)
├── test-db.php
├── test-api.php
├── README.md
├── API_DOCUMENTATION.md
└── vendor/                   (created by `composer install`, gitignored)
```

---

## Optional API Enhancements

This project includes one added endpoint and one added security feature,
beyond the base Filipino Cookbook API from the previous activity.

### 1. New Endpoint — `GET /api/foods/random`

**Description:** Returns one randomly selected Filipino food, with its
category, origin, and full ingredient list attached — same shape as the other
food endpoints.

**Purpose:** Gives client apps an easy "surprise me" / discovery feature
without having to fetch the whole food list and pick one client-side.

**Files modified:** `public/index.php`

**Endpoint added:**
```
GET /api/foods/random
```

**Testing instructions:**
1. In Thunder Client/Postman, create `GET http://localhost:8000/api/foods/random`.
2. Add header `Authorization: Bearer YOUR_SECRET_API_TOKEN`.
3. Send the request — expect `200 OK` with a single food object.
4. Send it several times — the returned food should vary.
5. Temporarily rename the `foods` table (or empty it) and confirm the
   endpoint returns `404 Not Found` with a clear message, then restore it.

*![Random Foods](images/3.png)*
*![Error](images/5.png)*

### 2. Security Feature — Per-IP Rate Limiting

**Description:** All `/api/*` routes are now protected by a sliding-window
rate limiter: each client IP is limited to 30 requests per 60 seconds. Requests
over the limit receive `429 Too Many Requests` instead of being processed.

**Purpose:** Reduces the risk of brute-force token guessing and protects the
database from being overwhelmed by a runaway client or script.

**Files modified:** `public/index.php` (added `isRateLimited()` and
`$rateLimitMiddleware`, attached to the `/api` route group)

**Implementation notes:** The limiter stores a short list of recent request
timestamps per IP in `storage/rate_limit/`, using file locking (`flock`) so
concurrent requests don't corrupt the count. This keeps state across requests
without needing a database table or an external cache service — appropriate
for a single-server student project.

**Testing instructions:**
1. Send 30 requests to `GET /api/status`-protected route (e.g. `/api/categories`)
   within a minute, with a valid token — all should return `200 OK`.
2. Send a 31st request within the same minute — expect `429 Too Many Requests`:
   ```json
   {
     "status": "error",
     "message": "Too many requests. Please wait a moment and try again."
   }
   ```
3. Wait 60 seconds and try again — requests should succeed again.

*![Welcome](images/4.png)*

---

## Developer Information

- **Name: PACANO, Lyron Dave M.**
- **Course & Section: Information Technology 4A**
- **GitHub Username: noryonacap**
- **Repository Link: https://github.com/norylonacap/filipino-cookbook-client-pacano**
- **Date Completed: July 27, 2026**
