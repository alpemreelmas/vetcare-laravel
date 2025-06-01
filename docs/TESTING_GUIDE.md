# 🧪 VetCare Testing Guide

## 🎯 **Overview**

This guide provides comprehensive testing strategies and procedures for the VetCare veterinary management system, covering unit tests, feature tests, API testing, and quality assurance processes.

## 🏗️ **Testing Architecture**

### **Testing Pyramid**
```
                    /\
                   /  \
                  / E2E \
                 /______\
                /        \
               /Integration\
              /__________\
             /            \
            /  Unit Tests  \
           /________________\
```

### **Test Types**
- **Unit Tests**: Individual components and business logic
- **Feature Tests**: API endpoints and user workflows
- **Integration Tests**: Database interactions and external services
- **End-to-End Tests**: Complete user journeys

## 📁 **Test Structure**

```
tests/
├── Feature/                    # Feature tests (API endpoints)
│   ├── Auth/
│   ├── Appointments/
│   ├── Pets/
│   ├── Medical/
│   └── Billing/
├── Unit/                       # Unit tests (business logic)
│   ├── Services/
│   ├── Models/
│   └── Helpers/
├── Integration/                # Integration tests
│   ├── Database/
│   └── External/
└── TestCase.php               # Base test class
```

## 🔧 **Test Environment Setup**

### **PHPUnit Configuration**
```xml
<!-- phpunit.xml -->
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="./vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true">
    <testsuites>
        <testsuite name="Unit">
            <directory suffix="Test.php">./tests/Unit</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory suffix="Test.php">./tests/Feature</directory>
        </testsuite>
        <testsuite name="Integration">
            <directory suffix="Test.php">./tests/Integration</directory>
        </testsuite>
    </testsuites>
    <coverage>
        <include>
            <directory suffix=".php">./app</directory>
        </include>
        <exclude>
            <directory>./app/Console</directory>
            <directory>./app/Exceptions</directory>
            <directory>./app/Http/Middleware</directory>
        </exclude>
    </coverage>
    <php>
        <env name="APP_ENV" value="testing"/>
        <env name="BCRYPT_ROUNDS" value="4"/>
        <env name="CACHE_DRIVER" value="array"/>
        <env name="DB_CONNECTION" value="sqlite"/>
        <env name="DB_DATABASE" value=":memory:"/>
        <env name="MAIL_MAILER" value="array"/>
        <env name="QUEUE_CONNECTION" value="sync"/>
        <env name="SESSION_DRIVER" value="array"/>
    </php>
</phpunit>
```

### **Test Environment Configuration**
```env
# .env.testing
APP_NAME="VetCare Testing"
APP_ENV=testing
APP_KEY=base64:test_key_here
APP_DEBUG=true
APP_URL=http://localhost

DB_CONNECTION=sqlite
DB_DATABASE=:memory:

CACHE_DRIVER=array
QUEUE_CONNECTION=sync
SESSION_DRIVER=array
MAIL_MAILER=array

FILESYSTEM_DISK=local
```

## 🧪 **Unit Testing**

### **Service Layer Tests**

