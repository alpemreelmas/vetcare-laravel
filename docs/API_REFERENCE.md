# 🔗 VetCare API Reference

## 🎯 **Overview**

The VetCare API is a RESTful API built with Laravel that provides comprehensive veterinary management functionality. The API supports role-based access control, appointment booking, medical record management, billing, and document handling.

## 🔐 **Authentication**

### **Authentication Method**
The API uses Laravel Sanctum for token-based authentication.

### **Authentication Endpoints**

#### **Register User**
```http
POST /api/auth/register
Content-Type: application/json

{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "password",
  "password_confirmation": "password"
}
```

**Response:**
```json
{
  "success": true,
  "message": "User registered successfully",
  "data": {
    "user": {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com"
    },
    "token": "1|abc123..."
  }
}
```

#### **Login**
```http
POST /api/auth/login
Content-Type: application/json

{
  "email": "john@example.com",
  "password": "password"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "user": {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com",
      "roles": ["user"]
    },
    "token": "1|abc123..."
  }
}
```

#### **Logout**
```http
POST /api/auth/logout
Authorization: Bearer {token}
```

#### **Get Profile**
```http
GET /api/auth/profile
Authorization: Bearer {token}
```

## 🏥 **Appointment Management**

### **Public Endpoints (No Authentication Required)**

#### **Browse Calendar Availability**
```http
GET /api/calendar?start_date=2024-01-15&end_date=2024-01-20
```

**Response:**
```json
{
  "success": true,
  "data": {
    "availability": [
      {
        "date": "2024-01-15",
        "slots": [
          {
            "doctor_id": 1,
            "doctor_name": "Dr. Sarah Smith",
            "start_time": "09:00",
            "end_time": "09:30",
            "available": true
          }
        ]
      }
    ]
  }
}
```

#### **Get Available Doctors for Specific Time**
```http
GET /api/available-doctors?date=2024-01-15&time=14:00&duration=30
```

**Response:**
```json
{
  "success": true,
  "data": {
    "doctors": [
      {
        "id": 1,
        "name": "Dr. Sarah Smith",
        "specialization": "General Veterinarian",
        "slot": {
          "start": "14:00",
          "end": "14:30",
          "date": "2024-01-15"
        }
      }
    ],
    "total_available": 1
  }
}
```

#### **Get Doctor's Available Slots**
```http
GET /api/calendar/1?date=2024-01-15
```

### **Authenticated Appointment Endpoints**

#### **Book Appointment**
```http
POST /api/appointments
Authorization: Bearer {token}
Content-Type: application/json

{
  "doctor_id": 1,
  "pet_id": 1,
  "date": "2024-01-15",
  "time": "14:00",
  "duration": 30,
  "appointment_type": "regular",
  "notes": "Regular checkup"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Appointment booked successfully",
  "data": {
    "appointment": {
      "id": 1,
      "doctor_id": 1,
      "pet_id": 1,
      "appointment_date": "2024-01-15",
      "appointment_time": "14:00:00",
      "duration": 30,
      "status": "scheduled",
      "doctor": {
        "id": 1,
        "name": "Dr. Sarah Smith"
      },
      "pet": {
        "id": 1,
        "name": "Buddy"
      }
    }
  }
}
```

#### **List User Appointments**
```http
GET /api/appointments?status=scheduled&per_page=10
Authorization: Bearer {token}
```

#### **Update Appointment**
```http
PUT /api/appointments/1
Authorization: Bearer {token}
Content-Type: application/json

{
  "date": "2024-01-16",
  "time": "15:00",
  "notes": "Updated appointment"
}
```

#### **Cancel Appointment**
```http
PATCH /api/appointments/1/cancel
Authorization: Bearer {token}
```

#### **Get Upcoming Appointments**
```http
GET /api/appointments/upcoming/list
Authorization: Bearer {token}
```

#### **Get Appointment History**
```http
GET /api/appointments/history/list
Authorization: Bearer {token}
```

