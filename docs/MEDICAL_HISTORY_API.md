# Medical History Tracking API Documentation

## Overview
This document describes the medical history tracking system for the VetCare application. The system provides comprehensive functionality for:
- **Medical Records**: Store past visit records with detailed examination findings
- **Diagnoses & Treatments**: Track medical diagnoses and treatment plans
- **Medical Documents**: Upload and manage medical files (X-rays, Lab Reports, etc.)

## Authentication
All medical history endpoints require authentication using Sanctum tokens:
```
Authorization: Bearer {your-token}
```

## System Architecture

### 📊 **Database Structure**

```
appointments
    └── medical_records (1:1)
        ├── diagnoses (1:many)
        │   └── treatments (1:many)
        ├── treatments (1:many)
        └── medical_documents (1:many)

pets
    ├── medical_records (1:many)
    ├── diagnoses (1:many)
    ├── treatments (1:many)
    └── medical_documents (1:many)
```

### 🔐 **Access Control**

| User Role | Medical Records | Medical Documents | Permissions |
|-----------|----------------|-------------------|-------------|
| **Admin** | All records | All documents | Full CRUD access |
| **Doctor** | Own records + appointments | Own uploads + patient files | Create, Read, Update |
| **Pet Owner** | Own pets only | Visible documents only | Read only |

---

## 📋 Medical Records API

### 1. List Medical Records
Get medical records based on user permissions.

**Endpoint:** `GET /api/medical-records`

**Query Parameters:**
- `pet_id`: Filter by pet ID
- `doctor_id`: Filter by doctor ID
- `status`: Filter by status (draft, completed, reviewed)
- `start_date`: Filter from date
- `end_date`: Filter to date
- `per_page`: Results per page (default: 15)

**Example Request:**
```bash
GET /api/medical-records?pet_id=1&status=completed&per_page=10
```

**Example Response:**
```json
{
  "is_success": true,
  "message": "Medical records retrieved successfully",
  "data": {
    "medical_records": [
      {
        "id": 1,
        "appointment_id": 5,
        "pet_id": 1,
        "doctor_id": 2,
        "visit_date": "2024-01-15",
        "chief_complaint": "Limping on left front leg",
        "physical_examination": "Swelling noted on left carpus",
        "weight": 25.5,
        "temperature": 38.2,
        "heart_rate": 120,
        "assessment": "Suspected sprain",
        "plan": "Rest and anti-inflammatory medication",
        "status": "completed",
        "pet": {
          "id": 1,
          "name": "Buddy",
          "species": "Dog",
          "owner": {
            "id": 3,
            "name": "John Doe"
          }
        },
        "doctor": {
          "id": 2,
          "user": {
            "name": "Dr. Sarah Smith"
          }
        },
        "diagnoses": [...],
        "treatments": [...]
      }
    ],
    "pagination": {
      "current_page": 1,
      "last_page": 3,
      "per_page": 10,
      "total": 25
    }
  }
}
```

### 2. Create Medical Record
Create a new medical record for an appointment.

**Endpoint:** `POST /api/medical-records`

**Request Body:**
```json
{
  "appointment_id": 5,
  "chief_complaint": "Limping on left front leg",
  "history_of_present_illness": "Started limping 2 days ago after playing in the park",
  "physical_examination": "Swelling and tenderness noted on left carpus. No obvious fracture.",
  "weight": 25.5,
  "temperature": 38.2,
  "heart_rate": 120,
  "respiratory_rate": 24,
  "assessment": "Suspected carpus sprain",
  "plan": "Rest, anti-inflammatory medication, follow-up in 1 week",
  "notes": "Owner advised to limit activity",
  "follow_up_instructions": "Return if limping worsens or doesn't improve in 3-4 days",
  "next_visit_date": "2024-01-22",
  "status": "completed"
}
```

**Validation Rules:**
- `appointment_id`: required, must exist
- `weight`: numeric, min 0
- `temperature`: numeric, 0-50°C
- `heart_rate`: integer, 0-500 bpm
- `respiratory_rate`: integer, 0-200 bpm
- `next_visit_date`: date, after today
- `status`: draft, completed, reviewed

### 3. Get Specific Medical Record
Get detailed information about a specific medical record.

**Endpoint:** `GET /api/medical-records/{record_id}`

**Response includes:**
- Complete medical record details
- Associated diagnoses and treatments
- Uploaded medical documents
- Pet and doctor information

### 4. Update Medical Record
Update an existing medical record (doctors only).

**Endpoint:** `PUT /api/medical-records/{record_id}`

