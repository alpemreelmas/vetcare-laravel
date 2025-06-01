# Role-Based Medical History API Documentation

## 🎯 **Overview**

The medical history system has been restructured with **role-based endpoint separation** for better security, organization, and user experience. Each user role has dedicated endpoints with appropriate permissions and functionality.

## 🏗️ **Architecture Benefits**

### **✅ Security Advantages:**
- **Clear Permission Boundaries**: Each role has distinct endpoints with specific access controls
- **Reduced Attack Surface**: Users can only access endpoints relevant to their role
- **Explicit Authorization**: No need for complex permission checks within controllers
- **Audit Trail**: Easy to track which role accessed which endpoints

### **✅ Development Benefits:**
- **Clean Code Separation**: Each controller focuses on specific role requirements
- **Easier Maintenance**: Role-specific logic is isolated
- **Better Testing**: Test each role's functionality independently
- **Scalable Architecture**: Easy to add new roles or modify existing ones

### **✅ User Experience Benefits:**
- **Intuitive URLs**: Clear indication of user capabilities
- **Role-Specific Features**: Each role gets features tailored to their needs
- **Optimized Responses**: Data filtered and formatted for specific roles

---

## 🔐 **Role-Based Endpoint Structure**

### **🔴 Admin Routes** - `/api/admin/`
**Full system access and management capabilities**

```bash
# Medical Records Management
GET    /api/admin/medical-records                    # List all medical records
POST   /api/admin/medical-records                    # Create medical record for any appointment
GET    /api/admin/medical-records/statistics         # System-wide statistics
GET    /api/admin/medical-records/doctor/{doctor}    # Records by specific doctor
GET    /api/admin/medical-records/pet/{pet}          # Records by specific pet
PATCH  /api/admin/medical-records/bulk-status        # Bulk update record statuses
GET    /api/admin/medical-records/{record}           # View any medical record
PUT    /api/admin/medical-records/{record}           # Update any medical record
DELETE /api/admin/medical-records/{record}           # Delete any medical record

# Medical Documents Management
GET    /api/admin/medical-documents                  # List all medical documents
POST   /api/admin/medical-documents                  # Upload document for any record
GET    /api/admin/medical-documents/statistics       # Document statistics
PATCH  /api/admin/medical-documents/bulk-visibility  # Bulk update visibility
PATCH  /api/admin/medical-documents/bulk-archive     # Bulk archive/unarchive
GET    /api/admin/medical-documents/pet/{pet}        # Documents by pet
GET    /api/admin/medical-documents/{document}       # View any document
PUT    /api/admin/medical-documents/{document}       # Update any document
DELETE /api/admin/medical-documents/{document}       # Delete any document
GET    /api/admin/medical-documents/{document}/download    # Download any document
PATCH  /api/admin/medical-documents/{document}/toggle-archive # Archive any document
```

### **🟡 Doctor Routes** - `/api/doctor/`
**Professional medical management for own patients**

```bash
# Medical Records Management
GET    /api/doctor/medical-records                   # List doctor's medical records
POST   /api/doctor/medical-records                   # Create record for own appointment
GET    /api/doctor/medical-records/pending           # Appointments needing records
GET    /api/doctor/medical-records/statistics        # Doctor's statistics
GET    /api/doctor/medical-records/pet/{pet}/history # Pet history (if treated)
POST   /api/doctor/medical-records/appointment/{appointment}/create # Quick create
GET    /api/doctor/medical-records/{record}          # View own medical record
PUT    /api/doctor/medical-records/{record}          # Update own medical record

# Medical Documents Management
GET    /api/doctor/medical-documents                 # List doctor's documents
POST   /api/doctor/medical-documents                 # Upload document for own record
GET    /api/doctor/medical-documents/statistics      # Doctor's upload statistics
GET    /api/doctor/medical-documents/recent-uploads  # Recent uploads
GET    /api/doctor/medical-documents/pet/{pet}       # Pet documents (if treated)
GET    /api/doctor/medical-documents/{document}      # View accessible document
PUT    /api/doctor/medical-documents/{document}      # Update own document
DELETE /api/doctor/medical-documents/{document}      # Delete own document
GET    /api/doctor/medical-documents/{document}/download # Download accessible document
PATCH  /api/doctor/medical-documents/{document}/toggle-archive # Archive own document
```