#### **AppointmentBookingService Test**
```php
<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\AppointmentBookingService;
use App\Data\Appointments\BookAppointmentData;
use App\Models\User;
use App\Models\Doctor;
use App\Models\Pet;
use App\Models\Appointment;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AppointmentBookingServiceTest extends TestCase
{
    use RefreshDatabase;

    private AppointmentBookingService $service;
    private User $user;
    private Doctor $doctor;
    private Pet $pet;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->service = app(AppointmentBookingService::class);
        
        // Create test data
        $this->user = User::factory()->create();
        $this->doctor = Doctor::factory()->create();
        $this->pet = Pet::factory()->create(['owner_id' => $this->user->id]);
    }

    public function test_can_book_appointment_with_valid_data()
    {
        $data = new BookAppointmentData(
            doctor_id: $this->doctor->id,
            pet_id: $this->pet->id,
            date: '2024-01-15',
            time: '14:00',
            duration: 30,
            appointment_type: 'regular'
        );

        $appointment = $this->service->bookAppointment($data);

        $this->assertInstanceOf(Appointment::class, $appointment);
        $this->assertEquals($this->doctor->id, $appointment->doctor_id);
        $this->assertEquals($this->pet->id, $appointment->pet_id);
        $this->assertEquals('2024-01-15', $appointment->appointment_date->format('Y-m-d'));
    }

    public function test_cannot_book_appointment_with_invalid_pet_ownership()
    {
        $otherUser = User::factory()->create();
        $otherPet = Pet::factory()->create(['owner_id' => $otherUser->id]);

        $data = new BookAppointmentData(
            doctor_id: $this->doctor->id,
            pet_id: $otherPet->id,
            date: '2024-01-15',
            time: '14:00',
            duration: 30,
            appointment_type: 'regular'
        );

        $this->expectException(\App\Exceptions\AppointmentException::class);
        $this->expectExceptionMessage('You can only book appointments for your own pets');

        $this->actingAs($this->user);
        $this->service->bookAppointment($data);
    }

    public function test_cannot_book_appointment_when_doctor_unavailable()
    {
        // Create existing appointment
        Appointment::factory()->create([
            'doctor_id' => $this->doctor->id,
            'appointment_date' => '2024-01-15',
            'appointment_time' => '14:00:00',
            'duration' => 30,
        ]);

        $data = new BookAppointmentData(
            doctor_id: $this->doctor->id,
            pet_id: $this->pet->id,
            date: '2024-01-15',
            time: '14:00',
            duration: 30,
            appointment_type: 'regular'
        );

        $this->expectException(\App\Exceptions\AppointmentException::class);
        $this->expectExceptionMessage('Doctor is not available at the requested time');

        $this->actingAs($this->user);
        $this->service->bookAppointment($data);
    }
}
```

#### **TreatmentBillingService Test**
```php
<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\TreatmentBillingService;
use App\Models\Treatment;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\MedicalRecord;
use App\Models\Pet;
use App\Models\User;
use App\Models\Doctor;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TreatmentBillingServiceTest extends TestCase
{
    use RefreshDatabase;

    private TreatmentBillingService $service;
    private Treatment $treatment;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->service = app(TreatmentBillingService::class);
        
        // Create test data
        $user = User::factory()->create();
        $doctor = Doctor::factory()->create();
        $pet = Pet::factory()->create(['owner_id' => $user->id]);
        $medicalRecord = MedicalRecord::factory()->create([
            'pet_id' => $pet->id,
            'doctor_id' => $doctor->id,
        ]);
        
        $this->treatment = Treatment::factory()->create([
            'medical_record_id' => $medicalRecord->id,
            'pet_id' => $pet->id,
            'cost' => 45.00,
            'name' => 'Antibiotics',
            'type' => 'medication',
        ]);
    }

    public function test_creates_invoice_for_payable_treatment()
    {
        $invoice = $this->service->createInvoiceForTreatment($this->treatment);

        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertEquals($this->treatment->cost, $invoice->total_amount);
        $this->assertCount(1, $invoice->items);
        $this->assertEquals($this->treatment->id, $invoice->items->first()->metadata['treatment_id']);
    }

    public function test_does_not_create_invoice_for_free_treatment()
    {
        $this->treatment->update(['cost' => 0]);

        $invoice = $this->service->createInvoiceForTreatment($this->treatment);

        $this->assertNull($invoice);
    }

    public function test_creates_service_for_new_treatment_type()
    {
        $initialServiceCount = Service::count();

        $this->service->createInvoiceForTreatment($this->treatment);

        $this->assertEquals($initialServiceCount + 1, Service::count());
        
        $service = Service::where('name', $this->treatment->name)->first();
        $this->assertNotNull($service);
        $this->assertEquals('treatment', $service->category);
        $this->assertEquals($this->treatment->cost, $service->base_price);
    }

    public function test_updates_invoice_when_treatment_cost_changes()
    {
        $invoice = $this->service->createInvoiceForTreatment($this->treatment);
        $originalAmount = $invoice->total_amount;

        $this->treatment->update(['cost' => 60.00]);
        $updatedInvoice = $this->service->updateInvoiceForTreatment($this->treatment);

        $this->assertNotEquals($originalAmount, $updatedInvoice->total_amount);
        $this->assertEquals(60.00, $updatedInvoice->total_amount);
    }
}
```