### 5. Delete Medical Record
Delete a medical record (admins only).

**Endpoint:** `DELETE /api/medical-records/{record_id}`

### 6. Get Pet Medical History
Get complete medical history for a specific pet.

**Endpoint:** `GET /api/medical-records/pet/{pet_id}/history`

**Example Response:**
```json
{
  "is_success": true,
  "message": "Pet medical history retrieved successfully",
  "data": {
    "pet": {
      "id": 1,
      "name": "Buddy",
      "species": "Dog",
      "breed": "Golden Retriever",
      "age": 3,
      "owner": {
        "id": 3,
        "name": "John Doe"
      }
    },
    "medical_records": [...],
    "summary": {
      "total_visits": 8,
      "active_diagnoses": 1,
      "chronic_conditions": 0,
      "current_treatments": 2,
      "total_documents": 12,
      "last_visit": "2024-01-15"
    }
  }
}
```

### 7. Create Medical Record from Appointment
Quick create medical record from an existing appointment.

**Endpoint:** `POST /api/medical-records/appointment/{appointment_id}/create`

---

## 📄 Medical Documents API

### 1. List Medical Documents
Get medical documents based on user permissions.

**Endpoint:** `GET /api/medical-documents`

**Query Parameters:**
- `pet_id`: Filter by pet ID
- `medical_record_id`: Filter by medical record ID
- `type`: Filter by document type
- `is_archived`: Filter archived status
- `search`: Search in title and description
- `per_page`: Results per page

**Example Request:**
```bash
GET /api/medical-documents?pet_id=1&type=xray&is_archived=false
```

### 2. Upload Medical Document
Upload a new medical document.

**Endpoint:** `POST /api/medical-documents`

**Request Body (multipart/form-data):**
```
medical_record_id: 1
title: "X-ray of left front leg"
description: "X-ray taken to rule out fracture"
type: "xray"
file: [binary file data]
document_date: "2024-01-15"
tags: ["fracture", "leg", "diagnostic"]
is_sensitive: false
visibility: "owner_and_doctor"
```

**Supported File Types:**
- **Images**: jpg, jpeg, png, gif
- **Documents**: pdf, doc, docx, txt
- **Max Size**: 10MB

**Document Types:**
- `xray` - X-ray images
- `lab_report` - Laboratory reports
- `blood_work` - Blood test results
- `ultrasound` - Ultrasound images
- `ct_scan` - CT scan images
- `mri` - MRI images
- `prescription` - Prescription documents
- `vaccination_record` - Vaccination records
- `surgical_report` - Surgery reports
- `pathology_report` - Pathology results
- `photo` - Clinical photos
- `other` - Other documents

**Visibility Levels:**
- `private` - Only uploader can see
- `doctor_only` - Only doctors can see
- `owner_and_doctor` - Pet owner and doctors can see
- `public` - Everyone can see

### 3. Get Specific Document
Get details of a specific medical document.

**Endpoint:** `GET /api/medical-documents/{document_id}`

**Example Response:**
```json
{
  "is_success": true,
  "message": "Medical document retrieved successfully",
  "data": {
    "document": {
      "id": 1,
      "title": "X-ray of left front leg",
      "description": "X-ray taken to rule out fracture",
      "type": "xray",
      "file_name": "buddy_xray_20240115.jpg",
      "file_type": "image/jpeg",
      "file_size": 2048576,
      "document_date": "2024-01-15",
      "tags": ["fracture", "leg", "diagnostic"],
      "visibility": "owner_and_doctor",
      "is_sensitive": false,
      "is_archived": false,
      "pet": {
        "id": 1,
        "name": "Buddy"
      },
      "uploader": {
        "id": 2,
        "name": "Dr. Sarah Smith"
      }
    },
    "file_url": "/storage/medical-documents/1/uuid-filename.jpg",
    "file_size_human": "2.00 MB"
  }
}
```

### 4. Download Medical Document
Download the actual file.

**Endpoint:** `GET /api/medical-documents/{document_id}/download`

**Response:** Binary file download with proper headers

### 5. Update Document Metadata
Update document information (not the file itself).

**Endpoint:** `PUT /api/medical-documents/{document_id}`

### 6. Delete Medical Document
Delete a medical document and its file.

**Endpoint:** `DELETE /api/medical-documents/{document_id}`

### 7. Archive/Unarchive Document
Toggle archive status of a document.

**Endpoint:** `PATCH /api/medical-documents/{document_id}/toggle-archive`

### 8. Get Pet Documents
Get all documents for a specific pet.

