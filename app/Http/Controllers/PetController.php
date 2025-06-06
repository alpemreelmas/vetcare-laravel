<?php

namespace App\Http\Controllers;

use App\Core\Helpers\ResponseHelper;
use App\Http\Requests\StorePetRequest;
use App\Http\Requests\UpdatePetRequest;
use App\Models\Pet;

class PetController extends Controller
{
    /**
     * Display a listing of the user's pets.
     * Users can only see their own pets.
     */
    public function index(): \Illuminate\Http\JsonResponse
    {
        $user = auth()->user();
        
        // Users can only see their own pets
        $pets = Pet::where('owner_id', $user->id)
            ->with('owner')
            ->get();

        return ResponseHelper::success('Pets retrieved successfully', [
            'pets' => $pets,
            'total' => $pets->count()
        ]);
    }

    /**
     * Store a newly created pet for the authenticated user.
     */
    public function store(StorePetRequest $request)
    {
        $user = auth()->user();
        
        $pet = Pet::create(array_merge($request->validated(), [
            'owner_id' => $user->id
        ]));

        return ResponseHelper::success('Pet created successfully', [
            'pet' => $pet->load('owner')
        ], 201);
    }

    /**
     * Display the specified pet.
     * Users can only view their own pets.
     */
    public function show(Pet $pet)
    {
        $user = auth()->user();
        
        // Check if user owns this pet
        if ($pet->owner_id !== $user->id) {
            return ResponseHelper::error('Pet not found or you do not own this pet', 404);
        }

        // TODO: Implement medical records and other relations too.
        return ResponseHelper::success('Pet retrieved successfully', [
            'pet' => $pet->load('owner')
        ]);
    }

    /**
     * Update the specified pet.
     * Users can only update their own pets.
     */
    public function update(UpdatePetRequest $request, Pet $pet)
    {
        $user = auth()->user();
        
        // Check if user owns this pet
        if ($pet->owner_id !== $user->id) {
            return ResponseHelper::error('Pet not found or you do not own this pet', 404);
        }

        $pet->update($request->validated());

        return ResponseHelper::success('Pet updated successfully', [
            'pet' => $pet->load('owner')
        ]);
    }

    /**
     * Remove the specified pet.
     * Users can only delete their own pets.
     */
    public function destroy(Pet $pet)
    {
        $user = auth()->user();
        
        // Check if user owns this pet
        if ($pet->owner_id !== $user->id) {
            return ResponseHelper::error('Pet not found or you do not own this pet', 404);
        }

        $pet->delete();

        return ResponseHelper::success('Pet deleted successfully');
    }
}