### **Model Tests**

#### **Pet Model Test**
```php
<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\Pet;
use App\Models\User;
use App\Models\Appointment;
use App\Models\MedicalRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PetTest extends TestCase
{
    use RefreshDatabase;

    public function test_pet_belongs_to_owner()
    {
        $user = User::factory()->create();
        $pet = Pet::factory()->create(['owner_id' => $user->id]);

        $this->assertInstanceOf(User::class, $pet->owner);
        $this->assertEquals($user->id, $pet->owner->id);
    }

    public function test_pet_has_many_appointments()
    {
        $pet = Pet::factory()->create();
        $appointments = Appointment::factory()->count(3)->create(['pet_id' => $pet->id]);

        $this->assertCount(3, $pet->appointments);
        $this->assertInstanceOf(Appointment::class, $pet->appointments->first());
    }

    public function test_pet_age_calculation()
    {
        $pet = Pet::factory()->create([
            'date_of_birth' => now()->subYears(2)->subMonths(6)
        ]);

        $age = $pet->getAgeAttribute();
        
        $this->assertStringContains('2 years', $age);
        $this->assertStringContains('6 months', $age);
    }

    public function test_pet_scope_by_species()
    {
        Pet::factory()->create(['species' => 'dog']);
        Pet::factory()->create(['species' => 'cat']);
        Pet::factory()->create(['species' => 'dog']);

        $dogs = Pet::bySpecies('dog')->get();
        $cats = Pet::bySpecies('cat')->get();

        $this->assertCount(2, $dogs);
        $this->assertCount(1, $cats);
    }

    public function test_pet_medical_summary()
    {
        $pet = Pet::factory()->create();
        MedicalRecord::factory()->count(5)->create(['pet_id' => $pet->id]);

        $summary = $pet->getMedicalSummary();

        $this->assertArrayHasKey('total_visits', $summary);
        $this->assertArrayHasKey('last_visit', $summary);
        $this->assertEquals(5, $summary['total_visits']);
    }
}
```

## 🌐 **Feature Testing (API Tests)**

### **Authentication Tests**

#### **Auth Feature Test**
```php
<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register()
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'user' => ['id', 'name', 'email'],
                        'token'
                    ]
                ]);

        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com'
        ]);
    }

    public function test_user_can_login_with_valid_credentials()
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'password' => Hash::make('password'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'john@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'user' => ['id', 'name', 'email'],
                        'token'
                    ]
                ]);
    }

    public function test_user_cannot_login_with_invalid_credentials()
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'password' => Hash::make('password'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'john@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401)
                ->assertJson([
                    'success' => false,
                    'message' => 'Invalid credentials'
                ]);
    }

    public function test_authenticated_user_can_logout()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/auth/logout');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Logged out successfully'
                ]);
    }
}
```

### **Appointment Tests**

