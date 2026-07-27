# Filipino Cookbook API — Documentation

Base URL: `http://localhost:8000`

All `/api/*` endpoints require:

```
Authorization: Bearer YOUR_SECRET_API_TOKEN
Accept: application/json
```

Missing/invalid token → `401 Unauthorized`. Too many requests from the same
IP within 60 seconds → `429 Too Many Requests`.

---

## GET /

**Description:** Welcome message. No authentication required.

**Example request:**
```
GET http://localhost:8000/
```

**Example response (200):**
```json
{
  "message": "Welcome to the Secured Filipino Cookbook API",
  "note": "Use a valid Bearer token to access /api endpoints."
}
```

---

## GET /api/status

**Description:** Health check used to confirm the API is reachable. No
authentication required.

**Example response (200):**
```json
{
  "status": "ok",
  "message": "Filipino Cookbook API is running"
}
```

---

## GET /api/foods

**Description:** Returns all Filipino foods, each with its category, origin,
and ingredient list attached.

**Required headers:**
```
Authorization: Bearer YOUR_SECRET_API_TOKEN
```

**Example response (200):**
```json
[
  {
    "food_id": 1,
    "food_name": "Adobo",
    "category_name": "Main Dish",
    "origin_name": "Manila",
    "instructions": "Simmer pork or chicken in soy sauce, vinegar, and garlic.",
    "ingredients": ["Bay Leaves", "Garlic", "Pork", "Soy Sauce", "Vinegar"]
  }
]
```

**Example error response (401):**
```json
{
  "status": "error",
  "message": "Unauthorized access. Valid API token is required."
}
```

---

## GET /api/foods/random

**Description:** Returns one randomly selected food, in the same shape as
`/api/foods`.

**Example request:**
```
GET http://localhost:8000/api/foods/random
```

**Example response (200):**
```json
{
  "food_id": 7,
  "food_name": "Sinigang",
  "category_name": "Soup",
  "origin_name": "Batangas",
  "instructions": "Boil pork or shrimp in a tamarind-based sour broth with vegetables.",
  "ingredients": ["Kangkong", "Pork", "Radish", "Tamarind", "Tomato"]
}
```

**Example error response (404) — no foods exist yet:**
```json
{
  "status": "error",
  "message": "No foods available in the database."
}
```

---

## GET /api/foods/{id}

**Description:** Returns one food by its numeric ID.

**Example request:**
```
GET http://localhost:8000/api/foods/1
```

**Example error response (404):**
```json
{
  "status": "error",
  "message": "Food not found"
}
```

---

## GET /api/foods/search/{name}

**Description:** Returns all foods whose name contains the given text
(case-insensitive partial match).

**Example request:**
```
GET http://localhost:8000/api/foods/search/adobo
```

**Example error response (404) — no matches:**
```json
{
  "status": "error",
  "message": "No matching food found"
}
```

---

## GET /api/categories

**Description:** Returns all food categories.

**Example response (200):**
```json
[
  { "category_id": 1, "category_name": "Appetizer" },
  { "category_id": 4, "category_name": "Main Dish" }
]
```

---

## GET /api/ingredients

**Description:** Returns all ingredients.

**Example response (200):**
```json
[
  { "ingredient_id": 1, "ingredient_name": "Bagoong" },
  { "ingredient_id": 2, "ingredient_name": "Bay Leaves" }
]
```

---

## POST /api/foods

**Description:** Adds a new food.

**Required headers:**
```
Authorization: Bearer YOUR_SECRET_API_TOKEN
Content-Type: application/json
```

**Request body:**
```json
{
  "food_name": "Dinengdeng",
  "category_id": 3,
  "origin_id": 4,
  "instructions": "Boil vegetables with bagoong-based broth and add grilled fish before serving.",
  "ingredient_ids": [10, 15, 22]
}
```

**Example success response (201):**
```json
{
  "status": "success",
  "message": "Food added successfully.",
  "food_id": 66
}
```

**Example error response (400) — missing field:**
```json
{
  "status": "error",
  "message": "Missing required field: food_name"
}
```

**Example error response (500) — database error (e.g. bad category_id):**
```json
{
  "status": "error",
  "message": "Failed to add food: ..."
}
```

---

## Rate Limiting

All `/api/*` routes allow **30 requests per 60 seconds per client IP**.
Exceeding this returns:

**Example error response (429):**
```json
{
  "status": "error",
  "message": "Too many requests. Please wait a moment and try again."
}
```

---

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
