# Mobile API Documentation

A REST API layer for mobile app consumption with standardized JSON responses.

## Base URL

All endpoints are prefixed with `/api/mobile`

## Authentication

The Mobile API uses JWT (JSON Web Token) authentication.

1. **Obtain a token** via `POST /api/login` with credentials:
   ```json
   {
     "username": "your_username",
     "password": "your_password"
   }
   ```

2. **Use the token** in all subsequent requests:
   ```
   Authorization: Bearer <your_jwt_token>
   ```

## Response Format

All API responses follow a standardized JSON structure:

### Success Response
```json
{
  "success": true,
  "message": "Description of the result",
  "data": { ... },
  "meta": { ... }  // Optional: pagination, counts, etc.
}
```

### Error Response
```json
{
  "success": false,
  "message": "Error description",
  "errors": { ... }  // Optional: detailed error data
}
```

### Paginated Response
```json
{
  "success": true,
  "message": "Data retrieved",
  "data": [ ... ],
  "meta": {
    "pagination": {
      "current_page": 1,
      "per_page": 20,
      "total_items": 100,
      "total_pages": 5,
      "has_next_page": true,
      "has_previous_page": false
    }
  }
}
```

## Endpoints

### 1. Health Check (Public)

**GET** `/api/mobile/health`

Check API status - no authentication required.

**Response:**
```json
{
  "success": true,
  "message": "Mobile API is operational",
  "data": {
    "status": "healthy",
    "version": "1.0.0",
    "timestamp": "2026-04-03T20:45:00+00:00"
  }
}
```

---

### 2. List Services

**GET** `/api/mobile/services`

Returns a paginated list of all available catering services.

**Query Parameters:**
- `page` (int, optional): Page number (default: 1)
- `limit` (int, optional): Items per page (default: 20, max: 100)

**Response:**
```json
{
  "success": true,
  "message": "Services retrieved successfully",
  "data": [
    {
      "id": 1,
      "name": "Wedding Package",
      "description": "Complete wedding catering service",
      "event_type": "wedding",
      "base_price": 1500.00,
      "min_guests": 50,
      "max_guests": 300,
      "image": "wedding.jpg"
    }
  ],
  "meta": {
    "pagination": {
      "current_page": 1,
      "per_page": 20,
      "total_items": 10,
      "total_pages": 1,
      "has_next_page": false,
      "has_previous_page": false
    }
  }
}
```

---

### 3. Get Service Details

**GET** `/api/mobile/services/{id}`

Returns detailed information about a specific service.

**Response:**
```json
{
  "success": true,
  "message": "Service retrieved successfully",
  "data": {
    "id": 1,
    "name": "Wedding Package",
    "description": "Complete wedding catering service",
    "event_type": "wedding",
    "base_price": 1500.00,
    "min_guests": 50,
    "max_guests": 300,
    "image": "wedding.jpg",
    "created_at": "2024-01-15 10:30:00",
    "updated_at": "2024-03-20 14:45:00"
  }
}
```

---

### 4. List User Bookings

**GET** `/api/mobile/bookings`

Returns a paginated list of bookings for the authenticated user.

**Query Parameters:**
- `page` (int, optional): Page number (default: 1)
- `limit` (int, optional): Items per page (default: 20)
- `status` (string, optional): Filter by status (`pending`, `confirmed`, `completed`, `cancelled`)

**Response:**
```json
{
  "success": true,
  "message": "Bookings retrieved successfully",
  "data": [
    {
      "id": 1,
      "customer_name": "John Doe",
      "event_date": "2026-05-15",
      "status": "confirmed",
      "guest_count": 100,
      "total_price": 150000.00,
      "service": {
        "id": 1,
        "name": "Wedding Package",
        "event_type": "wedding"
      }
    }
  ],
  "meta": {
    "pagination": { ... }
  }
}
```

---

### 5. Get Booking Details

**GET** `/api/mobile/bookings/{id}`

Returns detailed information about a specific booking. Users can only view their own bookings.

**Response:**
```json
{
  "success": true,
  "message": "Booking retrieved successfully",
  "data": {
    "id": 1,
    "customer_name": "John Doe",
    "event_date": "2026-05-15",
    "status": "confirmed",
    "guest_count": 100,
    "total_price": 150000.00,
    "service": {
      "id": 1,
      "name": "Wedding Package",
      "event_type": "wedding"
    },
    "service_details": {
      "id": 1,
      "name": "Wedding Package",
      "description": "Complete wedding catering service",
      "event_type": "wedding",
      "base_price": 1500.00,
      "min_guests": 50,
      "max_guests": 300,
      "image": "wedding.jpg"
    },
    "created_at": "2026-05-15 10:00:00"
  }
}
```