#### **Appointment Feature Test**
```php
<?php

namespace Tests\Feature\Appointments;

use Tests\TestCase;
use App\Models\User;
use App\Models\Doctor;
use App\Models\Pet;
use App\Models\Appointment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class AppointmentTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Doctor $doctor;
    private Pet $pet;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->doctor = Doctor::factory()->create();
        $this->pet = Pet::factory()->create(['owner_id' => $this->user->id]);
        
        Sanctum::actingAs($this->user);
    }

    public function test_user_can_book_appointment()
    {
        $response = $this->postJson('/api/appointments', [
            'doctor_id' => $this->doctor->id,
            'pet_id' => $this->pet->id,
            'date' => '2024-01-15',
            'time' => '14:00',
            'duration' => 30,
            'appointment_type' => 'regular',
            'notes' => 'Regular checkup'
        ]);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'appointment' => [
                            'id',
                            'doctor_id',
                            'pet_id',
                            'appointment_date',
                            'appointment_time',
                            'status'
                        ]
                    ]
                ]);

        $this->assertDatabaseHas('appointments', [
            'doctor_id' => $this->doctor->id,
            'pet_id' => $this->pet->id,
            'appointment_date' => '2024-01-15',
        ]);
    }

    public function test_user_cannot_book_appointment_for_other_users_pet()
    {
        $otherUser = User::factory()->create();
        $otherPet = Pet::factory()->create(['owner_id' => $otherUser->id]);

        $response = $this->postJson('/api/appointments', [
            'doctor_id' => $this->doctor->id,
            'pet_id' => $otherPet->id,
            'date' => '2024-01-15',
            'time' => '14:00',
            'duration' => 30,
            'appointment_type' => 'regular'
        ]);

        $response->assertStatus(403)
                ->assertJson([
                    'success' => false,
                    'message' => 'You can only book appointments for your own pets'
                ]);
    }

    public function test_user_can_view_their_appointments()
    {
        $appointments = Appointment::factory()->count(3)->create([
            'pet_id' => $this->pet->id,
            'doctor_id' => $this->doctor->id,
        ]);

        $response = $this->getJson('/api/appointments');

        $response->assertStatus(200)
                ->assertJsonCount(3, 'data.appointments');
    }

    public function test_user_can_cancel_appointment()
    {
        $appointment = Appointment::factory()->create([
            'pet_id' => $this->pet->id,
            'doctor_id' => $this->doctor->id,
            'status' => 'scheduled'
        ]);

        $response = $this->patchJson("/api/appointments/{$appointment->id}/cancel");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Appointment cancelled successfully'
                ]);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'cancelled'
        ]);
    }

    public function test_guest_can_view_calendar_availability()
    {
        $response = $this->getJson('/api/calendar?start_date=2024-01-15&end_date=2024-01-20');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'availability' => [
                            '*' => [
                                'date',
                                'slots' => [
                                    '*' => [
                                        'doctor_id',
                                        'doctor_name',
                                        'start_time',
                                        'end_time',
                                        'available'
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]);
    }

    public function test_guest_can_find_available_doctors()
    {
        $response = $this->getJson('/api/available-doctors?date=2024-01-15&time=14:00');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'doctors' => [
                            '*' => [
                                'id',
                                'name',
                                'specialization',
                                'slot' => [
                                    'start',
                                    'end',
                                    'date'
                                ]
                            ]
                        ],
                        'total_available'
                    ]
                ]);
    }
}
```

### **Medical Records Tests**