### **🟢 User Routes** - `/api/my/pets/`
**Read-only access to own pets' medical history**

```bash
# Pet Medical History (Read-Only)
GET    /api/my/pets/medical-summary                  # All pets with medical summary
GET    /api/my/pets/{pet}/medical-history            # Complete pet medical history
GET    /api/my/pets/{pet}/medical-records/{record}   # Specific medical record
GET    /api/my/pets/{pet}/medical-documents          # Pet's medical documents
GET    /api/my/pets/{pet}/medical-documents/{document} # Specific document details
GET    /api/my/pets/{pet}/medical-documents/{document}/download # Download document
GET    /api/my/pets/{pet}/active-diagnoses           # Current active diagnoses
GET    /api/my/pets/{pet}/current-treatments         # Current treatments
GET    /api/my/pets/{pet}/upcoming-appointments      # Upcoming appointments
```

---

## 📊 **Role Comparison Table**

| Feature | Admin | Doctor | User |
|---------|-------|--------|------|
| **View Medical Records** | ✅ All records | ✅ Own records only | ✅ Own pets only |
| **Create Medical Records** | ✅ Any appointment | ✅ Own appointments | ❌ Read-only |
| **Update Medical Records** | ✅ Any record | ✅ Own records | ❌ Read-only |
| **Delete Medical Records** | ✅ Any record | ❌ No deletion | ❌ Read-only |
| **Upload Documents** | ✅ Any record | ✅ Own records | ❌ Read-only |
| **View Documents** | ✅ All documents | ✅ Patient documents | ✅ Visible documents |
| **Download Documents** | ✅ All documents | ✅ Patient documents | ✅ Visible documents |
| **Bulk Operations** | ✅ Full access | ❌ No bulk ops | ❌ Read-only |
| **Statistics** | ✅ System-wide | ✅ Personal stats | ❌ No statistics |
| **Archive Documents** | ✅ Any document | ✅ Own documents | ❌ Read-only |

---

## 🔒 **Security Features**

### **Admin Security:**
- ✅ **Full System Access**: Can manage all medical data
- ✅ **Bulk Operations**: Efficient management of large datasets
- ✅ **System Statistics**: Complete overview of medical data
- ✅ **Audit Capabilities**: Track all medical activities

### **Doctor Security:**
- ✅ **Patient-Centric Access**: Only pets they have treated
- ✅ **Professional Tools**: Medical record creation and document upload
- ✅ **Workflow Integration**: Pending records and statistics
- ✅ **Data Integrity**: Cannot access other doctors' records

### **User Security:**
- ✅ **Pet Ownership Validation**: Only their own pets
- ✅ **Visibility Filtering**: Only documents marked as visible to owners
- ✅ **Read-Only Access**: Cannot modify medical data
- ✅ **Privacy Protection**: Cannot see sensitive medical documents

---

## 🎯 **Frontend Implementation Examples**

### **Admin Dashboard:**
```javascript
// Admin can access all medical records with advanced filtering
const getAllMedicalRecords = async (filters = {}) => {
  const params = new URLSearchParams(filters);
  const response = await fetch(`/api/admin/medical-records?${params}`, {
    headers: { 'Authorization': `Bearer ${adminToken}` }
  });
  return response.json();
};

// Admin bulk operations
const bulkUpdateRecordStatus = async (recordIds, status) => {
  const response = await fetch('/api/admin/medical-records/bulk-status', {
    method: 'PATCH',
    headers: {
      'Authorization': `Bearer ${adminToken}`,
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({ record_ids: recordIds, status })
  });
  return response.json();
};
```

### **Doctor Interface:**
```javascript
// Doctor can view their own medical records
const getMyMedicalRecords = async (filters = {}) => {
  const params = new URLSearchParams(filters);
  const response = await fetch(`/api/doctor/medical-records?${params}`, {
    headers: { 'Authorization': `Bearer ${doctorToken}` }
  });
  return response.json();
};

// Doctor can upload documents for their patients
const uploadMedicalDocument = async (medicalRecordId, file, metadata) => {
  const formData = new FormData();
  formData.append('medical_record_id', medicalRecordId);
  formData.append('file', file);
  Object.keys(metadata).forEach(key => {
    formData.append(key, metadata[key]);
  });

  const response = await fetch('/api/doctor/medical-documents', {
    method: 'POST',
    headers: { 'Authorization': `Bearer ${doctorToken}` },
    body: formData
  });
  return response.json();
};

// Doctor can check pending medical records
const getPendingRecords = async () => {
  const response = await fetch('/api/doctor/medical-records/pending', {
    headers: { 'Authorization': `Bearer ${doctorToken}` }
  });
  return response.json();
};
```

