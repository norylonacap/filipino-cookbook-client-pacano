# Secured Filipino Cookbook API (Slim Framework)

REST API built with Slim Framework, PDO (MySQL), and token-based security.

## 1. Prepare the database

1. Start MySQL (XAMPP).
2. Import `filipino_foods_relational.sql` — it creates and fills the `filipino_cookbook_api` database (`categories`, `origins`, `foods`, `ingredients`, `food_ingredients`).

## 2. Install dependencies

From the project root:

```bash
composer install
```

This downloads Slim and slim/psr7 into `vendor/` (not included in this download — installers need internet access to Packagist).

If `composer.json` isn't already here, it is — just run the command above.

## 3. Configure the DB connection

Open `public/index.php` → `getDbConnection()` and adjust `$user` / `$pass` if your MySQL root account isn't the default XAMPP `root` with no password.

## 4. Run the API

```bash
php -S localhost:8000 -t public
```

Base URL: `http://localhost:8000`

## 5. Authentication

All `/api/*` routes require this header:

```
Authorization: Bearer dmmmsu-cookbook-token-2026
```

Missing/incorrect token → `401 Unauthorized`.

## 6. Endpoints

| Method | Endpoint | Token? | Purpose |
|---|---|---|---|
| GET | `/` | No | Welcome message |
| GET | `/api/foods` | Yes | All foods + category, origin, ingredients |
| GET | `/api/foods/{id}` | Yes | One food by ID (404 if missing) |
| GET | `/api/foods/search/{name}` | Yes | Search foods by name |
| GET | `/api/categories` | Yes | All categories |
| GET | `/api/ingredients` | Yes | All ingredients |
| POST | `/api/foods` | Yes | Add a new food (201 Created) |

### Sample POST body for `/api/foods`

```json
{
  "food_name": "Dinengdeng",
  "category_id": 3,
  "origin_id": 4,
  "instructions": "Boil vegetables with bagoong-based broth and add grilled fish before serving.",
  "ingredient_ids": [10, 15, 22]
}
```

## 7. Testing with Thunder Client

1. New request → `GET http://localhost:8000/` → should return the welcome message with no auth needed.
2. New request → `GET http://localhost:8000/api/foods` → add header `Authorization: Bearer dmmmsu-cookbook-token-2026` → should return the full food list.
3. Remove/alter the header → should get `401 Unauthorized`.
4. Try `GET http://localhost:8000/api/foods/999` → should get `404 Not Found`.
5. Try `POST http://localhost:8000/api/foods` with the JSON body above (Body → JSON) plus the Authorization header → should get `201 Created`.

## Project structure

```
filipino-cookbook-api/
├── composer.json
├── filipino_foods_relational.sql
├── public/
│   └── index.php
└── vendor/            (created by `composer install`)
```