#### **Medical Records Feature Test**
```php
<?php

namespace Tests\Feature\Medical;

use Tests\TestCase;
use App\Models\User;
use App\Models\Doctor;
use App\Models\Pet;
use App\Models\MedicalRecord;
use App\Models\Treatment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

class MedicalRecordsTest extends TestCase
{
    use RefreshDatabase;

    private User $doctor;
    private User $petOwner;
    private Pet $pet;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create roles
        Role::create(['name' => 'doctor']);
        Role::create(['name' => 'user']);
        
        // Create doctor user
        $this->doctor = User::factory()->create();
        $this->doctor->assignRole('doctor');
        Doctor::factory()->create(['user_id' => $this->doctor->id]);
        
        // Create pet owner
        $this->petOwner = User::factory()->create();
        $this->petOwner->assignRole('user');
        $this->pet = Pet::factory()->create(['owner_id' => $this->petOwner->id]);
    }

    public function test_doctor_can_create_medical_record()
    {
        Sanctum::actingAs($this->doctor);

        $response = $this->postJson('/api/doctor/medical-records', [
            'pet_id' => $this->pet->id,
            'visit_date' => '2024-01-15',
            'visit_type' => 'routine_checkup',
            'chief_complaint' => 'Annual checkup',
            'physical_examination' => 'Normal examination',
            'vital_signs' => [
                'temperature' => 38.5,
                'weight' => 25.5,
                'heart_rate' => 120
            ],
            'assessment' => 'Healthy dog',
            'plan' => 'Continue current care'
        ]);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'medical_record' => [
                            'id',
                            'pet_id',
                            'doctor_id',
                            'visit_date',
                            'chief_complaint'
                        ]
                    ]
                ]);

        $this->assertDatabaseHas('medical_records', [
            'pet_id' => $this->pet->id,
            'chief_complaint' => 'Annual checkup'
        ]);
    }

    public function test_doctor_can_add_treatment_with_automatic_billing()
    {
        Sanctum::actingAs($this->doctor);

        $medicalRecord = MedicalRecord::factory()->create([
            'pet_id' => $this->pet->id,
            'doctor_id' => $this->doctor->doctor->id
        ]);

        $response = $this->postJson('/api/doctor/treatments', [
            'medical_record_id' => $medicalRecord->id,
            'type' => 'medication',
            'name' => 'Antibiotics',
            'medication_name' => 'Amoxicillin',
            'dosage' => '250mg',
            'frequency' => 'Twice daily',
            'start_date' => '2024-01-15',
            'cost' => 45.00
        ]);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'treatment' => ['id', 'name', 'cost'],
                        'billing_info' => [
                            'invoice_id',
                            'invoice_number',
                            'amount',
                            'is_billed'
                        ]
                    ]
                ]);

        $this->assertDatabaseHas('treatments', [
            'medical_record_id' => $medicalRecord->id,
            'name' => 'Antibiotics',
            'cost' => 45.00
        ]);

        $this->assertDatabaseHas('invoices', [
            'pet_id' => $this->pet->id,
            'total_amount' => 45.00
        ]);
    }

    public function test_pet_owner_can_view_their_pets_medical_history()
    {
        Sanctum::actingAs($this->petOwner);

        $medicalRecords = MedicalRecord::factory()->count(3)->create([
            'pet_id' => $this->pet->id
        ]);

        $response = $this->getJson("/api/my/pets/{$this->pet->id}/medical-history");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'pet' => ['id', 'name'],
                        'medical_records' => [
                            '*' => [
                                'id',
                                'visit_date',
                                'visit_type',
                                'doctor'
                            ]
                        ]
                    ]
                ]);
    }

    public function test_pet_owner_cannot_view_other_pets_medical_history()
    {
        $otherUser = User::factory()->create();
        $otherPet = Pet::factory()->create(['owner_id' => $otherUser->id]);

        Sanctum::actingAs($this->petOwner);

        $response = $this->getJson("/api/my/pets/{$otherPet->id}/medical-history");

        $response->assertStatus(404);
    }
}
```

## 🔄 **Integration Testing**

### **Database Integration Tests**

#### **Database Relationships Test**
```php
<?php

namespace Tests\Integration\Database;

use Tests\TestCase;
use App\Models\User;
use App\Models\Doctor;
use App\Models\Pet;
use App\Models\Appointment;
use App\Models\MedicalRecord;
use App\Models\Treatment;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DatabaseRelationshipsTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_medical_workflow_relationships()
    {
        // Create user and doctor
        $user = User::factory()->create();
        $doctor = Doctor::factory()->create();
        
        // Create pet
        $pet = Pet::factory()->create(['owner_id' => $user->id]);
        
        // Create appointment
        $appointment = Appointment::factory()->create([
            'doctor_id' => $doctor->id,
            'pet_id' => $pet->id
        ]);
        
        // Create medical record
        $medicalRecord = MedicalRecord::factory()->create([
            'appointment_id' => $appointment->id,
            'pet_id' => $pet->id,
            'doctor_id' => $doctor->id
        ]);
        
        // Create treatment with cost
        $treatment = Treatment::factory()->create([
            'medical_record_id' => $medicalRecord->id,
            'pet_id' => $pet->id,
            'cost' => 50.00
        ]);

        // Verify relationships
        $this->assertEquals($user->id, $pet->owner->id);
        $this->assertEquals($doctor->id, $appointment->doctor->id);
        $this->assertEquals($pet->id, $appointment->pet->id);
        $this->assertEquals($appointment->id, $medicalRecord->appointment->id);
        $this->assertEquals($medicalRecord->id, $treatment->medicalRecord->id);
        
        // Verify automatic billing created invoice
        $this->assertDatabaseHas('invoices', [
            'pet_id' => $pet->id,
            'owner_id' => $user->id,
            'doctor_id' => $doctor->id
        ]);
    }

    public function test_cascade_deletions_work_correctly()
    {
        $user = User::factory()->create();
        $pet = Pet::factory()->create(['owner_id' => $user->id]);
        $appointment = Appointment::factory()->create(['pet_id' => $pet->id]);

        // Delete user should cascade to pets and appointments
        $user->delete();

        $this->assertDatabaseMissing('pets', ['id' => $pet->id]);
        $this->assertDatabaseMissing('appointments', ['id' => $appointment->id]);
    }
}
```

