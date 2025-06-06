# Appointment Booking API Documentation

## Overview
This document describes the appointment booking API endpoints for the VetCare application. The API allows users to book, update, cancel, and manage veterinary appointments.

## Authentication
All appointment booking endpoints require authentication using Sanctum tokens. Include the token in the Authorization header:
```
Authorization: Bearer {your-token}
```

## Base URL
```
/api
```

## Booking Workflow Overview

The appointment system supports **two main booking workflows** to provide maximum flexibility:

### **Workflow 1: Time-First Booking** 
*"I want a specific time, show me available doctors"*
1. User selects desired date/time → `GET /available-doctors`
2. System shows available doctors for that slot
3. User picks a doctor → `POST /appointments`

### **Workflow 2: Doctor-First Booking**
*"I want a specific doctor, show me available times"*
1. User selects desired doctor → `GET /calendar/{doctor_id}`
2. System shows available time slots for that doctor
3. User picks a time → `POST /appointments`

### **Workflow 3: Browse All Availability**
*"Show me everything available this week"*
1. User browses calendar → `GET /calendar`
2. System shows all available slots across all doctors
3. User picks doctor + time → `POST /appointments`

## Endpoints

### 1. Get Calendar Availability
Get available appointment slots for all doctors within a date range.

**Endpoint:** `GET /calendar`

**Use Case:** Browse all available appointments across all doctors for a date range.

**Parameters:**
- `start_date` (required): Start date in Y-m-d format
- `end_date` (required): End date in Y-m-d format

**Example Request:**
```bash
GET /api/calendar?start_date=2024-01-15&end_date=2024-01-20
```

**Example Response:**
```json
{
  "is_success": true,
  "message": "Calendar availability retrieved successfully",
  "data": {
    "calendar": [
      {
        "date": "2024-01-15",
        "day_name": "Monday",
        "available_slots": [
          {
            "time": "09:00",
            "time_range": "09:00 - 09:20",
            "available_count": 3,
            "total_doctors": 5
          }
        ],
        "total_available_slots": 15
      }
    ],
    "date_range": {
      "start": "2024-01-15",
      "end": "2024-01-20"
    }
  }
}
```

### 2. Get Available Doctors for Specific Slot ⭐
Get doctors available for a specific date and time.

**Endpoint:** `GET /available-doctors`

**Use Case:** When a user has a preferred time slot and wants to see which doctors are available at that time.

**Perfect for:** 
- "I can only come at 2:00 PM on Friday - who's available?"
- Time-constrained users who need flexibility on doctor choice
- Emergency appointments where any available doctor is acceptable

**Parameters:**
- `date` (required): Date in Y-m-d format
- `time` (required): Time in H:i format (24-hour format)

**Example Request:**
```bash
GET /api/available-doctors?date=2024-01-15&time=09:00
```

**Example Response:**
```json
{
  "is_success": true,
  "message": "Available doctors retrieved successfully",
  "data": {
    "doctors": [
      {
        "id": 1,
        "name": "Dr. Sarah Smith",
        "specialization": "General Veterinarian",
        "working_hours": "9:00-17:00",
        "slot": {
          "start": "09:00",
          "end": "09:20",
          "date": "2024-01-15"
        }
      },
      {
        "id": 3,
        "name": "Dr. Mike Johnson",
        "specialization": "Small Animal Veterinarian", 
        "working_hours": "8:00-16:00",
        "slot": {
          "start": "09:00",
          "end": "09:20",
          "date": "2024-01-15"
        }
      }
    ],
    "requested_slot": {
      "date": "2024-01-15",
      "time": "09:00",
      "duration": "20 minutes"
    },
    "total_available": 2
  }
}
```

**Frontend Implementation Tips:**
```javascript
// Example: User selects time first
const checkAvailableDoctors = async (date, time) => {
  const response = await fetch(`/api/available-doctors?date=${date}&time=${time}`);
  const data = await response.json();
  
  if (data.data.total_available === 0) {
    showMessage("No doctors available at this time. Please choose a different slot.");
  } else {
    displayDoctorOptions(data.data.doctors);
  }
};
```

### 3. Get Available Slots for Specific Doctor
Get available time slots for a specific doctor.

