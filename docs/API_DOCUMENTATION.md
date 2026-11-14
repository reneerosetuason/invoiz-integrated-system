# INVOIZ REST API Documentation

This document describes the REST API endpoints provided by the INVOIZ backend for mobile applications (`buyer_mobile`, `rider_mobile`) and external integrations.

---

## 1. General Standards & Conventions

### Base URLs
* **Buyer & Commerce API**: `http://<your-host>/api`
* **Logistics & Courier API**: `http://<your-host>/api/logistics`

### Common Headers
```http
Accept: application/json
Content-Type: application/json
Authorization: Bearer <SANCTUM_PERSONAL_ACCESS_TOKEN>
```

### Standard Response Structure

#### Success Response (200 / 201)
```json
{
  "message": "Operation successful.",
  "data": { ... }
}
```

#### Validation Error Response (422)
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "field_name": [
      "Validation error description."
    ]
  }
}
```

#### Authentication / Access Denied (401 / 403)
```json
{
  "message": "Unauthenticated."
}
```

---

## 2. Authentication & Account Endpoints

### 1. User Registration
Registers a new customer account.
* **Method**: `POST`
* **Path**: `/api/register`
* **Payload**:
  ```json
  {
    "name": "Juan Dela Cruz",
    "email": "juan@example.com",
    "password": "SecurePassword123!",
    "password_confirmation": "SecurePassword123!",
    "phone": "+639171234567"
  }
  ```
* **Response**: `201 Created`
  Returns user record and dispatches email OTP verification.

### 2. User Login
Authenticates an existing user and returns a Sanctum bearer token.
* **Method**: `POST`
* **Path**: `/api/login`
* **Payload**:
  ```json
  {
    "email": "juan@example.com",
    "password": "SecurePassword123!"
  }
  ```
* **Response**: `200 OK`
  ```json
  {
    "token": "1|qX83kd9a01kdls...",
    "user": {
      "id": 15,
      "name": "Juan Dela Cruz",
      "email": "juan@example.com",
      "role": "buyer",
      "account_status": "active"
    }
  }
  ```

### 3. Verify OTP
Verifies the 6-digit email confirmation code.
* **Method**: `POST`
* **Path**: `/api/verify-otp`
* **Payload**:
  ```json
  {
    "email": "juan@example.com",
    "code": "849201"
  }
  ```

### 4. Current User Profile
Retrieves currently authenticated user data and permissions.
* **Method**: `GET`
* **Path**: `/api/user`
* **Headers**: `Authorization: Bearer <token>`

---

## 3. Buyer Marketplace APIs

### 1. Search & Browse Products
* **Method**: `GET`
* **Path**: `/api/products`
* **Query Parameters**:
  * `search` (string): Keywords matching product title, description, or store name.
  * `category` (string): Slug/ID of category.
  * `store_id` (int): Filter products by a specific merchant.
  * `on_sale` (bool): Filter items currently marked on promotional sale.
  * `sort` (string): `recommended`, `price_asc`, `price_desc`, `rating`.

### 2. Product Details
* **Method**: `GET`
* **Path**: `/api/products/{id}`
* **Response**:
  ```json
  {
    "id": 127,
    "title": "Ergonomic Office Chair",
    "price": 2500.00,
    "stock": 14,
    "weight_kg": 7.50,
    "length_cm": 60.00,
    "width_cm": 60.00,
    "height_cm": 95.00,
    "images": [
      "/storage/products/127/front.jpg",
      "/storage/products/127/back.jpg"
    ],
    "seller": {
      "id": 4,
      "store_name": "Comfort Seats PH",
      "rating": 4.9
    }
  }
  ```

### 3. Cart Management
* **Get Cart**: `GET /api/cart`
* **Add to Cart**: `POST /api/cart` (body: `product_id`, `quantity`, `variant_id`)
* **Update Quantity**: `PATCH /api/cart/{itemId}` (body: `quantity`)
* **Remove Item**: `DELETE /api/cart/{itemId}`

### 4. Orders & Checkout
* **Checkout Order**: `POST /api/checkout`
  * Body: `address_id`, `payment_method` (`cod`, `gcash`), `voucher_code`, `items: [{cart_item_id}]`.
* **List Buyer Orders**: `GET /api/orders`
* **Get Order Details**: `GET /api/orders/{id}`
* **Cancel Order**: `POST /api/orders/{id}/cancel` (body: `cancellation_reason`)

### 5. Return & Refund Disputes
* **Submit Return Request**:
  * **Method**: `POST`
  * **Path**: `/api/orders/{id}/returns`
  * **Content-Type**: `multipart/form-data`
  * **Fields**:
    * `order_item_id` (required, int): Specific purchased line item.
    * `quantity` (required, int): Number of items to return.
    * `reason` (required, string): One of `damaged, wrong_item, not_as_described, changed_mind, ordered_wrong_size, ordered_wrong_color, received_wrong_size, received_wrong_color, other`.
    * `reason_details` (string): Required when reason is `other` (min 10 characters).
    * `evidence` (required, file): Photo or video file (max 50MB).
* **Confirm Refund Received**:
  * **Method**: `POST`
  * **Path**: `/api/returns/{returnRequestId}/confirm`
  * **Response**: Marks settlement complete and closes dispute.
* **View Refund Proof**:
  * **Method**: `GET`
  * **Path**: `/api/returns/{returnRequestId}/proof`

---

## 4. Logistics & Courier APIs

### 1. Courier Login
* **Method**: `POST`
* **Path**: `/api/logistics/auth/login`
* **Payload**: `email`, `password`

### 2. Assigned Deliveries Queue
* **Method**: `GET`
* **Path**: `/api/logistics/rider/deliveries`
* **Headers**: `Authorization: Bearer <rider_token>`
* **Returns**: Active delivery jobs sorted by priority and proximity.

### 3. Update Delivery Milestone
* **Pickup Parcel from Merchant**:
  * `POST /api/logistics/rider/deliveries/{id}/pickup`
* **Complete Dropoff with Proof**:
  * `POST /api/logistics/rider/deliveries/{id}/deliver`
  * `multipart/form-data`: `proof_photo` (image file), `signature` (base64 or image), `notes`.
* **Report Delivery Failure**:
  * `POST /api/logistics/rider/deliveries/{id}/fail`
  * Body: `failure_reason` (`customer_unavailable`, `wrong_address`, `refused_delivery`).