## 📊 **Test Data Management**

### **Test Factories**

#### **Enhanced Pet Factory**
```php
<?php

namespace Database\Factories;

use App\Models\Pet;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PetFactory extends Factory
{
    protected $model = Pet::class;

    public function definition(): array
    {
        $species = $this->faker->randomElement(['dog', 'cat', 'bird', 'rabbit']);
        
        return [
            'owner_id' => User::factory(),
            'name' => $this->faker->firstName(),
            'species' => $species,
            'breed' => $this->getBreedForSpecies($species),
            'date_of_birth' => $this->faker->dateTimeBetween('-10 years', '-1 month'),
            'gender' => $this->faker->randomElement(['male', 'female']),
            'weight' => $this->faker->randomFloat(2, 1, 50),
            'color' => $this->faker->colorName(),
            'microchip_number' => $this->faker->numerify('############'),
        ];
    }

    public function dog(): static
    {
        return $this->state(fn (array $attributes) => [
            'species' => 'dog',
            'breed' => $this->faker->randomElement([
                'Golden Retriever', 'Labrador', 'German Shepherd', 'Bulldog'
            ]),
        ]);
    }

    public function cat(): static
    {
        return $this->state(fn (array $attributes) => [
            'species' => 'cat',
            'breed' => $this->faker->randomElement([
                'Persian', 'Siamese', 'Maine Coon', 'British Shorthair'
            ]),
        ]);
    }

    private function getBreedForSpecies(string $species): string
    {
        $breeds = [
            'dog' => ['Golden Retriever', 'Labrador', 'German Shepherd'],
            'cat' => ['Persian', 'Siamese', 'Maine Coon'],
            'bird' => ['Canary', 'Parakeet', 'Cockatiel'],
            'rabbit' => ['Holland Lop', 'Netherland Dwarf', 'Mini Rex'],
        ];

        return $this->faker->randomElement($breeds[$species] ?? ['Mixed']);
    }
}
```

### **Test Seeders**

#### **Test Data Seeder**
```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Doctor;
use App\Models\Pet;
use App\Models\Appointment;
use App\Models\MedicalRecord;
use App\Models\Treatment;
use Spatie\Permission\Models\Role;

class TestDataSeeder extends Seeder
{
    public function run(): void
    {
        // Create roles
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $doctorRole = Role::firstOrCreate(['name' => 'doctor']);
        $userRole = Role::firstOrCreate(['name' => 'user']);

        // Create admin user
        $admin = User::factory()->create([
            'name' => 'Test Admin',
            'email' => 'admin@test.com',
        ]);
        $admin->assignRole($adminRole);

        // Create doctor users
        $doctors = User::factory()->count(3)->create();
        foreach ($doctors as $doctorUser) {
            $doctorUser->assignRole($doctorRole);
            Doctor::factory()->create(['user_id' => $doctorUser->id]);
        }

        // Create pet owners
        $petOwners = User::factory()->count(5)->create();
        foreach ($petOwners as $owner) {
            $owner->assignRole($userRole);
            
            // Create pets for each owner
            $pets = Pet::factory()->count(rand(1, 3))->create([
                'owner_id' => $owner->id
            ]);

            // Create appointments and medical records
            foreach ($pets as $pet) {
                $appointments = Appointment::factory()->count(rand(1, 3))->create([
                    'pet_id' => $pet->id,
                    'doctor_id' => Doctor::inRandomOrder()->first()->id,
                ]);

                foreach ($appointments as $appointment) {
                    $medicalRecord = MedicalRecord::factory()->create([
                        'appointment_id' => $appointment->id,
                        'pet_id' => $pet->id,
                        'doctor_id' => $appointment->doctor_id,
                    ]);

                    // Create treatments (some with costs for billing)
                    Treatment::factory()->count(rand(0, 2))->create([
                        'medical_record_id' => $medicalRecord->id,
                        'pet_id' => $pet->id,
                        'cost' => rand(0, 1) ? fake()->randomFloat(2, 20, 200) : null,
                    ]);
                }
            }
        }
    }
}
```