**Endpoint:** `GET /calendar/{doctor_id}`

**Use Case:** When a user has a preferred doctor and wants to see their available time slots.

**Perfect for:**
- "I want to see Dr. Smith specifically"
- Follow-up appointments with the same doctor
- Users who have doctor preferences

**Parameters:**
- `date` (required): Date in Y-m-d format

**Example Request:**
```bash
GET /api/calendar/1?date=2024-01-15
```

**Example Response:**
```json
{
  "is_success": true,
  "message": "Available slots retrieved successfully",
  "data": {
    "doctor": {
      "id": 1,
      "name": "Dr. Sarah Smith",
      "specialization": "General Veterinarian"
    },
    "available_slots": [
      {
        "start": "09:00",
        "end": "09:20"
      },
      {
        "start": "09:20", 
        "end": "09:40"
      },
      {
        "start": "10:00",
        "end": "10:20"
      }
    ]
  }
}
```

## Endpoint Comparison Table

| Endpoint | When to Use | Returns | Best For |
|----------|-------------|---------|----------|
| `GET /calendar` | Browse all availability | All slots for all doctors | General browsing, calendar view |
| `GET /available-doctors` | **Time-first booking** | Available doctors for specific time | "I want 2PM Friday - who's free?" |
| `GET /calendar/{doctor}` | **Doctor-first booking** | Available times for specific doctor | "I want Dr. Smith - when is she free?" |

### 4. Book New Appointment
Create a new appointment booking.

**Endpoint:** `POST /appointments`

**Request Body:**
```json
{
  "doctor_id": 1,
  "pet_id": 2,
  "date": "2024-01-15",
  "time": "09:00",
  "appointment_type": "regular",
  "duration": 30,
  "notes": "Regular checkup for my cat"
}
```

**Validation Rules:**
- `doctor_id`: required, integer, must exist in doctors table
- `pet_id`: required, integer, must exist in pets table and belong to authenticated user
- `date`: required, date format Y-m-d, must be today or future date
- `time`: required, time format H:i
- `appointment_type`: required, one of: regular, emergency, surgery, vaccination, checkup, consultation
- `duration`: required, integer, between 15-120 minutes
- `notes`: optional, string, max 1000 characters

**Example Response:**
```json
{
  "is_success": true,
  "message": "Appointment booked successfully",
  "data": {
    "appointment": {
      "id": 123,
      "doctor_id": 1,
      "doctor_name": "Dr. John Smith",
      "doctor_specialization": "General Veterinarian",
      "user_id": 5,
      "user_name": "Jane Doe",
      "user_email": "jane@example.com",
      "pet_id": 2,
      "pet_name": "Fluffy",
      "pet_species": "Cat",
      "pet_breed": "Persian",
      "start_datetime": "2024-01-15 09:00:00",
      "end_datetime": "2024-01-15 09:30:00",
      "appointment_type": "regular",
      "duration": 30,
      "notes": "Regular checkup for my cat",
      "status": "pending",
      "created_at": "2024-01-10 10:30:00",
      "updated_at": "2024-01-10 10:30:00"
    }
  }
}
```

### 5. List User Appointments
Get a list of user's appointments with optional filtering.

**Endpoint:** `GET /appointments`

**Query Parameters (all optional):**
- `doctor_id`: Filter by doctor ID
- `pet_id`: Filter by pet ID
- `start_date`: Filter appointments from this date
- `end_date`: Filter appointments until this date
- `status`: Filter by status (pending, confirmed, completed, cancelled, no-show)
- `appointment_type`: Filter by appointment type
- `per_page`: Number of results per page (default: 15, max: 100)
- `page`: Page number (default: 1)

**Example Request:**
```bash
GET /api/appointments?status=pending&per_page=10
```

### 6. Get Specific Appointment
Get details of a specific appointment.

**Endpoint:** `GET /appointments/{appointment_id}`

**Example Request:**
```bash
GET /api/appointments/123
```

### 7. Update Appointment
Update an existing appointment.

**Endpoint:** `PUT /appointments/{appointment_id}`

