<?php

namespace Database\Factories;

use App\Models\MedicalRecord;
use App\Models\Pet;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MedicalDocument>
 */
class MedicalDocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $documentTypes = [
            'xray', 'lab_report', 'blood_work', 'ultrasound', 'ct_scan', 'mri',
            'prescription', 'vaccination_record', 'surgical_report', 
            'pathology_report', 'photo', 'other'
        ];

        $fileTypes = [
            'pdf' => ['application/pdf', '.pdf'],
            'jpg' => ['image/jpeg', '.jpg'],
            'png' => ['image/png', '.png'],
            'doc' => ['application/msword', '.doc'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', '.docx'],
        ];

        $selectedFileType = $this->faker->randomElement(array_keys($fileTypes));
        $mimeType = $fileTypes[$selectedFileType][0];
        $extension = $fileTypes[$selectedFileType][1];

        $documentType = $this->faker->randomElement($documentTypes);
        $fileName = $this->generateFileName($documentType, $extension);

        return [
            'medical_record_id' => $this->faker->optional(0.8)->passthrough(MedicalRecord::factory()),
            'pet_id' => Pet::factory(),
            'uploaded_by' => User::factory(),
            'type' => $documentType,
            'title' => $this->generateTitle($documentType),
            'description' => $this->faker->optional(0.7)->sentence(),
            'file_name' => $fileName,
            'file_path' => 'medical_documents/' . $this->faker->date('Y/m') . '/' . $fileName,
            'file_type' => $mimeType,
            'file_size' => $this->faker->numberBetween(50000, 5000000), // 50KB to 5MB
            'file_hash' => $this->faker->optional(0.8)->sha256(),
            'document_date' => $this->faker->optional(0.9)->dateTimeBetween('-2 years', 'now')?->format('Y-m-d'),
            'tags' => $this->faker->optional(0.5)->words(3),
            'is_sensitive' => $this->faker->boolean(20), // 20% sensitive
            'is_archived' => $this->faker->boolean(10), // 10% archived
            'visibility' => $this->faker->randomElement(['private', 'doctor_only', 'owner_and_doctor', 'public']),
        ];
    }

    /**
     * Create a document for a specific medical record.
     */
    public function forMedicalRecord(MedicalRecord $medicalRecord): static
    {
        return $this->state(fn (array $attributes) => [
            'medical_record_id' => $medicalRecord->id,
            'pet_id' => $medicalRecord->pet_id,
        ]);
    }

    /**
     * Create a document for a specific pet.
     */
    public function forPet(Pet $pet): static
    {
        return $this->state(fn (array $attributes) => [
            'pet_id' => $pet->id,
        ]);
    }

    /**
     * Create an X-ray document.
     */
    public function xray(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'xray',
            'title' => 'X-Ray - ' . $this->faker->randomElement(['Chest', 'Abdomen', 'Leg', 'Hip', 'Spine']),
            'file_type' => 'image/jpeg',
            'file_name' => 'xray_' . $this->faker->date('Ymd') . '_' . $this->faker->randomNumber(4) . '.jpg',
        ]);
    }

    /**
     * Create a lab report document.
     */
    public function labReport(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'lab_report',
            'title' => $this->faker->randomElement([
                'Blood Chemistry Panel',
                'Complete Blood Count',
                'Urinalysis',
                'Fecal Examination',
                'Thyroid Panel'
            ]),
            'file_type' => 'application/pdf',
            'file_name' => 'lab_report_' . $this->faker->date('Ymd') . '_' . $this->faker->randomNumber(4) . '.pdf',
        ]);
    }

    /**
     * Create a prescription document.
     */
    public function prescription(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'prescription',
            'title' => 'Prescription - ' . $this->faker->randomElement([
                'Amoxicillin', 'Prednisone', 'Metacam', 'Tramadol'
            ]),
            'file_type' => 'application/pdf',
            'file_name' => 'prescription_' . $this->faker->date('Ymd') . '_' . $this->faker->randomNumber(4) . '.pdf',
        ]);
    }

    /**
     * Create a vaccination record document.
     */
    public function vaccinationRecord(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'vaccination_record',
            'title' => 'Vaccination Record - ' . $this->faker->randomElement([
                'DHPP', 'Rabies', 'Bordetella', 'Lyme Disease'
            ]),
            'file_type' => 'application/pdf',
            'file_name' => 'vaccination_' . $this->faker->date('Ymd') . '_' . $this->faker->randomNumber(4) . '.pdf',
        ]);
    }

    /**
     * Create a surgical report document.
     */
    public function surgicalReport(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'surgical_report',
            'title' => 'Surgery Report - ' . $this->faker->randomElement([
                'Spay Surgery', 'Neuter Surgery', 'Tumor Removal', 'Dental Extraction'
            ]),
            'file_type' => 'application/pdf',
            'file_name' => 'surgery_report_' . $this->faker->date('Ymd') . '_' . $this->faker->randomNumber(4) . '.pdf',
        ]);
    }

    /**
     * Create a private document.
     */
    public function private(): static
    {
        return $this->state(fn (array $attributes) => [
            'visibility' => 'private',
        ]);
    }

    /**
     * Create a document shared with owner and doctor.
     */
    public function sharedWithOwner(): static
    {
        return $this->state(fn (array $attributes) => [
            'visibility' => 'owner_and_doctor',
        ]);
    }

    /**
     * Create a public document.
     */
    public function public(): static
    {
        return $this->state(fn (array $attributes) => [
            'visibility' => 'public',
        ]);
    }

    /**
     * Create a sensitive document.
     */
    public function sensitive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_sensitive' => true,
            'visibility' => 'private',
        ]);
    }

    /**
     * Create an archived document.
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_archived' => true,
        ]);
    }

    /**
     * Create a PDF document.
     */
    public function pdf(): static
    {
        return $this->state(fn (array $attributes) => [
            'file_type' => 'application/pdf',
            'file_name' => str_replace(['.jpg', '.png', '.doc', '.docx'], '.pdf', 
                $attributes['file_name'] ?? 'document_' . $this->faker->randomNumber(6) . '.pdf'
            ),
        ]);
    }

    /**
     * Create an image document.
     */
    public function image(): static
    {
        $imageType = $this->faker->randomElement(['jpg', 'png']);
        $mimeType = $imageType === 'jpg' ? 'image/jpeg' : 'image/png';
        
        return $this->state(fn (array $attributes) => [
            'file_type' => $mimeType,
            'file_name' => str_replace(['.pdf', '.doc', '.docx'], '.' . $imageType, 
                $attributes['file_name'] ?? 'image_' . $this->faker->randomNumber(6) . '.' . $imageType
            ),
        ]);
    }

    /**
     * Generate a file name based on document type.
     */
    private function generateFileName(string $documentType, string $extension): string
    {
        $prefix = match($documentType) {
            'xray' => 'xray',
            'lab_report' => 'lab_report',
            'blood_work' => 'blood_work',
            'ultrasound' => 'ultrasound',
            'ct_scan' => 'ct_scan',
            'mri' => 'mri',
            'prescription' => 'prescription',
            'vaccination_record' => 'vaccination',
            'surgical_report' => 'surgery_report',
            'pathology_report' => 'pathology',
            'photo' => 'photo',
            default => 'document',
        };

        return $prefix . '_' . $this->faker->date('Ymd') . '_' . $this->faker->randomNumber(6) . $extension;
    }

    /**
     * Generate a title based on document type.
     */
    private function generateTitle(string $documentType): string
    {
        return match($documentType) {
            'xray' => 'X-Ray - ' . $this->faker->randomElement(['Chest', 'Abdomen', 'Leg', 'Hip']),
            'lab_report' => $this->faker->randomElement(['Blood Chemistry Panel', 'Complete Blood Count', 'Urinalysis']),
            'blood_work' => 'Blood Work - ' . $this->faker->randomElement(['CBC', 'Chemistry Panel', 'Thyroid Panel']),
            'ultrasound' => 'Ultrasound - ' . $this->faker->randomElement(['Abdominal', 'Cardiac', 'Pregnancy']),
            'ct_scan' => 'CT Scan - ' . $this->faker->randomElement(['Head', 'Chest', 'Abdomen']),
            'mri' => 'MRI - ' . $this->faker->randomElement(['Brain', 'Spine', 'Joint']),
            'prescription' => 'Prescription - ' . $this->faker->randomElement(['Amoxicillin', 'Prednisone', 'Metacam']),
            'vaccination_record' => 'Vaccination - ' . $this->faker->randomElement(['DHPP', 'Rabies', 'Bordetella']),
            'surgical_report' => 'Surgery Report - ' . $this->faker->randomElement(['Spay', 'Neuter', 'Tumor Removal']),
            'pathology_report' => 'Pathology Report - ' . $this->faker->randomElement(['Biopsy', 'Cytology', 'Necropsy']),
            'photo' => 'Photo - ' . $this->faker->randomElement(['Wound', 'Lesion', 'Post-op', 'Before/After']),
            default => 'Medical Document',
        };
    }
} 