## 🚀 **Running Tests**

### **Basic Test Commands**
```bash
# Run all tests
php artisan test

# Run specific test suite
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature
php artisan test --testsuite=Integration

# Run tests with coverage
php artisan test --coverage

# Run specific test file
php artisan test tests/Feature/Auth/AuthTest.php

# Run specific test method
php artisan test --filter test_user_can_login

# Run tests in parallel
php artisan test --parallel

# Run tests with detailed output
php artisan test --verbose
```

### **Test Environment Setup**
```bash
# Create test database
php artisan migrate --env=testing

# Seed test data
php artisan db:seed --class=TestDataSeeder --env=testing

# Clear test cache
php artisan cache:clear --env=testing
php artisan config:clear --env=testing
```

## 📊 **Test Coverage and Quality**

### **Coverage Requirements**
- **Minimum Coverage**: 80%
- **Critical Components**: 95%
- **Service Layer**: 90%
- **Controllers**: 85%

### **Quality Metrics**
```bash
# Generate coverage report
php artisan test --coverage --min=80

# Generate HTML coverage report
php artisan test --coverage-html coverage-report

# Check code quality with PHPStan
./vendor/bin/phpstan analyse

# Check code style with PHP CS Fixer
./vendor/bin/php-cs-fixer fix --dry-run --diff
```

## 🔄 **Continuous Integration**

### **GitHub Actions Workflow**
```yaml
# .github/workflows/tests.yml
name: Tests

on:
  push:
    branches: [ main, develop ]
  pull_request:
    branches: [ main ]

jobs:
  test:
    runs-on: ubuntu-latest
    
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: password
          MYSQL_DATABASE: vetcare_test
        ports:
          - 3306:3306
        options: --health-cmd="mysqladmin ping" --health-interval=10s --health-timeout=5s --health-retries=3

    steps:
    - uses: actions/checkout@v3

    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '8.1'
        extensions: mbstring, dom, fileinfo, mysql, gd, zip
        coverage: xdebug

    - name: Copy .env
      run: php -r "file_exists('.env') || copy('.env.testing', '.env');"

    - name: Install Dependencies
      run: composer install -q --no-ansi --no-interaction --no-scripts --no-progress --prefer-dist

    - name: Generate key
      run: php artisan key:generate

    - name: Directory Permissions
      run: chmod -R 777 storage bootstrap/cache

    - name: Run Tests
      run: php artisan test --coverage --min=80
      env:
        DB_CONNECTION: mysql
        DB_HOST: 127.0.0.1
        DB_PORT: 3306
        DB_DATABASE: vetcare_test
        DB_USERNAME: root
        DB_PASSWORD: password

    - name: Upload coverage reports
      uses: codecov/codecov-action@v3
```

## 🐛 **Debugging Tests**

### **Common Test Issues**

#### **Database State Issues**
```php
// Use RefreshDatabase trait
use Illuminate\Foundation\Testing\RefreshDatabase;

class MyTest extends TestCase
{
    use RefreshDatabase;
    
    // Test methods...
}
```

#### **Authentication Issues**
```php
// Use Sanctum for API authentication
use Laravel\Sanctum\Sanctum;

public function test_authenticated_endpoint()
{
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    
    $response = $this->getJson('/api/protected-endpoint');
    // Assertions...
}
```

#### **Time-Sensitive Tests**
```php
// Use Carbon for time manipulation
use Illuminate\Support\Facades\Date;

public function test_time_sensitive_feature()
{
    Date::setTestNow('2024-01-15 14:00:00');
    
    // Test logic...
    
    Date::setTestNow(); // Reset
}
```

### **Test Debugging Tools**
```bash
# Debug specific test
php artisan test --filter test_name --stop-on-failure

# Run tests with debug output
php artisan test --verbose --debug

# Use dd() in tests for debugging
public function test_something()
{
    $response = $this->getJson('/api/endpoint');
    dd($response->json()); // Debug output
}
```

---

This testing guide provides comprehensive coverage of testing strategies, implementation examples, and best practices for ensuring the quality and reliability of the VetCare veterinary management system. 