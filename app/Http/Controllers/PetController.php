<?php

namespace App\Http\Controllers;

use App\Core\Helpers\ResponseHelper;
use App\Http\Requests\StorePetRequest;
use App\Http\Requests\UpdatePetRequest;
use App\Models\Pet;

class PetController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): \Illuminate\Http\JsonResponse
    {
        return ResponseHelper::success(data: [
            'pets' => Pet::with('owner')->get()
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePetRequest $request)
    {
        $pet = Pet::create(array_merge($request->validated(), [
            'owner_id' => auth()->user()->id
        ]));

        return ResponseHelper::success(data: [
            'message' => 'Pet created successfully',
            'pet' => $pet->load('owner')
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Pet $pet)
    {
        // TODO: Implement medical records and other relations too.
        return ResponseHelper::success(data: [
            'pet' => $pet->load('owner')
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePetRequest $request, Pet $pet)
    {
        $pet->update($request->validated());

        return ResponseHelper::success(data: [
            'message' => 'Pet updated successfully',
            'pet' => $pet->load('owner')
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Pet $pet)
    {
        $pet->delete();

        return ResponseHelper::success(data: [
            'message' => 'Pet deleted successfully'
        ]);
    }
}
