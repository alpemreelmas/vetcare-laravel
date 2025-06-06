# Pet Management API Documentation

## Overview
This document describes the pet management API endpoints for the VetCare application. The API is **separated into two distinct controllers** to maintain proper access control and functionality separation.

## Authentication
All pet management endpoints require authentication using Sanctum tokens:
```
Authorization: Bearer {your-token}
```

## Controller Separation

### 🔐 **Access Control Summary**

| Controller | Access Level | Scope | Purpose |
|------------|--------------|-------|---------|
| **PetController** | Authenticated Users | Own pets only | Personal pet management |
| **Admin/PetController** | Admin Only | All pets | System-wide pet administration |

---

## 👤 User Pet Management (`/api/pets`)

**Access:** Authenticated users can only manage their own pets.

### 1. List User's Pets
Get all pets belonging to the authenticated user.

**Endpoint:** `GET /api/pets`

**Example Response:**
```json
{
  "is_success": true,
  "message": "Pets retrieved successfully",
  "data": {
    "pets": [
      {
        "id": 1,
        "name": "Fluffy",
        "species": "Cat",
        "breed": "Persian",
        "date_of_birth": "2020-05-15",
        "weight": "4.50",
        "gender": "female",
        "age": 3,
        "owner_id": 5,
        "owner": {
          "id": 5,
          "name": "John Doe",
          "email": "john@example.com"
        }
      }
    ],
    "total": 1
  }
}
```

### 2. Create New Pet
Add a new pet for the authenticated user.

**Endpoint:** `POST /api/pets`

**Request Body:**
```json
{
  "name": "Buddy",
  "species": "Dog",
  "breed": "Golden Retriever",
  "date_of_birth": "2021-03-10",
  "weight": 25.5,
  "gender": "male"
}
```

**Validation Rules:**
- `name`: required, string, max 255 characters
- `species`: required, string, max 255 characters
- `breed`: required, string, max 255 characters
- `date_of_birth`: optional, date, cannot be in the future
- `weight`: optional, numeric, min 0, max 999.99
- `gender`: optional, enum (male, female, other)

### 3. View Specific Pet
Get details of a specific pet (only if owned by user).

**Endpoint:** `GET /api/pets/{pet_id}`

### 4. Update Pet
Update pet information (only if owned by user).

**Endpoint:** `PUT /api/pets/{pet_id}`

**Request Body:** Same as create, all fields optional

### 5. Delete Pet
Delete a pet (only if owned by user).

**Endpoint:** `DELETE /api/pets/{pet_id}`

---

## 👨‍💼 Admin Pet Management (`/api/admin/pets`)

**Access:** Admin users only - can manage all pets in the system.

### 1. List All Pets (Admin)
Get all pets in the system with filtering and pagination.

**Endpoint:** `GET /api/admin/pets`

**Query Parameters:**
- `owner_id`: Filter by owner ID
- `species`: Filter by species (partial match)
- `breed`: Filter by breed (partial match)
- `search`: Search in name, species, or breed
- `per_page`: Results per page (default: 15)
- `page`: Page number

**Example Request:**
```bash
GET /api/admin/pets?species=dog&per_page=10&page=1
```

**Example Response:**
```json
{
  "is_success": true,
  "message": "All pets retrieved successfully",
  "data": {
    "pets": [...],
    "pagination": {
      "current_page": 1,
      "last_page": 5,
      "per_page": 10,
      "total": 47
    },
    "filters_applied": {
      "species": "dog"
    }
  }
}
```

### 2. Create Pet for Any User (Admin)
Create a pet for any user in the system.

**Endpoint:** `POST /api/admin/pets`

**Request Body:**
```json
{
  "owner_id": 5,
  "name": "Max",
  "species": "Dog",
  "breed": "Labrador",
  "date_of_birth": "2022-01-15",
  "weight": 30.0,
  "gender": "male"
}
```

**Additional Validation:**
- `owner_id`: required, must exist in users table

### 3. View Any Pet (Admin)
Get detailed information about any pet, including appointments.

**Endpoint:** `GET /api/admin/pets/{pet_id}`

**Response includes:**
- Pet details
- Owner information
- Appointment history with doctor details

### 4. Update Any Pet (Admin)
Update any pet's information, including changing ownership.

**Endpoint:** `PUT /api/admin/pets/{pet_id}`

**Request Body:**
```json
{
  "owner_id": 8,
  "name": "Updated Name",
  "species": "Cat"
}
```