## 🐕 **Pet Management**

### **User Pet Management**

#### **List User's Pets**
```http
GET /api/pets
Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "pets": [
      {
        "id": 1,
        "name": "Buddy",
        "species": "dog",
        "breed": "Golden Retriever",
        "date_of_birth": "2020-05-15",
        "gender": "male",
        "weight": 25.5,
        "microchip_number": "123456789"
      }
    ]
  }
}
```

#### **Create Pet**
```http
POST /api/pets
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "Buddy",
  "species": "dog",
  "breed": "Golden Retriever",
  "date_of_birth": "2020-05-15",
  "gender": "male",
  "weight": 25.5,
  "color": "Golden",
  "microchip_number": "123456789"
}
```

#### **Update Pet**
```http
PUT /api/pets/1
Authorization: Bearer {token}
Content-Type: application/json

{
  "weight": 26.0,
  "color": "Golden Brown"
}
```

#### **Delete Pet**
```http
DELETE /api/pets/1
Authorization: Bearer {token}
```

### **Admin Pet Management**

#### **List All Pets (Admin)**
```http
GET /api/admin/pets?search=buddy&species=dog&per_page=15
Authorization: Bearer {admin_token}
```

#### **Get Pet Statistics (Admin)**
```http
GET /api/admin/pets/statistics
Authorization: Bearer {admin_token}
```

## 🏥 **Medical Records Management**

### **Doctor Medical Records**

#### **List Doctor's Medical Records**
```http
GET /api/doctor/medical-records?status=completed&per_page=15
Authorization: Bearer {doctor_token}
```

#### **Create Medical Record**
```http
POST /api/doctor/medical-records
Authorization: Bearer {doctor_token}
Content-Type: application/json

{
  "pet_id": 1,
  "visit_date": "2024-01-15",
  "visit_type": "routine_checkup",
  "chief_complaint": "Annual checkup",
  "physical_examination": "Normal examination findings",
  "vital_signs": {
    "temperature": 38.5,
    "weight": 25.5,
    "heart_rate": 120
  },
  "assessment": "Healthy dog",
  "plan": "Continue current diet and exercise"
}
```

#### **Create Medical Record from Appointment**
```http
POST /api/doctor/medical-records/appointment/1/create
Authorization: Bearer {doctor_token}
```

#### **Get Pet's Medical History**
```http
GET /api/doctor/medical-records/pet/1/history
Authorization: Bearer {doctor_token}
```

### **User Medical History Access**

#### **Get Pet's Medical History (Owner)**
```http
GET /api/my/pets/1/medical-history
Authorization: Bearer {token}
```

#### **Get Pet's Active Diagnoses**
```http
GET /api/my/pets/1/active-diagnoses
Authorization: Bearer {token}
```

#### **Get Pet's Current Treatments**
```http
GET /api/my/pets/1/current-treatments
Authorization: Bearer {token}
```

## 💊 **Treatment Management**

### **Doctor Treatment Management**

#### **List Doctor's Treatments**
```http
GET /api/doctor/treatments?type=medication&status=active
Authorization: Bearer {doctor_token}
```

#### **Create Treatment with Automatic Billing**
```http
POST /api/doctor/treatments
Authorization: Bearer {doctor_token}
Content-Type: application/json

{
  "medical_record_id": 1,
  "type": "medication",
  "name": "Antibiotics",
  "medication_name": "Amoxicillin",
  "dosage": "250mg",
  "frequency": "Twice daily",
  "duration_days": 7,
  "start_date": "2024-01-15",
  "cost": 45.00,
  "billing_code": "MED-001"
}
```

**Response (with automatic billing):**
```json
{
  "success": true,
  "message": "Treatment created and invoice generated automatically",
  "data": {
    "treatment": {
      "id": 1,
      "name": "Antibiotics",
      "cost": 45.00,
      "status": "prescribed"
    },
    "billing_info": {
      "invoice_id": 123,
      "invoice_number": "INV-2024-01-0001",
      "amount": 45.00,
      "is_billed": true
    }
  }
}
```