**Request Body (all fields optional):**
```json
{
  "doctor_id": 2,
  "pet_id": 3,
  "date": "2024-01-16",
  "time": "10:00",
  "appointment_type": "checkup",
  "duration": 45,
  "status": "confirmed",
  "notes": "Updated notes"
}
```

### 8. Cancel Appointment
Cancel an existing appointment.

**Endpoint:** `PATCH /appointments/{appointment_id}/cancel`

**Example Request:**
```bash
PATCH /api/appointments/123/cancel
```

### 9. Delete Appointment
Permanently delete an appointment (admin only or appointment owner).

**Endpoint:** `DELETE /appointments/{appointment_id}`

### 10. Get Upcoming Appointments
Get user's upcoming appointments (next 10).

**Endpoint:** `GET /appointments/upcoming/list`

**Example Response:**
```json
{
  "is_success": true,
  "message": "Upcoming appointments retrieved successfully",
  "data": {
    "appointments": [...],
    "total": 3
  }
}
```

### 11. Get Appointment History
Get user's past appointments with pagination.

**Endpoint:** `GET /appointments/history/list`

**Query Parameters:**
- `page`: Page number (default: 1)

## Frontend Implementation Examples

### Example 1: Time-First Booking Flow
```javascript
// Step 1: User selects date and time
const selectedDate = "2024-01-15";
const selectedTime = "14:00";

// Step 2: Check available doctors
const availableDoctors = await fetch(`/api/available-doctors?date=${selectedDate}&time=${selectedTime}`);
const doctorsData = await availableDoctors.json();

// Step 3: Show doctor options to user
if (doctorsData.data.total_available > 0) {
  displayDoctorSelection(doctorsData.data.doctors);
} else {
  showNoAvailabilityMessage();
}

// Step 4: Book appointment with selected doctor
const bookAppointment = async (doctorId, petId) => {
  const response = await fetch('/api/appointments', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      doctor_id: doctorId,
      pet_id: petId,
      date: selectedDate,
      time: selectedTime,
      appointment_type: "regular",
      duration: 30
    })
  });
};
```

### Example 2: Doctor-First Booking Flow
```javascript
// Step 1: User selects doctor
const selectedDoctorId = 1;
const selectedDate = "2024-01-15";

// Step 2: Get available slots for that doctor
const availableSlots = await fetch(`/api/calendar/${selectedDoctorId}?date=${selectedDate}`);
const slotsData = await availableSlots.json();

// Step 3: Show time options to user
displayTimeSlotSelection(slotsData.data.available_slots);

// Step 4: Book appointment with selected time
const bookAppointment = async (selectedTime, petId) => {
  // Same booking logic as above
};
```

## Error Responses

### Validation Errors (422)
```json
{
  "is_success": false,
  "message": "The selected time slot is not available",
  "data": null
}
```

### Authentication Errors (401)
```json
{
  "is_success": false,
  "message": "Unauthenticated",
  "data": null
}
```

### Authorization Errors (403)
```json
{
  "is_success": false,
  "message": "Unauthorized to delete this appointment",
  "data": null
}
```

### Not Found Errors (404)
```json
{
  "is_success": false,
  "message": "Appointment not found",
  "data": null
}
```

## Business Rules

1. **Pet Ownership**: Users can only book appointments for pets they own
2. **Time Availability**: Appointments can only be booked for available time slots
3. **Doctor Working Hours**: Appointments must be within doctor's working hours
4. **Future Appointments**: New appointments can only be booked for today or future dates
5. **Cancellation**: Only future appointments can be cancelled
6. **Status Transitions**: Completed or cancelled appointments cannot be modified
7. **Conflict Prevention**: No overlapping appointments for the same doctor

## Appointment Statuses

- `pending`: Newly created appointment awaiting confirmation
- `confirmed`: Appointment confirmed by the clinic
- `completed`: Appointment has been completed
- `cancelled`: Appointment has been cancelled
- `no-show`: Patient did not show up for the appointment

## Appointment Types

- `regular`: Standard appointment
- `emergency`: Emergency appointment
- `surgery`: Surgical procedure
- `vaccination`: Vaccination appointment
- `checkup`: Regular health checkup
- `consultation`: Consultation appointment 