<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com/)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel/)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development/)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

# VetCare Laravel Application

A comprehensive veterinary care management system built with Laravel, featuring appointment booking, doctor management, and pet care tracking.

## Features

- 🔐 **Authentication & Authorization** (Sanctum + Spatie Permissions)
- 👨‍⚕️ **Doctor Management** with specializations and working hours
- 🐕 **Pet Management** with owner relationships
- 📅 **Advanced Appointment Booking System**
- 🗓️ **Calendar Integration** with availability checking
- 🏥 **Medical History Tracking** with diagnoses, treatments, and documents
- 💰 **Comprehensive Billing System** with service-based pricing and payment processing
- 🏥💰 **Automatic Treatment Billing** with instant invoice generation for payable treatments
- 📊 **Comprehensive API** with clean architecture

## 📅 Appointment Booking System

The appointment system is designed with **flexibility** in mind, supporting multiple booking workflows to accommodate different user preferences.

### 🎯 **Three Booking Workflows**

#### **1. Time-First Booking** ⏰
*Perfect for users with time constraints*

**User Journey:** "I can only come at 2:00 PM on Friday - who's available?"

```
User selects time → System shows available doctors → User picks doctor → Books appointment
```

**API Flow:**
```bash
GET /api/available-doctors?date=2024-01-15&time=14:00
# Returns list of doctors available at that specific time

POST /api/appointments
# Books with selected doctor
```

#### **2. Doctor-First Booking** 👨‍⚕️
*Perfect for users with doctor preferences*

**User Journey:** "I want to see Dr. Smith - when is she available?"

```
User selects doctor → System shows available times → User picks time → Books appointment
```

**API Flow:**
```bash
GET /api/calendar/1?date=2024-01-15
# Returns available time slots for Dr. Smith

POST /api/appointments
# Books at selected time
```

#### **3. Browse All Availability** 📋
*Perfect for flexible users*

**User Journey:** "Show me everything available this week"

```
User browses calendar → System shows all slots → User picks doctor + time → Books appointment
```

**API Flow:**
```bash
GET /api/calendar?start_date=2024-01-15&end_date=2024-01-20
# Returns all available slots across all doctors

POST /api/appointments
# Books selected slot
```

### 🔍 **Key Endpoint: `/available-doctors`**

This endpoint is specifically designed for **time-first booking scenarios**:

**Purpose:** Find which doctors are available for a specific time slot

**When to use:**
- User has a preferred time but is flexible on doctor
- Emergency appointments where any available doctor works
- Time-constrained scheduling (e.g., "I can only come during lunch break")

**Example Response:**
```json
{
  "data": {
    "doctors": [
      {
        "id": 1,
        "name": "Dr. Sarah Smith",
        "specialization": "General Veterinarian",
        "slot": {
          "start": "14:00",
          "end": "14:20",
          "date": "2024-01-15"
        }
      }
    ],
    "total_available": 1
  }
}
```

### 📊 **Endpoint Comparison**

| Endpoint | Purpose | Returns | Best For |
|----------|---------|---------|----------|
| `GET /calendar` | Browse all availability | All slots for all doctors | Calendar view, general browsing |
| `GET /available-doctors` | **Time-first booking** | Available doctors for specific time | "I want 2PM Friday - who's free?" |
| `GET /calendar/{doctor}` | **Doctor-first booking** | Available times for specific doctor | "I want Dr. Smith - when is she free?" |

### 🛡️ **Business Rules**

- ✅ **Pet Ownership Validation**: Users can only book for their own pets
- ✅ **Time Availability**: No double-booking, respects doctor schedules
- ✅ **Working Hours**: Appointments only during doctor's working hours
- ✅ **Future Booking**: No past-date appointments
- ✅ **Conflict Prevention**: Automatic overlap detection

## 💰 Billing System

The VetCare application includes a comprehensive billing system for service-based veterinary billing, invoice generation, and payment processing.

### 🎯 **Key Features**

#### **Service-Based Billing**
- **Flexible Pricing**: Fixed, variable, and range-based pricing models
- **Service Categories**: Consultation, diagnostic, treatment, surgery, vaccination, grooming, emergency
- **Equipment Tracking**: Required equipment for each service
- **Duration Estimates**: Service time planning for scheduling

#### **Invoice Generation**
- **Automatic Numbering**: Sequential invoice numbers (INV-YYYY-MM-NNNN)
- **Multi-Item Support**: Multiple services per invoice
- **Tax Calculations**: Configurable tax rates
- **Discount System**: Percentage or fixed amount discounts
- **Status Tracking**: Complete invoice lifecycle management