#### **Mark Treatment as Administered**
```http
PATCH /api/doctor/treatments/1/administered
Authorization: Bearer {doctor_token}
```

#### **Mark Treatment as Completed**
```http
PATCH /api/doctor/treatments/1/completed
Authorization: Bearer {doctor_token}
```

## 📄 **Medical Documents**

### **Doctor Document Management**

#### **Upload Medical Document**
```http
POST /api/doctor/medical-documents
Authorization: Bearer {doctor_token}
Content-Type: multipart/form-data

{
  "medical_record_id": 1,
  "pet_id": 1,
  "document_type": "lab_result",
  "title": "Blood Test Results",
  "description": "Complete blood count",
  "document_date": "2024-01-15",
  "visibility": "owner_and_doctor",
  "file": [binary file data]
}
```

#### **Download Document**
```http
GET /api/doctor/medical-documents/1/download
Authorization: Bearer {doctor_token}
```

### **User Document Access**

#### **Get Pet's Documents**
```http
GET /api/my/pets/1/medical-documents
Authorization: Bearer {token}
```

#### **Download Document (Owner)**
```http
GET /api/my/pets/1/medical-documents/1/download
Authorization: Bearer {token}
```

## 💰 **Billing System**

### **Public Service Browsing**

#### **Browse Available Services**
```http
GET /api/services?category=consultation&active=true
```

**Response:**
```json
{
  "success": true,
  "data": {
    "services": [
      {
        "id": 1,
        "name": "General Consultation",
        "category": "consultation",
        "base_price": 75.00,
        "pricing_type": "fixed",
        "duration_minutes": 30,
        "service_code": "CONS-001"
      }
    ]
  }
}
```

#### **Get Service Details**
```http
GET /api/services/1
```

#### **Get Services by Category**
```http
GET /api/services/category/consultation
```

### **Invoice Management**

#### **Admin Invoice Management**
```http
GET /api/admin/invoices?status=unpaid&per_page=15
Authorization: Bearer {admin_token}
```

#### **Create Invoice (Admin/Doctor)**
```http
POST /api/admin/invoices
Authorization: Bearer {admin_token}
Content-Type: application/json

{
  "pet_id": 1,
  "owner_id": 1,
  "doctor_id": 1,
  "service_date": "2024-01-15",
  "due_date": "2024-02-15",
  "items": [
    {
      "service_id": 1,
      "quantity": 1,
      "unit_price": 75.00,
      "description": "General consultation"
    }
  ],
  "tax_rate": 10.0,
  "notes": "Regular checkup invoice"
}
```

#### **User Invoice Access**
```http
GET /api/my/invoices?status=unpaid
Authorization: Bearer {token}
```

#### **Get Invoice Summary**
```http
GET /api/my/invoices/summary
Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "total_invoices": 5,
    "total_amount": 375.00,
    "paid_amount": 225.00,
    "outstanding_amount": 150.00,
    "overdue_count": 1,
    "overdue_amount": 75.00
  }
}
```

### **Payment Processing**

#### **Process Online Payment**
```http
POST /api/my/payments/online
Authorization: Bearer {token}
Content-Type: application/json

{
  "invoice_id": 1,
  "amount": 75.00,
  "payment_method": "credit_card",
  "payment_token": "stripe_token_here",
  "gateway": "stripe"
}
```

#### **Get Payment History**
```http
GET /api/my/invoices/payment-history
Authorization: Bearer {token}
```

#### **Process Refund (Admin)**
```http
POST /api/admin/payments/1/refund
Authorization: Bearer {admin_token}
Content-Type: application/json

{
  "amount": 75.00,
  "reason": "Service not provided"
}
```

## 👨‍⚕️ **Doctor Management (Admin)**

#### **List Doctors**
```http
GET /api/doctors
Authorization: Bearer {admin_token}
```