### **Pet Owner Interface:**
```javascript
// Pet owner can view their pets' medical history
const getPetMedicalHistory = async (petId) => {
  const response = await fetch(`/api/my/pets/${petId}/medical-history`, {
    headers: { 'Authorization': `Bearer ${userToken}` }
  });
  return response.json();
};

// Pet owner can view medical summary for all their pets
const getMyPetsSummary = async () => {
  const response = await fetch('/api/my/pets/medical-summary', {
    headers: { 'Authorization': `Bearer ${userToken}` }
  });
  return response.json();
};

// Pet owner can download visible documents
const downloadPetDocument = async (petId, documentId) => {
  const response = await fetch(`/api/my/pets/${petId}/medical-documents/${documentId}/download`, {
    headers: { 'Authorization': `Bearer ${userToken}` }
  });
  
  if (response.ok) {
    const blob = await response.blob();
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'medical-document';
    a.click();
  }
};
```

---

## 📋 **Migration from Old Endpoints**

### **Old Unified Endpoints → New Role-Based Endpoints**

| Old Endpoint | Admin Replacement | Doctor Replacement | User Replacement |
|--------------|-------------------|-------------------|------------------|
| `GET /api/medical-records` | `GET /api/admin/medical-records` | `GET /api/doctor/medical-records` | `GET /api/my/pets/medical-summary` |
| `POST /api/medical-records` | `POST /api/admin/medical-records` | `POST /api/doctor/medical-records` | ❌ Not available |
| `GET /api/medical-records/{id}` | `GET /api/admin/medical-records/{id}` | `GET /api/doctor/medical-records/{id}` | `GET /api/my/pets/{pet}/medical-records/{id}` |
| `GET /api/medical-documents` | `GET /api/admin/medical-documents` | `GET /api/doctor/medical-documents` | `GET /api/my/pets/{pet}/medical-documents` |
| `POST /api/medical-documents` | `POST /api/admin/medical-documents` | `POST /api/doctor/medical-documents` | ❌ Not available |

---

## 🚀 **Benefits Summary**

### **For Administrators:**
- **Complete System Control**: Manage all medical data across the platform
- **Advanced Analytics**: System-wide statistics and reporting
- **Bulk Operations**: Efficient management of large datasets
- **Audit Capabilities**: Track and monitor all medical activities

### **For Doctors:**
- **Professional Workflow**: Tools designed for medical practice
- **Patient-Focused**: Access only relevant patient data
- **Efficient Documentation**: Quick medical record creation and document upload
- **Personal Statistics**: Track their own medical practice metrics

### **For Pet Owners:**
- **Comprehensive Pet Health**: Complete medical history for their pets
- **Easy Access**: Intuitive endpoints for pet medical information
- **Privacy Respected**: Only see information appropriate for pet owners
- **Mobile-Friendly**: Optimized for pet owner mobile applications

---

## 🔧 **Implementation Notes**

### **Middleware Requirements:**
```php
// Admin routes require admin role
Route::middleware(['auth:sanctum', 'role:admin'])

// Doctor routes require doctor role  
Route::middleware(['auth:sanctum', 'role:doctor'])

// User routes require authentication only
Route::middleware('auth:sanctum')
```

### **Controller Organization:**
```
app/Http/Controllers/
├── Admin/
│   ├── MedicalRecordController.php    # Full system access
│   └── MedicalDocumentController.php  # All documents management
├── Doctor/
│   ├── MedicalRecordController.php    # Professional tools
│   └── MedicalDocumentController.php  # Patient document management
└── User/
    └── PetMedicalHistoryController.php # Read-only pet history
```

This role-based separation provides a clean, secure, and maintainable architecture that scales well with different user needs and permissions. Each role gets exactly the functionality they need without unnecessary complexity or security risks. 