**Endpoint:** `GET /api/medical-documents/pet/{pet_id}`

---

## 🔒 Security Features

### **File Security:**
- ✅ **File Type Validation**: Only allowed file types accepted
- ✅ **Size Limits**: Maximum 10MB per file
- ✅ **Duplicate Detection**: MD5 hash prevents duplicate uploads
- ✅ **Secure Storage**: Files stored outside web root
- ✅ **Access Control**: Permission-based file access

### **Data Privacy:**
- ✅ **Role-Based Access**: Different permissions for each user type
- ✅ **Visibility Controls**: Granular document visibility settings
- ✅ **Sensitive Data Flags**: Mark sensitive documents
- ✅ **Audit Trail**: Track who uploaded/modified documents

### **Medical Record Security:**
- ✅ **Doctor Ownership**: Doctors can only modify their own records
- ✅ **Pet Ownership**: Users can only view their pets' records
- ✅ **Admin Override**: Admins have full access for management

---

## 📊 Business Rules

### **Medical Records:**
1. **One Record Per Appointment**: Each appointment can have only one medical record
2. **Doctor Creation**: Only doctors can create/update medical records
3. **Status Workflow**: draft → completed → reviewed
4. **Data Integrity**: Automatic linking to appointment, pet, and doctor

### **Medical Documents:**
1. **Doctor Upload**: Only doctors can upload medical documents
2. **File Uniqueness**: Duplicate files (same hash) are rejected per pet
3. **Automatic Cleanup**: Files deleted when document is deleted
4. **Visibility Inheritance**: Documents inherit appropriate visibility based on uploader role

### **Access Control:**
1. **Pet Owners**: Can view their pets' medical history (with appropriate visibility)
2. **Doctors**: Can manage records for their appointments and upload documents
3. **Admins**: Full system access for management purposes

---

## 🎯 Frontend Implementation Examples

### **Upload Medical Document:**
```javascript
const uploadDocument = async (medicalRecordId, file, metadata) => {
  const formData = new FormData();
  formData.append('medical_record_id', medicalRecordId);
  formData.append('file', file);
  formData.append('title', metadata.title);
  formData.append('type', metadata.type);
  formData.append('visibility', metadata.visibility);

  const response = await fetch('/api/medical-documents', {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`
    },
    body: formData
  });

  return response.json();
};
```

### **Get Pet Medical History:**
```javascript
const getPetHistory = async (petId) => {
  const response = await fetch(`/api/medical-records/pet/${petId}/history`);
  const data = await response.json();
  
  return {
    records: data.data.medical_records,
    summary: data.data.summary,
    pet: data.data.pet
  };
};
```

### **Create Medical Record:**
```javascript
const createMedicalRecord = async (appointmentId, recordData) => {
  const response = await fetch('/api/medical-records', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${token}`
    },
    body: JSON.stringify({
      appointment_id: appointmentId,
      ...recordData
    })
  });

  return response.json();
};
```

---

## 📋 Route Summary

### **Medical Records:**
```
GET    /api/medical-records                           # List medical records
POST   /api/medical-records                           # Create medical record
GET    /api/medical-records/{record}                  # Get specific record
PUT    /api/medical-records/{record}                  # Update record
DELETE /api/medical-records/{record}                  # Delete record
GET    /api/medical-records/pet/{pet}/history         # Get pet history
POST   /api/medical-records/appointment/{appointment}/create  # Quick create
```

### **Medical Documents:**
```
GET    /api/medical-documents                         # List documents
POST   /api/medical-documents                         # Upload document
GET    /api/medical-documents/{document}              # Get document details
PUT    /api/medical-documents/{document}              # Update metadata
DELETE /api/medical-documents/{document}              # Delete document
GET    /api/medical-documents/{document}/download     # Download file
PATCH  /api/medical-documents/{document}/toggle-archive  # Archive/unarchive
GET    /api/medical-documents/pet/{pet}               # Get pet documents
```

---

## 🚨 Error Responses

### **File Upload Errors:**
```json
{
  "is_success": false,
  "message": "The file field is required.",
  "data": {
    "errors": {
      "file": ["The file field is required."]
    }
  }
}
```

### **Permission Errors:**
```json
{
  "is_success": false,
  "message": "Only doctors can create medical records",
  "data": null
}
```

### **Business Rule Violations:**
```json
{
  "is_success": false,
  "message": "Medical record already exists for this appointment",
  "data": null
}
```

This comprehensive medical history tracking system provides a complete solution for managing veterinary medical records, diagnoses, treatments, and document uploads with proper security and access controls. 