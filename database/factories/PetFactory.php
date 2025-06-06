<?php

namespace Database\Factories;

use App\Enums\GenderEnum;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Pet>
 */
class PetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $petNames = [
            'Buddy', 'Max', 'Charlie', 'Lucy', 'Cooper', 'Luna', 'Daisy', 'Milo',
            'Bella', 'Rocky', 'Molly', 'Tucker', 'Sadie', 'Bear', 'Maggie', 'Duke',
            'Roxy', 'Toby', 'Chloe', 'Jack', 'Sophie', 'Oliver', 'Lola', 'Zeus'
        ];

        $species = ['Dog', 'Cat', 'Bird', 'Rabbit', 'Hamster', 'Guinea Pig', 'Ferret'];
        
        $dogBreeds = [
            'Golden Retriever', 'Labrador Retriever', 'German Shepherd', 'Bulldog',
            'Poodle', 'Beagle', 'Rottweiler', 'Yorkshire Terrier', 'Dachshund', 'Siberian Husky'
        ];
        
        $catBreeds = [
            'Persian', 'Maine Coon', 'British Shorthair', 'Ragdoll', 'Bengal',
            'Abyssinian', 'Birman', 'Oriental Shorthair', 'Manx', 'Russian Blue'
        ];
        
        $birdBreeds = [
            'Canary', 'Parakeet', 'Cockatiel', 'Lovebird', 'Finch', 'Parrot'
        ];

        $selectedSpecies = fake()->randomElement($species);
        
        $breed = match($selectedSpecies) {
            'Dog' => fake()->randomElement($dogBreeds),
            'Cat' => fake()->randomElement($catBreeds),
            'Bird' => fake()->randomElement($birdBreeds),
            default => fake()->word()
        };

        return [
            'owner_id' => User::factory(),
            'name' => fake()->randomElement($petNames),
            'species' => $selectedSpecies,
            'breed' => $breed,
            'date_of_birth' => fake()->dateTimeBetween('-15 years', '-2 months')->format('Y-m-d'),
            'weight' => fake()->randomFloat(2, 0.5, 80),
            'gender' => fake()->randomElement(GenderEnum::cases())->value,
        ];
    }
}
