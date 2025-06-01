# 🗄️ VetCare Database Schema Documentation

## 📊 **Overview**

The VetCare database is designed with a normalized structure supporting a comprehensive veterinary management system. The schema includes user management, appointment scheduling, medical records, billing, and document management.

## 🏗️ **Database Design Principles**

- **Normalization**: Third normal form (3NF) to minimize redundancy
- **Referential Integrity**: Foreign key constraints ensure data consistency
- **Indexing**: Strategic indexes for performance optimization
- **Soft Deletes**: Preserve data integrity for audit trails
- **Timestamps**: Track creation and modification times

## 📋 **Table Categories**

### **1. User Management & Authentication**
- `users` - User accounts and profiles
- `model_has_roles` - Role assignments (Spatie)
- `model_has_permissions` - Permission assignments (Spatie)
- `roles` - System roles (Spatie)
- `permissions` - System permissions (Spatie)
- `personal_access_tokens` - API authentication tokens (Sanctum)

### **2. Medical Domain**
- `doctors` - Doctor profiles and specializations
- `pets` - Pet information and ownership
- `appointments` - Appointment scheduling
- `medical_records` - Medical visit records
- `diagnoses` - Medical diagnoses
- `treatments` - Treatment plans and medications
- `medical_documents` - File attachments and documents

### **3. Billing Domain**
- `services` - Billable veterinary services
- `invoices` - Billing documents
- `invoice_items` - Individual services on invoices
- `payments` - Payment records and transactions

### **4. System Tables**
- `doctor_restricted_time_frames` - Doctor availability restrictions
- `cache` - Application caching
- `jobs` - Background job queue
- `failed_jobs` - Failed job tracking

## 📝 **Detailed Table Schemas**