#### **Payment Processing**
- **Multiple Methods**: Cash, credit/debit cards, bank transfer, online payment, check, mobile payment, insurance
- **Partial Payments**: Support for installment payments
- **Refund Processing**: Full and partial refunds with tracking
- **Fee Tracking**: Processing fees and gateway charges
- **Payment History**: Complete audit trail

#### **Role-Based Access**
- **Admin**: Full system access, statistics, bulk operations
- **Doctor**: Own invoices and patient billing
- **Pet Owner**: View own invoices, payment history, online payments

### 🔗 **Billing API Endpoints**

```bash
# Public Service Browsing
GET /api/services                    # Browse available services
GET /api/services/{service}          # Service details
GET /api/services/category/{category} # Services by category

# Admin Billing Management
POST   /api/admin/services           # Create service
GET    /api/admin/invoices           # List all invoices
POST   /api/admin/invoices           # Create invoice
GET    /api/admin/payments           # List all payments
POST   /api/admin/payments           # Process payment

# Doctor Billing
GET  /api/doctor/invoices           # Doctor's invoices
POST /api/doctor/invoices           # Create invoice
GET  /api/doctor/invoices/statistics # Doctor's billing stats

# Pet Owner Billing
GET  /api/my/invoices               # User's invoices
GET  /api/my/invoices/summary       # Invoice summary
GET  /api/my/invoices/overdue       # Overdue invoices
POST /api/my/payments/online        # Online payment
```

### 💳 **Payment Workflow Example**

```bash
# 1. Browse available services
GET /api/services

# 2. Create invoice (Admin/Doctor)
POST /api/admin/invoices
{
  "pet_id": 1,
  "owner_id": 1,
  "items": [
    {
      "service_id": 1,
      "quantity": 1,
      "unit_price": 75.00
    }
  ]
}

# 3. Process payment (Pet Owner)
POST /api/my/payments/online
{
  "invoice_id": 1,
  "amount": 75.00,
  "payment_token": "stripe_token_here"
}
```

### 📊 **Database Schema**

The billing system includes four main tables:

- **`services`**: Billable veterinary services with flexible pricing
- **`invoices`**: Billing documents with comprehensive financial tracking
- **`invoice_items`**: Individual services on each invoice
- **`payments`**: Payment records with multiple method support

For detailed documentation, see [`docs/BILLING_SYSTEM.md`](docs/BILLING_SYSTEM.md)

## 🏥💰 Automatic Treatment Billing

The VetCare application now includes **automatic invoice generation** when payable treatments are added to medical records. This seamlessly integrates medical care with billing.

### 🎯 **Key Features**

#### **Instant Automatic Billing**
- ✅ **Auto-Invoice Creation**: Invoices generated automatically when treatments with costs are added
- ✅ **Smart Service Mapping**: Treatments automatically mapped to billing services
- ✅ **Real-time Updates**: Invoice amounts update when treatment costs change
- ✅ **Payment Protection**: Prevents changes to paid treatments

#### **Treatment Management with Billing**
- 🔄 **Seamless Integration**: Medical records and billing work together
- 🏷️ **Billing Code Support**: Link treatments to existing services via billing codes
- 📊 **Comprehensive Tracking**: Full audit trail of treatment billing
- 💡 **Smart Defaults**: Automatic service creation for new treatment types

### 🔗 **Treatment Billing API**

```bash
# Create treatment with automatic billing
POST /api/doctor/treatments
{
  "medical_record_id": 1,
  "type": "medication",
  "name": "Antibiotics",
  "cost": 45.00,          # This triggers automatic invoice creation
  "billing_code": "MED-001"
}

# Response includes billing information
{
  "data": {
    "treatment": { ... },
    "billing_info": {
      "invoice_id": 123,
      "invoice_number": "INV-2024-01-0001",
      "amount": 45.00,
      "is_billed": true
    },
    "message": "Treatment created and invoice generated automatically"
  }
}

# List treatments with billing status
GET /api/doctor/treatments

# Get treatment statistics including billing
GET /api/admin/treatments/statistics
```

### 💻 **Frontend Integration Example**