### 5. Delete Any Pet (Admin)
Delete any pet from the system.

**Endpoint:** `DELETE /api/admin/pets/{pet_id}`

**Business Rule:** Cannot delete pets with existing appointments.

### 6. Get Pets by Owner (Admin)
Get all pets belonging to a specific user.

**Endpoint:** `GET /api/admin/pets/owner/{user_id}`

**Example Response:**
```json
{
  "is_success": true,
  "message": "Owner pets retrieved successfully",
  "data": {
    "owner": {
      "id": 5,
      "name": "John Doe",
      "email": "john@example.com"
    },
    "pets": [...],
    "total": 3
  }
}
```

### 7. Pet Statistics (Admin)
Get system-wide pet statistics.

**Endpoint:** `GET /api/admin/pets/statistics`

**Example Response:**
```json
{
  "is_success": true,
  "message": "Pet statistics retrieved successfully",
  "data": {
    "total_pets": 156,
    "pets_by_species": [
      {"species": "Dog", "count": 89},
      {"species": "Cat", "count": 67}
    ],
    "recent_registrations": 12,
    "pets_with_appointments": 134
  }
}
```

---

## 🔒 Security Features

### User Controller Security:
- ✅ **Ownership Validation**: Users can only access their own pets
- ✅ **Automatic Owner Assignment**: `owner_id` is automatically set to authenticated user
- ✅ **404 for Unauthorized Access**: Returns "not found" instead of "forbidden" for security

### Admin Controller Security:
- ✅ **Role-Based Access**: Only users with 'admin' role can access
- ✅ **Appointment Validation**: Prevents deletion of pets with existing appointments
- ✅ **Owner Validation**: Ensures target owners exist when creating/updating pets

---

## 📊 Business Rules

1. **Pet Ownership**: Users can only manage pets they own
2. **Admin Override**: Admins can manage any pet in the system
3. **Appointment Protection**: Pets with appointments cannot be deleted
4. **Data Integrity**: Owner changes are validated to ensure target users exist
5. **Age Calculation**: Pet age is automatically calculated from date of birth

---

## 🚨 Error Responses

### User Access Violations (404):
```json
{
  "is_success": false,
  "message": "Pet not found or you do not own this pet",
  "data": null
}
```

### Admin Authorization (403):
```json
{
  "is_success": false,
  "message": "This action is unauthorized",
  "data": null
}
```

### Validation Errors (422):
```json
{
  "is_success": false,
  "message": "The given data was invalid",
  "data": {
    "errors": {
      "owner_id": ["The selected owner does not exist."]
    }
  }
}
```

### Business Rule Violations (422):
```json
{
  "is_success": false,
  "message": "Cannot delete pet with existing appointments. Please cancel or complete all appointments first.",
  "data": null
}
```

---

## 🎯 Frontend Implementation Tips

### For User Interfaces:
```javascript
// List user's pets
const userPets = await fetch('/api/pets');

// Create new pet (owner_id is automatic)
const newPet = await fetch('/api/pets', {
  method: 'POST',
  body: JSON.stringify({
    name: 'Buddy',
    species: 'Dog',
    breed: 'Golden Retriever'
  })
});
```

### For Admin Interfaces:
```javascript
// List all pets with filters
const allPets = await fetch('/api/admin/pets?search=golden&per_page=20');

// Create pet for specific user
const adminCreatePet = await fetch('/api/admin/pets', {
  method: 'POST',
  body: JSON.stringify({
    owner_id: 5,
    name: 'Max',
    species: 'Dog'
  })
});

// Get statistics for dashboard
const stats = await fetch('/api/admin/pets/statistics');
```

---

## 📋 Route Summary

### User Routes (`auth:sanctum`):
```
GET    /api/pets           # List user's pets
POST   /api/pets           # Create pet for user
GET    /api/pets/{pet}     # Show user's pet
PUT    /api/pets/{pet}     # Update user's pet
DELETE /api/pets/{pet}     # Delete user's pet
```

### Admin Routes (`auth:sanctum + role:admin`):
```
GET    /api/admin/pets                 # List all pets
POST   /api/admin/pets                 # Create pet for any user
GET    /api/admin/pets/statistics      # Get pet statistics
GET    /api/admin/pets/owner/{user}    # Get pets by owner
GET    /api/admin/pets/{pet}           # Show any pet
PUT    /api/admin/pets/{pet}           # Update any pet
DELETE /api/admin/pets/{pet}           # Delete any pet
``` 