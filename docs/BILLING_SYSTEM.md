# 💰 VetCare Billing System Documentation

## 🏗️ **System Overview**

The VetCare Billing System provides comprehensive service-based billing, invoice generation, and payment tracking for veterinary services. The system supports multiple payment methods, automated calculations, and role-based access control.

## 📊 **Database Schema**

### Services Table
Defines billable veterinary services with flexible pricing options.

```sql
- id (Primary Key)
- name (Service name: "General Checkup", "X-Ray", etc.)
- description (Detailed service description)
- category (consultation, diagnostic, treatment, surgery, vaccination, grooming, emergency, other)
- base_price (Standard service price)
- min_price/max_price (For variable pricing services)
- is_variable_pricing (Boolean flag)
- estimated_duration (Service duration in minutes)
- notes (Additional service information)
- required_equipment (JSON array of equipment needed)
- is_active (Service availability status)
- requires_appointment (Boolean flag)
- is_emergency_service (Boolean flag)
- service_code (Unique internal code)
- tags (JSON array for categorization)
```

### Invoices Table
Manages billing documents with comprehensive financial tracking.

```sql
- id (Primary Key)
- invoice_number (Unique identifier: "INV-2024-001")
- appointment_id (Optional link to appointment)
- pet_id (Pet receiving services)
- owner_id (Pet owner/bill recipient)
- doctor_id (Service provider)
- invoice_date/due_date/service_date
- subtotal/tax_rate/tax_amount/discount_amount/total_amount
- paid_amount/balance_due
- status (draft, sent, viewed, paid, partially_paid, overdue, cancelled, refunded)
- payment_status (unpaid, partially_paid, paid, refunded)
- notes/terms_and_conditions/payment_instructions
- discount_type/discount_value/discount_reason
- sent_at/viewed_at/paid_at (Tracking timestamps)
```

### Invoice Items Table
Individual services on each invoice with pricing details.

```sql
- id (Primary Key)
- invoice_id (Parent invoice)
- service_id (Service reference)
- service_name (Stored service name)
- description/service_code
- quantity (Number of units)
- unit_price/total_price
- discount_amount/discount_reason
- notes (Item-specific notes)
- metadata (JSON for additional data)
```

### Payments Table
Comprehensive payment tracking with multiple methods support.

```sql
- id (Primary Key)
- invoice_id (Invoice being paid)
- user_id (Payment maker)
- processed_by (Staff who processed payment)
- payment_number (Unique identifier: "PAY-2024-001")
- transaction_id/reference_number
- amount/payment_method
- status (pending, processing, completed, failed, cancelled, refunded, disputed)
- payment_date/processed_at/cleared_at
- card_last_four/card_type/bank_name/check_number
- gateway_response (JSON for online payments)
- notes/failure_reason
- fee_amount/currency
- refunded_amount/refunded_at/refund_reason
```

## 🎯 **Key Features**

### 1. Service-Based Billing
- **Flexible Pricing**: Fixed, variable, and range-based pricing
- **Service Categories**: Consultation, diagnostic, treatment, surgery, vaccination, grooming, emergency
- **Equipment Tracking**: Required equipment for each service
- **Duration Estimates**: Service time planning
- **Emergency Services**: Special handling for urgent care

### 2. Invoice Generation
- **Automatic Numbering**: Sequential invoice numbers (INV-YYYY-MM-NNNN)
- **Multi-Item Support**: Multiple services per invoice
- **Tax Calculations**: Configurable tax rates
- **Discount System**: Percentage or fixed amount discounts
- **Status Tracking**: Complete invoice lifecycle management

### 3. Payment Processing
- **Multiple Methods**: Cash, credit/debit cards, bank transfer, online payment, check, mobile payment, insurance
- **Partial Payments**: Support for installment payments
- **Refund Processing**: Full and partial refunds
- **Fee Tracking**: Processing fees and gateway charges
- **Payment History**: Complete audit trail

### 4. Role-Based Access
- **Admin**: Full system access, statistics, bulk operations
- **Doctor**: Own invoices and patient billing
- **Pet Owner**: View own invoices, payment history, online payments

## 🔗 **API Endpoints**