---

### 6. Create Booking

**POST** `/api/mobile/bookings`

Create a new booking from the mobile app.

**Request Body:**
```json
{
  "service_id": 1,
  "customer_name": "Jane Smith",
  "event_date": "2026-06-20",
  "guest_count": 75
}
```

**Response:**
```json
{
  "success": true,
  "message": "Booking created successfully",
  "data": {
    "id": 2,
    "customer_name": "Jane Smith",
    "event_date": "2026-06-20",
    "status": "pending",
    "guest_count": 75,
    "total_price": 112500.00,
    "service_details": { ... }
  }
}
```

**Validation Rules:**
- `service_id`: Must exist in the database
- `customer_name`: Required, non-empty string
- `event_date`: Required, must be today or a future date (YYYY-MM-DD format)
- `guest_count`: Required, must be between service's min and max guests

---

### 7. Get User Profile

**GET** `/api/mobile/profile`

Returns the authenticated user's profile information.

**Response:**
```json
{
  "success": true,
  "message": "Profile retrieved successfully",
  "data": {
    "id": 1,
    "username": "johndoe",
    "email": "john@example.com",
    "roles": ["ROLE_USER"],
    "status": "active",
    "is_verified": true,
    "created_at": "2024-01-10 08:00:00"
  }
}
```

---

### 8. Get Dashboard Statistics

**GET** `/api/mobile/dashboard`

Returns summary statistics and the next upcoming booking for the authenticated user.

**Response:**
```json
{
  "success": true,
  "message": "Dashboard data retrieved successfully",
  "data": {
    "statistics": {
      "total_bookings": 10,
      "upcoming_bookings": 3,
      "pending_bookings": 1,
      "confirmed_bookings": 2
    },
    "next_booking": {
      "id": 5,
      "customer_name": "Alice Brown",
      "event_date": "2026-04-10",
      "status": "confirmed",
      "guest_count": 50,
      "total_price": 75000.00,
      "service": {
        "id": 2,
        "name": "Birthday Party",
        "event_type": "birthday"
      }
    }
  }
}
```

---

## Error Codes

| HTTP Status | Description |
|-------------|-------------|
| 200 | Success |
| 201 | Created successfully |
| 400 | Bad request / Validation error |
| 401 | Unauthorized / Invalid or missing token |
| 403 | Forbidden / Access denied |
| 404 | Resource not found |
| 500 | Internal server error |

## Mobile App Integration Example

### JavaScript (React Native / Fetch)

```javascript
const API_BASE = 'https://your-domain.com/api/mobile';
const TOKEN = 'your_jwt_token';

// Helper function for API calls
async function apiCall(endpoint, options = {}) {
  const response = await fetch(`${API_BASE}${endpoint}`, {
    ...options,
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${TOKEN}`,
      ...options.headers,
    },
  });
  
  return response.json();
}

// Get services
const services = await apiCall('/services?page=1&limit=10');

// Create booking
const newBooking = await apiCall('/bookings', {
  method: 'POST',
  body: JSON.stringify({
    service_id: 1,
    customer_name: 'John Doe',
    event_date: '2026-05-15',
    guest_count: 100,
  }),
});
```

### Flutter (Dart)

```dart
import 'package:http/http.dart' as http;
import 'dart:convert';

class MobileApiClient {
  static const String baseUrl = 'https://your-domain.com/api/mobile';
  final String token;

  MobileApiClient(this.token);

  Future<Map<String, dynamic>> get(String endpoint) async {
    final response = await http.get(
      Uri.parse('$baseUrl$endpoint'),
      headers: {
        'Authorization': 'Bearer $token',
        'Content-Type': 'application/json',
      },
    );
    return jsonDecode(response.body);
  }

  Future<Map<String, dynamic>> post(String endpoint, Map<String, dynamic> data) async {
    final response = await http.post(
      Uri.parse('$baseUrl$endpoint'),
      headers: {
        'Authorization': 'Bearer $token',
        'Content-Type': 'application/json',
      },
      body: jsonEncode(data),
    );
    return jsonDecode(response.body);
  }
}
```

## Files Created

| File | Description |
|------|-------------|
| `src/Trait/ApiResponseTrait.php` | Standardized JSON response trait |
| `src/Controller/MobileApiController.php` | Mobile API controller with 8 endpoints |
| `docs/mobile-api.md` | This documentation file |

## CORS Support

The API is configured to accept cross-origin requests. Ensure your mobile app sends the appropriate headers for JWT authentication.