#### **Create Doctor**
```http
POST /api/doctors
Authorization: Bearer {admin_token}
Content-Type: application/json

{
  "user_id": 5,
  "specialization": "Emergency Veterinarian",
  "license_number": "VET123456",
  "working_hours": {
    "monday": {"start": "09:00", "end": "17:00"},
    "tuesday": {"start": "09:00", "end": "17:00"},
    "wednesday": {"start": "09:00", "end": "17:00"},
    "thursday": {"start": "09:00", "end": "17:00"},
    "friday": {"start": "09:00", "end": "17:00"},
    "saturday": null,
    "sunday": null
  }
}
```

## 📊 **Statistics and Reports**

### **Admin Statistics**

#### **Treatment Statistics**
```http
GET /api/admin/treatments/statistics
Authorization: Bearer {admin_token}
```

#### **Invoice Statistics**
```http
GET /api/admin/invoices/statistics
Authorization: Bearer {admin_token}
```

#### **Payment Statistics**
```http
GET /api/admin/payments/statistics
Authorization: Bearer {admin_token}
```

### **Doctor Statistics**

#### **Doctor's Medical Records Statistics**
```http
GET /api/doctor/medical-records/statistics
Authorization: Bearer {doctor_token}
```

#### **Doctor's Invoice Statistics**
```http
GET /api/doctor/invoices/statistics
Authorization: Bearer {doctor_token}
```

## 🔒 **Role-Based Access Control**

### **Roles**
- **admin**: Full system access
- **doctor**: Medical professional access
- **user**: Pet owner access

### **Middleware Usage**
```php
// Admin only
Route::middleware(['auth:sanctum', 'role:admin'])

// Doctor only  
Route::middleware(['auth:sanctum', 'role:doctor'])

// Authenticated users
Route::middleware('auth:sanctum')
```

## 📝 **Standard Response Format**

### **Success Response**
```json
{
  "success": true,
  "message": "Operation completed successfully",
  "data": {
    // Response data
  }
}
```

### **Error Response**
```json
{
  "success": false,
  "message": "Error description",
  "errors": {
    "field": ["Validation error message"]
  }
}
```

### **Validation Error Response**
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "email": ["The email field is required."],
    "password": ["The password must be at least 8 characters."]
  }
}
```

## 🔍 **Query Parameters**

### **Common Parameters**
- `per_page`: Number of items per page (default: 15)
- `page`: Page number for pagination
- `search`: Search term for filtering
- `sort_by`: Field to sort by
- `sort_order`: asc or desc

### **Date Filtering**
- `start_date`: Start date for date range filtering
- `end_date`: End date for date range filtering
- `date`: Specific date filtering

### **Status Filtering**
- `status`: Filter by status field
- `active`: Filter active/inactive items
- `archived`: Include/exclude archived items

## 🚀 **Rate Limiting**

The API implements rate limiting to prevent abuse:
- **Authentication endpoints**: 5 requests per minute
- **General API endpoints**: 60 requests per minute
- **File upload endpoints**: 10 requests per minute

## 📱 **CORS Configuration**

The API supports Cross-Origin Resource Sharing (CORS) for web applications:
- Allowed origins: Configurable in environment
- Allowed methods: GET, POST, PUT, PATCH, DELETE
- Allowed headers: Authorization, Content-Type, Accept

## 🔧 **Error Codes**

### **HTTP Status Codes**
- `200`: Success
- `201`: Created
- `400`: Bad Request
- `401`: Unauthorized
- `403`: Forbidden
- `404`: Not Found
- `422`: Validation Error
- `429`: Too Many Requests
- `500`: Internal Server Error

### **Custom Error Messages**
- Pet ownership validation
- Appointment availability conflicts
- Payment processing errors
- File upload restrictions
- Role permission violations

---

This API reference provides comprehensive documentation for integrating with the VetCare veterinary management system. For additional examples and implementation details, refer to the specific feature documentation files. 