### **Users Table**
```sql
CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    email_verified_at TIMESTAMP NULL,
    password VARCHAR(255) NOT NULL,
    remember_token VARCHAR(100) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

**Purpose:** Core user accounts for all system users (admins, doctors, pet owners)

**Relationships:**
- One-to-many with `pets` (as owner)
- One-to-one with `doctors` (for doctor users)
- Many-to-many with `roles` via `model_has_roles`

### **Doctors Table**
```sql
CREATE TABLE doctors (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    specialization VARCHAR(255) NOT NULL,
    license_number VARCHAR(255) NULL,
    working_hours JSON NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

**Purpose:** Doctor-specific information and professional details

**Key Fields:**
- `specialization`: Medical specialization (e.g., "General Veterinarian", "Surgery Specialist")
- `license_number`: Professional license identifier
- `working_hours`: JSON structure for weekly schedule

**Relationships:**
- Belongs to `users`
- One-to-many with `appointments`
- One-to-many with `medical_records`

### **Pets Table**
```sql
CREATE TABLE pets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    owner_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    species VARCHAR(255) NOT NULL,
    breed VARCHAR(255) NULL,
    date_of_birth DATE NULL,
    gender ENUM('male', 'female', 'unknown') NOT NULL DEFAULT 'unknown',
    weight DECIMAL(5,2) NULL,
    color VARCHAR(255) NULL,
    microchip_number VARCHAR(255) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_pets_owner_id (owner_id),
    INDEX idx_pets_species (species)
);
```

**Purpose:** Pet profiles and basic information

**Key Fields:**
- `species`: Animal type (dog, cat, bird, etc.)
- `breed`: Specific breed information
- `microchip_number`: Unique identification chip

**Relationships:**
- Belongs to `users` (as owner)
- One-to-many with `appointments`
- One-to-many with `medical_records`

### **Appointments Table**
```sql
CREATE TABLE appointments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    doctor_id BIGINT UNSIGNED NOT NULL,
    pet_id BIGINT UNSIGNED NOT NULL,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    duration INTEGER NOT NULL DEFAULT 30,
    appointment_type ENUM('regular', 'emergency', 'follow_up', 'surgery') NOT NULL DEFAULT 'regular',
    status ENUM('scheduled', 'confirmed', 'in_progress', 'completed', 'cancelled', 'no_show') NOT NULL DEFAULT 'scheduled',
    notes TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
    FOREIGN KEY (pet_id) REFERENCES pets(id) ON DELETE CASCADE,
    INDEX idx_appointments_doctor_date (doctor_id, appointment_date),
    INDEX idx_appointments_pet_id (pet_id),
    INDEX idx_appointments_status (status)
);
```

**Purpose:** Appointment scheduling and management

**Key Fields:**
- `duration`: Appointment length in minutes
- `appointment_type`: Type of visit
- `status`: Current appointment status

**Relationships:**
- Belongs to `doctors`
- Belongs to `pets`
- One-to-one with `medical_records`

### **Medical Records Table**
```sql
CREATE TABLE medical_records (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    appointment_id BIGINT UNSIGNED NULL,
    pet_id BIGINT UNSIGNED NOT NULL,
    doctor_id BIGINT UNSIGNED NOT NULL,
    visit_date DATE NOT NULL,
    visit_type ENUM('routine_checkup', 'emergency', 'follow_up', 'surgery', 'vaccination', 'dental', 'other') NOT NULL DEFAULT 'routine_checkup',
    chief_complaint TEXT NULL,
    physical_examination TEXT NULL,
    vital_signs JSON NULL,
    assessment TEXT NULL,
    plan TEXT NULL,
    notes TEXT NULL,
    status ENUM('draft', 'completed', 'reviewed') NOT NULL DEFAULT 'draft',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL,
    FOREIGN KEY (pet_id) REFERENCES pets(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
    INDEX idx_medical_records_pet_id (pet_id),
    INDEX idx_medical_records_doctor_id (doctor_id),
    INDEX idx_medical_records_visit_date (visit_date)
);
```

**Purpose:** Medical visit records and examination details

**Key Fields:**
- `vital_signs`: JSON structure for temperature, weight, heart rate, etc.
- `chief_complaint`: Primary reason for visit
- `assessment`: Medical assessment and findings
- `plan`: Treatment plan and recommendations

**Relationships:**
- Belongs to `appointments` (optional)
- Belongs to `pets`
- Belongs to `doctors`
- One-to-many with `diagnoses`
- One-to-many with `treatments`

### **Diagnoses Table**
```sql
CREATE TABLE diagnoses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    medical_record_id BIGINT UNSIGNED NOT NULL,
    pet_id BIGINT UNSIGNED NOT NULL,
    diagnosis_code VARCHAR(255) NULL,
    diagnosis_name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    severity ENUM('mild', 'moderate', 'severe', 'critical') NOT NULL DEFAULT 'mild',
    status ENUM('active', 'resolved', 'chronic', 'under_observation') NOT NULL DEFAULT 'active',
    diagnosed_date DATE NOT NULL,
    resolved_date DATE NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (medical_record_id) REFERENCES medical_records(id) ON DELETE CASCADE,
    FOREIGN KEY (pet_id) REFERENCES pets(id) ON DELETE CASCADE,
    INDEX idx_diagnoses_pet_id (pet_id),
    INDEX idx_diagnoses_status (status)
);
```

**Purpose:** Medical diagnoses and conditions

**Key Fields:**
- `diagnosis_code`: Standard medical coding (ICD-10, etc.)
- `severity`: Condition severity level
- `status`: Current diagnosis status

**Relationships:**
- Belongs to `medical_records`
- Belongs to `pets`
- One-to-many with `treatments`

### **Treatments Table**
```sql
CREATE TABLE treatments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    medical_record_id BIGINT UNSIGNED NOT NULL,
    pet_id BIGINT UNSIGNED NOT NULL,
    diagnosis_id BIGINT UNSIGNED NULL,
    type ENUM('medication', 'procedure', 'surgery', 'therapy', 'vaccination', 'diagnostic_test', 'other') NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    medication_name VARCHAR(255) NULL,
    dosage VARCHAR(255) NULL,
    frequency VARCHAR(255) NULL,
    route VARCHAR(255) NULL,
    duration_days INTEGER NULL,
    procedure_code VARCHAR(255) NULL,
    procedure_notes TEXT NULL,
    anesthesia_type ENUM('none', 'local', 'general', 'sedation') NULL,
    start_date DATE NOT NULL,
    end_date DATE NULL,
    administered_at TIMESTAMP NULL,
    status ENUM('prescribed', 'in_progress', 'completed', 'discontinued', 'on_hold') NOT NULL DEFAULT 'prescribed',
    instructions TEXT NULL,
    side_effects TEXT NULL,
    response_notes TEXT NULL,
    cost DECIMAL(10,2) NULL,
    billing_code VARCHAR(255) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (medical_record_id) REFERENCES medical_records(id) ON DELETE CASCADE,
    FOREIGN KEY (pet_id) REFERENCES pets(id) ON DELETE CASCADE,
    FOREIGN KEY (diagnosis_id) REFERENCES diagnoses(id) ON DELETE SET NULL,
    INDEX idx_treatments_pet_id (pet_id),
    INDEX idx_treatments_type (type),
    INDEX idx_treatments_status (status)
);
```

**Purpose:** Treatment plans, medications, and procedures

**Key Fields:**
- `type`: Treatment category
- `cost`: Treatment cost for billing
- `billing_code`: Link to billing services
- Medication-specific fields: `dosage`, `frequency`, `route`
- Procedure-specific fields: `procedure_code`, `anesthesia_type`

**Relationships:**
- Belongs to `medical_records`
- Belongs to `pets`
- Belongs to `diagnoses` (optional)
- One-to-one with `invoice_items` (via metadata)

### **Medical Documents Table**
```sql
CREATE TABLE medical_documents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    medical_record_id BIGINT UNSIGNED NULL,
    pet_id BIGINT UNSIGNED NOT NULL,
    doctor_id BIGINT UNSIGNED NOT NULL,
    document_type ENUM('lab_result', 'xray', 'ultrasound', 'ct_scan', 'mri', 'photo', 'report', 'prescription', 'other') NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INTEGER NOT NULL,
    mime_type VARCHAR(255) NOT NULL,
    document_date DATE NOT NULL,
    visibility ENUM('owner_and_doctor', 'doctor_only', 'system_only') NOT NULL DEFAULT 'owner_and_doctor',
    is_archived BOOLEAN NOT NULL DEFAULT FALSE,
    metadata JSON NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (medical_record_id) REFERENCES medical_records(id) ON DELETE SET NULL,
    FOREIGN KEY (pet_id) REFERENCES pets(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
    INDEX idx_medical_documents_pet_id (pet_id),
    INDEX idx_medical_documents_doctor_id (doctor_id),
    INDEX idx_medical_documents_type (document_type)
);
```

**Purpose:** Medical document and file management

**Key Fields:**
- `document_type`: Type of medical document
- `visibility`: Access control for documents
- `metadata`: Additional document information in JSON format

**Relationships:**
- Belongs to `medical_records` (optional)
- Belongs to `pets`
- Belongs to `doctors`

### **Services Table**
```sql
CREATE TABLE services (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    category ENUM('consultation', 'diagnostic', 'treatment', 'surgery', 'vaccination', 'grooming', 'emergency') NOT NULL,
    base_price DECIMAL(10,2) NOT NULL,
    pricing_type ENUM('fixed', 'variable', 'range') NOT NULL DEFAULT 'fixed',
    min_price DECIMAL(10,2) NULL,
    max_price DECIMAL(10,2) NULL,
    duration_minutes INTEGER NULL,
    service_code VARCHAR(255) UNIQUE NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    requires_appointment BOOLEAN NOT NULL DEFAULT TRUE,
    equipment_needed TEXT NULL,
    tags JSON NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    INDEX idx_services_category (category),
    INDEX idx_services_active (is_active),
    INDEX idx_services_code (service_code)
);
```

**Purpose:** Billable veterinary services catalog

**Key Fields:**
- `pricing_type`: Fixed, variable, or range-based pricing
- `service_code`: Unique identifier for billing
- `equipment_needed`: Required equipment list
- `tags`: JSON array for categorization

**Relationships:**
- One-to-many with `invoice_items`

### **Invoices Table**
```sql
CREATE TABLE invoices (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(255) UNIQUE NOT NULL,
    appointment_id BIGINT UNSIGNED NULL,
    pet_id BIGINT UNSIGNED NOT NULL,
    owner_id BIGINT UNSIGNED NOT NULL,
    doctor_id BIGINT UNSIGNED NOT NULL,
    invoice_date DATE NOT NULL,
    due_date DATE NOT NULL,
    service_date DATE NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL DEFAULT 0,
    tax_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
    tax_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    discount_type ENUM('percentage', 'fixed') NULL,
    discount_value DECIMAL(10,2) NULL,
    discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    paid_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    balance_due DECIMAL(10,2) NOT NULL DEFAULT 0,
    payment_status ENUM('unpaid', 'partial', 'paid', 'overdue', 'cancelled') NOT NULL DEFAULT 'unpaid',
    status ENUM('draft', 'sent', 'viewed', 'paid', 'cancelled') NOT NULL DEFAULT 'draft',
    notes TEXT NULL,
    sent_at TIMESTAMP NULL,
    viewed_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL,
    FOREIGN KEY (pet_id) REFERENCES pets(id) ON DELETE CASCADE,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
    INDEX idx_invoices_owner_id (owner_id),
    INDEX idx_invoices_doctor_id (doctor_id),
    INDEX idx_invoices_payment_status (payment_status),
    INDEX idx_invoices_due_date (due_date)
);
```

**Purpose:** Billing documents and invoice management

**Key Fields:**
- `invoice_number`: Unique invoice identifier (INV-YYYY-MM-NNNN)
- `payment_status`: Current payment status
- Financial fields: `subtotal`, `tax_amount`, `total_amount`, etc.

**Relationships:**
- Belongs to `appointments` (optional)
- Belongs to `pets`
- Belongs to `users` (owner)
- Belongs to `doctors`
- One-to-many with `invoice_items`
- One-to-many with `payments`

### **Invoice Items Table**
```sql
CREATE TABLE invoice_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_id BIGINT UNSIGNED NOT NULL,
    service_id BIGINT UNSIGNED NULL,
    service_name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    service_code VARCHAR(255) NULL,
    quantity INTEGER NOT NULL DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    notes TEXT NULL,
    metadata JSON NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE SET NULL,
    INDEX idx_invoice_items_invoice_id (invoice_id),
    INDEX idx_invoice_items_service_id (service_id)
);
```

**Purpose:** Individual services and items on invoices

**Key Fields:**
- `metadata`: JSON field for additional information (e.g., treatment_id for automatic billing)
- `service_code`: Reference to service catalog

**Relationships:**
- Belongs to `invoices`
- Belongs to `services` (optional)

### **Payments Table**
```sql
CREATE TABLE payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_id BIGINT UNSIGNED NOT NULL,
    payment_method ENUM('cash', 'credit_card', 'debit_card', 'bank_transfer', 'online_payment', 'check', 'mobile_payment', 'insurance') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_date DATE NOT NULL,
    transaction_id VARCHAR(255) NULL,
    gateway_response JSON NULL,
    processing_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
    net_amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'completed', 'failed', 'cancelled', 'refunded') NOT NULL DEFAULT 'pending',
    reference_number VARCHAR(255) NULL,
    notes TEXT NULL,
    refund_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    refund_date DATE NULL,
    refund_reason TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    INDEX idx_payments_invoice_id (invoice_id),
    INDEX idx_payments_status (status),
    INDEX idx_payments_payment_date (payment_date)
);
```

**Purpose:** Payment records and transaction tracking

**Key Fields:**
- `gateway_response`: JSON field for payment gateway responses
- `processing_fee`: Payment processing costs
- Refund fields: `refund_amount`, `refund_date`, `refund_reason`

**Relationships:**
- Belongs to `invoices`

### **Doctor Restricted Time Frames Table**
```sql
CREATE TABLE doctor_restricted_time_frames (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    doctor_id BIGINT UNSIGNED NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    start_time TIME NULL,
    end_time TIME NULL,
    reason VARCHAR(255) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
    INDEX idx_doctor_restrictions_doctor_date (doctor_id, start_date)
);
```

**Purpose:** Doctor availability restrictions and time-off management

**Key Fields:**
- `start_time`/`end_time`: NULL for full-day restrictions
- `reason`: Vacation, conference, emergency, etc.

**Relationships:**
- Belongs to `doctors`

## 🔗 **Key Relationships**

### **User → Pet → Medical Records Flow**
```
User (owner) → Pet → Appointment → Medical Record → Diagnosis/Treatment
```

### **Billing Flow**
```
Treatment (with cost) → Invoice Item → Invoice → Payment
```

### **Document Management Flow**
```
Medical Record → Medical Document (file storage)
```

## 📊 **Indexes and Performance**

### **Primary Indexes**
- All tables have auto-incrementing primary keys
- Unique constraints on critical fields (email, invoice_number, service_code)

### **Foreign Key Indexes**
- All foreign key columns are indexed for join performance
- Composite indexes for common query patterns

### **Search Indexes**
- `pets.species` - For filtering by animal type
- `appointments.status` - For appointment status queries
- `medical_records.visit_date` - For date range queries
- `invoices.payment_status` - For billing status queries

## 🔒 **Data Integrity Constraints**

### **Referential Integrity**
- Foreign key constraints with appropriate CASCADE/SET NULL actions
- Prevents orphaned records while preserving audit trails

### **Business Rules**
- ENUM constraints for status fields
- CHECK constraints for logical data validation
- NOT NULL constraints for required fields

### **Soft Deletes**
- Important records use soft deletes to preserve history
- Audit trails maintained for compliance

## 📈 **Scalability Considerations**

### **Partitioning Strategy**
- Consider date-based partitioning for large tables (medical_records, appointments)
- Archive old data to separate tables/databases

### **Indexing Strategy**
- Monitor query patterns and add indexes as needed
- Regular index maintenance and optimization

### **Data Archival**
- Implement data retention policies
- Archive completed appointments and old medical records
- Maintain billing data for legal requirements

---

This database schema provides a robust foundation for the VetCare veterinary management system with proper normalization, relationships, and performance considerations. 