### Public Service Endpoints
```
GET /api/services                    # Browse available services
GET /api/services/{service}          # Service details
GET /api/services/{service}/pricing  # Get pricing information
GET /api/services/category/{category} # Services by category
GET /api/services/emergency/list     # Emergency services
GET /api/services/categories/list    # Service categories
```

### Admin Billing Management
```
# Service Management
POST   /api/admin/services           # Create service
PUT    /api/admin/services/{service} # Update service
DELETE /api/admin/services/{service} # Delete service

# Invoice Management
GET    /api/admin/invoices           # List all invoices
POST   /api/admin/invoices           # Create invoice
GET    /api/admin/invoices/statistics # Invoice statistics
GET    /api/admin/invoices/{invoice} # Invoice details
PUT    /api/admin/invoices/{invoice} # Update invoice
DELETE /api/admin/invoices/{invoice} # Delete invoice
POST   /api/admin/invoices/{invoice}/send # Send invoice
POST   /api/admin/invoices/{invoice}/mark-viewed # Mark as viewed

# Payment Management
GET    /api/admin/payments           # List all payments
POST   /api/admin/payments           # Process payment
GET    /api/admin/payments/statistics # Payment statistics
GET    /api/admin/payments/methods   # Payment method stats
GET    /api/admin/payments/{payment} # Payment details
PUT    /api/admin/payments/{payment} # Update payment
POST   /api/admin/payments/{payment}/refund # Process refund
```

### Doctor Billing
```
GET  /api/doctor/invoices           # Doctor's invoices
POST /api/doctor/invoices           # Create invoice
GET  /api/doctor/invoices/{invoice} # Invoice details
PUT  /api/doctor/invoices/{invoice} # Update invoice
```

### Pet Owner Billing
```
GET  /api/my/invoices               # User's invoices
GET  /api/my/invoices/summary       # Invoice summary
GET  /api/my/invoices/overdue       # Overdue invoices
GET  /api/my/invoices/unpaid        # Unpaid invoices
GET  /api/my/invoices/payment-history # Payment history
GET  /api/my/invoices/{invoice}     # Invoice details
GET  /api/my/invoices/{invoice}/download-pdf # Download PDF
GET  /api/my/invoices/{invoice}/print # Print format
GET  /api/my/pets/{pet_id}/invoices # Pet-specific invoices

# Payment Processing
POST /api/my/payments/online        # Online payment
GET  /api/my/payments/invoice/{invoice} # Invoice payments
```

## 💻 **Frontend Implementation Examples**

### Service Selection Component
```javascript
// Fetch available services
const fetchServices = async (category = null) => {
  const url = category 
    ? `/api/services/category/${category}`
    : '/api/services';
  
  const response = await fetch(url);
  const data = await response.json();
  return data.data.services;
};

// Service pricing calculator
const calculateServicePrice = async (serviceId, customPrice = null) => {
  const response = await fetch(`/api/services/${serviceId}/pricing`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ custom_price: customPrice })
  });
  
  const data = await response.json();
  return data.data.effective_price;
};
```