```javascript
// Create treatment with automatic billing notification
const createTreatment = async (treatmentData) => {
  const response = await fetch('/api/doctor/treatments', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${token}`
    },
    body: JSON.stringify({
      medical_record_id: treatmentData.medicalRecordId,
      type: treatmentData.type,
      name: treatmentData.name,
      cost: treatmentData.cost, // Triggers automatic billing
      start_date: treatmentData.startDate
    })
  });
  
  const result = await response.json();
  
  // Show billing notification if invoice was created
  if (result.data.billing_info) {
    showNotification(
      `Invoice ${result.data.billing_info.invoice_number} created automatically for $${result.data.billing_info.amount}`
    );
  }
  
  return result;
};
```

### 🔒 **Business Rules**

- **Automatic Billing**: Only treatments with `cost > 0` trigger invoice creation
- **Payment Protection**: Cannot modify/delete treatments that have been paid
- **Service Integration**: Automatically creates or links to billing services
- **Audit Trail**: Complete logging of all automatic billing actions

For complete documentation, see [`docs/TREATMENT_BILLING.md`](docs/TREATMENT_BILLING.md)

## 🚀 Quick Start

### Prerequisites

- PHP 8.1+
- Composer
- Laravel 10+
- MySQL/SQLite

### Installation

1. **Clone the repository**
```bash
git clone <repository-url>
cd vetcare-laravel
```

2. **Install dependencies**
```bash
composer install
```

3. **Environment setup**
```bash
cp .env.example .env
php artisan key:generate
```

4. **Database setup**
```bash
php artisan migrate
```

5. **Seed sample data**
```bash
# Full dataset (recommended for development)
php artisan db:seed

# OR quick test data (minimal dataset)
php artisan db:seed --class=QuickTestSeeder
```

### 🎯 **Test Data Created**

After seeding, you'll have:

**Full Seeding:**
- 1 Admin user (`admin@vetcare.com`)
- 10 Regular users
- 8 Doctors with various specializations
- 20+ Pets with realistic data
- 30+ Appointments across different statuses
- Doctor availability restrictions

**Quick Test Seeding:**
- 1 Admin (`admin@test.com`)
- 3 Regular users
- 3 Doctors (`doctor1@test.com`, `doctor2@test.com`, `doctor3@test.com`)
- 6 Pets (2 per user)
- 10 Appointments
- Some doctor restrictions

**Default Password:** `password` for all users

## 📚 API Documentation

Comprehensive API documentation is available in [`docs/APPOINTMENT_API.md`](docs/APPOINTMENT_API.md)

### 🔗 **Key Endpoints**

```bash
# Authentication
POST /api/auth/login
POST /api/auth/register

# Calendar & Availability
GET /api/calendar                    # Browse all availability
GET /api/available-doctors           # Time-first booking
GET /api/calendar/{doctor}           # Doctor-first booking

# Appointment Management
POST /api/appointments               # Book appointment
GET /api/appointments                # List user appointments
GET /api/appointments/{id}           # Get specific appointment
PUT /api/appointments/{id}           # Update appointment
PATCH /api/appointments/{id}/cancel  # Cancel appointment

# Utility
GET /api/appointments/upcoming/list  # Upcoming appointments
GET /api/appointments/history/list   # Appointment history
```

## 🏗️ Architecture

### **Clean Architecture Principles**

- **Data Layer**: Spatie Laravel Data for type-safe DTOs
- **Service Layer**: Business logic separation
- **Controller Layer**: HTTP request handling only
- **Model Layer**: Eloquent relationships and scopes

### **Key Components**

```
app/
├── Data/Appointments/           # DTOs with validation
│   ├── BookAppointmentData.php
│   ├── UpdateAppointmentData.php
│   └── AppointmentResource.php
├── Services/                    # Business logic
│   ├── AppointmentBookingService.php
│   ├── AppointmentCalendarService.php
│   └── DoctorAvailabilityService.php
├── Http/Controllers/           # API endpoints
│   └── AppointmentController.php
└── Models/                     # Eloquent models
    ├── Appointment.php
    ├── Doctor.php
    └── Pet.php
```

## 🧪 Testing

### **Manual Testing with Seeded Data**

1. **Login as a user:**
```bash
POST /api/auth/login
{
  "email": "user@example.com",
  "password": "password"
}
```

2. **Check calendar availability:**
```bash
GET /api/calendar?start_date=2024-01-15&end_date=2024-01-20
```

3. **Find available doctors for specific time:**
```bash
GET /api/available-doctors?date=2024-01-15&time=09:00
```

4. **Book an appointment:**
```bash
POST /api/appointments
{
  "doctor_id": 1,
  "pet_id": 1,
  "date": "2024-01-15",
  "time": "09:00",
  "appointment_type": "regular",
  "duration": 30,
  "notes": "Regular checkup"
}
```

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests if applicable
5. Submit a pull request

## 📄 License

This project is open-sourced software licensed under the [MIT license](LICENSE).

---

## 💡 For Frontend Developers

The appointment system provides **maximum flexibility** for UI/UX design:

- **Time-picker first** → Use `/available-doctors`
- **Doctor-picker first** → Use `/calendar/{doctor}`
- **Calendar view** → Use `/calendar`

All endpoints return consistent JSON responses with proper error handling. See [`docs/APPOINTMENT_API.md`](docs/APPOINTMENT_API.md) for complete implementation examples.
