<?php

namespace App\Http\Controllers\Admin;

use App\Core\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePetRequest;
use App\Http\Requests\Admin\UpdatePetRequest;
use App\Models\Pet;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PetController extends Controller
{
    /**
     * Display a listing of all pets (Admin only).
     * Admins can see all pets in the system.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Pet::with(['owner']);

        // Apply filters if provided
        if ($request->has('owner_id')) {
            $query->where('owner_id', $request->owner_id);
        }

        if ($request->has('species')) {
            $query->where('species', 'like', '%' . $request->species . '%');
        }

        if ($request->has('breed')) {
            $query->where('breed', 'like', '%' . $request->breed . '%');
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhere('species', 'like', '%' . $search . '%')
                  ->orWhere('breed', 'like', '%' . $search . '%');
            });
        }

        $perPage = $request->get('per_page', 15);
        $pets = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return ResponseHelper::success('All pets retrieved successfully', [
            'pets' => $pets->items(),
            'pagination' => [
                'current_page' => $pets->currentPage(),
                'last_page' => $pets->lastPage(),
                'per_page' => $pets->perPage(),
                'total' => $pets->total(),
            ],
            'filters_applied' => $request->only(['owner_id', 'species', 'breed', 'search'])
        ]);
    }

    /**
     * Store a newly created pet for any user (Admin only).
     */
    public function store(StorePetRequest $request): JsonResponse
    {
        $pet = Pet::create($request->validated());

        return ResponseHelper::success('Pet created successfully', [
            'pet' => $pet->load('owner')
        ], 201);
    }

    /**
     * Display the specified pet (Admin only).
     * Admins can view any pet.
     */
    public function show(Pet $pet): JsonResponse
    {
        return ResponseHelper::success('Pet retrieved successfully', [
            'pet' => $pet->load(['owner', 'appointments.doctor.user'])
        ]);
    }

    /**
     * Update the specified pet (Admin only).
     * Admins can update any pet.
     */
    public function update(UpdatePetRequest $request, Pet $pet): JsonResponse
    {
        $pet->update($request->validated());

        return ResponseHelper::success('Pet updated successfully', [
            'pet' => $pet->load('owner')
        ]);
    }

    /**
     * Remove the specified pet (Admin only).
     * Admins can delete any pet.
     */
    public function destroy(Pet $pet): JsonResponse
    {
        // Check if pet has any appointments
        if ($pet->appointments()->exists()) {
            return ResponseHelper::error(
                'Cannot delete pet with existing appointments. Please cancel or complete all appointments first.',
                422
            );
        }

        $pet->delete();

        return ResponseHelper::success('Pet deleted successfully');
    }

    /**
     * Get pets by owner (Admin only).
     */
    public function getByOwner(User $owner): JsonResponse
    {
        $pets = Pet::where('owner_id', $owner->id)
            ->with('owner')
            ->orderBy('name')
            ->get();

        return ResponseHelper::success('Owner pets retrieved successfully', [
            'owner' => [
                'id' => $owner->id,
                'name' => $owner->name,
                'email' => $owner->email,
            ],
            'pets' => $pets,
            'total' => $pets->count()
        ]);
    }

    /**
     * Get pet statistics (Admin only).
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'total_pets' => Pet::count(),
            'pets_by_species' => Pet::selectRaw('species, COUNT(*) as count')
                ->groupBy('species')
                ->orderBy('count', 'desc')
                ->get(),
            'recent_registrations' => Pet::where('created_at', '>=', now()->subDays(30))->count(),
            'pets_with_appointments' => Pet::whereHas('appointments')->count(),
        ];

        return ResponseHelper::success('Pet statistics retrieved successfully', $stats);
    }
} 