### Invoice Creation
```javascript
// Create invoice with multiple services
const createInvoice = async (invoiceData) => {
  const response = await fetch('/api/admin/invoices', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${token}`
    },
    body: JSON.stringify({
      pet_id: invoiceData.petId,
      owner_id: invoiceData.ownerId,
      doctor_id: invoiceData.doctorId,
      due_date: invoiceData.dueDate,
      tax_rate: invoiceData.taxRate,
      items: invoiceData.services.map(service => ({
        service_id: service.id,
        quantity: service.quantity,
        unit_price: service.price,
        notes: service.notes
      }))
    })
  });
  
  return await response.json();
};
```

### Payment Processing
```javascript
// Process online payment
const processPayment = async (paymentData) => {
  const response = await fetch('/api/my/payments/online', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${token}`
    },
    body: JSON.stringify({
      invoice_id: paymentData.invoiceId,
      amount: paymentData.amount,
      payment_token: paymentData.stripeToken,
      card_last_four: paymentData.cardLastFour,
      card_type: paymentData.cardType
    })
  });
  
  return await response.json();
};

// Get payment history
const getPaymentHistory = async (filters = {}) => {
  const params = new URLSearchParams(filters);
  const response = await fetch(`/api/my/invoices/payment-history?${params}`, {
    headers: { 'Authorization': `Bearer ${token}` }
  });
  
  return await response.json();
};
```

### Invoice Display Component
```javascript
const InvoiceDisplay = ({ invoice }) => {
  return (
    <div className="invoice">
      <div className="invoice-header">
        <h2>Invoice #{invoice.invoice_number}</h2>
        <div className="status-badge">{invoice.payment_status}</div>
      </div>
      
      <div className="invoice-details">
        <p>Pet: {invoice.pet.name}</p>
        <p>Doctor: {invoice.doctor.user.name}</p>
        <p>Date: {invoice.invoice_date}</p>
        <p>Due: {invoice.due_date}</p>
      </div>
      
      <div className="invoice-items">
        {invoice.items.map(item => (
          <div key={item.id} className="invoice-item">
            <span>{item.service_name}</span>
            <span>{item.quantity} × ${item.unit_price}</span>
            <span>${item.total_price}</span>
          </div>
        ))}
      </div>
      
      <div className="invoice-totals">
        <div>Subtotal: ${invoice.subtotal}</div>
        <div>Tax: ${invoice.tax_amount}</div>
        <div>Total: ${invoice.total_amount}</div>
        <div>Paid: ${invoice.paid_amount}</div>
        <div>Balance: ${invoice.balance_due}</div>
      </div>
      
      {invoice.balance_due > 0 && (
        <button onClick={() => initiatePayment(invoice)}>
          Pay Now
        </button>
      )}
    </div>
  );
};
```

## 🔒 **Security Features**

### Access Control
- **Role-based permissions**: Admin, Doctor, Pet Owner access levels
- **Ownership validation**: Users can only access their own data
- **Invoice viewing restrictions**: Secure invoice access
- **Payment authorization**: Authenticated payment processing

### Data Protection
- **Sensitive data masking**: Card numbers, personal information
- **Audit trails**: Complete payment and invoice history
- **Secure file handling**: Invoice PDF generation and storage
- **Transaction logging**: All financial operations logged

## 📈 **Business Rules**

### Invoice Management
- Invoices cannot be deleted if payments exist
- Services cannot be deleted if used in invoices
- Automatic status updates based on payment amounts
- Overdue detection and notifications

### Payment Processing
- Payment amounts cannot exceed invoice balance
- Partial payments supported with status tracking
- Refunds limited to paid amounts
- Processing fees calculated automatically

### Service Pricing
- Variable pricing within defined ranges
- Automatic service code generation
- Equipment requirements tracking
- Emergency service identification

## 🚀 **Getting Started**

### 1. Run Migrations
```bash
php artisan migrate
```

### 2. Seed Sample Data
```bash
php artisan db:seed --class=ServiceSeeder
```

### 3. Test API Endpoints
```bash
# Get available services
curl -X GET "http://localhost:8000/api/services"

# Create invoice (Admin)
curl -X POST "http://localhost:8000/api/admin/invoices" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "pet_id": 1,
    "owner_id": 1,
    "items": [
      {
        "service_id": 1,
        "quantity": 1
      }
    ]
  }'
```

## 📊 **Statistics and Reporting**

### Invoice Statistics
- Total revenue and pending amounts
- Overdue invoice tracking
- Monthly revenue trends
- Top services by revenue
- Payment status breakdowns

### Payment Analytics
- Payment method preferences
- Processing fee analysis
- Refund tracking
- Daily/monthly payment trends
- Success rate monitoring

## 🔧 **Customization Options**

### Service Configuration
- Custom service categories
- Flexible pricing models
- Equipment requirement tracking
- Duration-based scheduling

### Invoice Customization
- Custom terms and conditions
- Configurable tax rates
- Discount policies
- Payment instructions

### Payment Gateway Integration
- Stripe, PayPal, Square support
- Custom gateway implementation
- Fee calculation customization
- Currency support

---

## 📞 **Support**

For technical support or feature requests, please contact the development team or create an issue in the project repository.

**Happy Billing! 💰🐾** 