# API Documentation

## Authentication

All API endpoints require authentication using an API token.

Include the token in the request header:

```
X-API-Token: pk_xxx.yyy
```

---

## Generating API Token

API tokens are generated using a Symfony console command inside the BMS container.

Example:

```
php bin/console app:generate-api-token "PartnerName" products.read orders.read
```

Example output:

```
pk_xxxxxxxxxxxxxxxx.yyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyy
```

⚠️ The secret part of the token cannot be retrieved again after creation.

---

## Products

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /api/v1/products | List products |
| GET | /api/v1/products/{id} | Get product detail |
| POST | /api/v1/products | Create product |
| PUT/PATCH | /api/v1/products/{id} | Update product |
| DELETE | /api/v1/products/{id} | Delete product |

---

## Categories

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /api/v1/categories | List categories |
| GET | /api/v1/categories/{id} | Get category detail |
| POST | /api/v1/categories | Create category |
| PUT | /api/v1/categories/{id} | Update category |
| DELETE | /api/v1/categories/{id} | Delete category |

---

## Colors

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /api/v1/colors | List colors |
| GET | /api/v1/colors/{id} | Get color detail |
| POST | /api/v1/colors | Create color |
| PUT | /api/v1/colors/{id} | Update color |
| DELETE | /api/v1/colors/{id} | Delete color |

---

## Sizes

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /api/v1/sizes | List sizes |
| GET | /api/v1/sizes/{id} | Get size detail |
| POST | /api/v1/sizes | Create size |
| PUT/PATCH | /api/v1/sizes/{id} | Update size |
| DELETE | /api/v1/sizes/{id} | Delete size |

---

## Currencies

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /api/v1/currencies | List currencies |
| GET | /api/v1/currencies/{id} | Get currency detail |
| GET | /api/v1/currencies/default | Get default currency |
| POST | /api/v1/currencies | Create currency |
| PUT | /api/v1/currencies/{id} | Update currency |
| DELETE | /api/v1/currencies/{id} | Delete currency |
| POST | /api/v1/currencies/{id}/default | Set default currency |

---

## Customers

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /api/v1/customers | List customers |
| GET | /api/v1/customers/{id} | Get customer detail |
| POST | /api/v1/customers/register | Register customer |
| GET | /api/v1/customers/{id}/wishlist | Get wishlist |
| POST | /api/v1/customers/{id}/wishlist | Update wishlist |
| POST | /api/v1/customers | Create customer (admin) |
| PUT | /api/v1/customers/{id} | Update customer |
| DELETE | /api/v1/customers/{id} | Delete customer |

---

## Employees

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /api/v1/employees | List employees |
| GET | /api/v1/employees/{id} | Get employee detail |
| POST | /api/v1/employees | Create employee |
| PATCH | /api/v1/employees/{id} | Update employee |
| DELETE | /api/v1/employees/{id} | Delete employee |
| POST | /api/v1/employees/{id}/password | Change password |

---

## Orders

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /api/v1/orders | List orders |
| GET | /api/v1/orders/{id} | Get order detail |
| POST | /api/v1/orders | Create order |
| PUT/PATCH | /api/v1/orders/{id} | Update order |
| DELETE | /api/v1/orders/{id} | Delete order |

---

## Returns

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /api/v1/returns | List return requests |
| GET | /api/v1/returns/{id} | Get return detail |
| POST | /api/v1/returns | Create return request |
| PATCH | /api/v1/returns/{id}/status | Update return status |
| DELETE | /api/v1/returns/{id} | Delete return |

---

## Shop Info

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /api/v1/shop-info | Get current shop info |
| GET | /api/v1/shop-info/{id} | Get shop info detail |
| POST | /api/v1/shop-info | Create shop info |
| PUT/PATCH | /api/v1/shop-info/{id} | Update shop info |
| DELETE | /api/v1/shop-info/{id} | Delete shop info |
| PATCH | /api/v1/shop-info/current | Update current shop info |

---

## Notes

- All endpoints return JSON responses.
- Authentication is required for all endpoints except health checks.
- Access is controlled via scopes mapped to roles (e.g., `products.read`, `products.write`).
