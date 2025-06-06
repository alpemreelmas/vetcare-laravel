<?php

namespace App\Http\Controllers;

use App\Core\Helpers\ResponseHelper;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    /**
     * Display a listing of services.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Service::query();

        // Apply filters
        if ($request->has('category')) {
            $query->byCategory($request->category);
        }

        if ($request->has('is_active')) {
            if ($request->boolean('is_active')) {
                $query->active();
            } else {
                $query->where('is_active', false);
            }
        }

        if ($request->has('is_emergency')) {
            if ($request->boolean('is_emergency')) {
                $query->emergency();
            }
        }

        if ($request->has('search')) {
            $query->search($request->search);
        }

        if ($request->has('min_price')) {
            $query->where('base_price', '>=', $request->min_price);
        }

        if ($request->has('max_price')) {
            $query->where('base_price', '<=', $request->max_price);
        }

        $perPage = $request->get('per_page', 15);
        $services = $query->orderBy('name')->paginate($perPage);

        return ResponseHelper::success('Services retrieved successfully', [
            'services' => $services->items(),
            'pagination' => [
                'current_page' => $services->currentPage(),
                'last_page' => $services->lastPage(),
                'per_page' => $services->perPage(),
                'total' => $services->total(),
            ]
        ]);
    }

    /**
     * Store a newly created service (Admin only).
     */
    public function store(Request $request): JsonResponse
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'required|in:consultation,diagnostic,treatment,surgery,vaccination,grooming,emergency,other',
            'base_price' => 'required|numeric|min:0',
            'min_price' => 'nullable|numeric|min:0',
            'max_price' => 'nullable|numeric|min:0',
            'is_variable_pricing' => 'nullable|boolean',
            'estimated_duration' => 'nullable|integer|min:1',
            'notes' => 'nullable|string',
            'required_equipment' => 'nullable|array',
            'is_active' => 'nullable|boolean',
            'requires_appointment' => 'nullable|boolean',
            'is_emergency_service' => 'nullable|boolean',
            'tags' => 'nullable|array',
        ]);

        // Validate price range if variable pricing
        if ($request->boolean('is_variable_pricing')) {
            if (!$request->has('min_price') || !$request->has('max_price')) {
                return ResponseHelper::error('Min and max prices are required for variable pricing', 422);
            }
            if ($request->min_price >= $request->max_price) {
                return ResponseHelper::error('Min price must be less than max price', 422);
            }
        }

        // Generate service code if not provided
        if (!$request->has('service_code')) {
            $validatedData['service_code'] = Service::generateServiceCode(
                $validatedData['category'],
                $validatedData['name']
            );
        }

        $service = Service::create($validatedData);

        return ResponseHelper::success('Service created successfully', [
            'service' => $service
        ], 201);
    }

    /**
     * Display the specified service.
     */
    public function show(Service $service): JsonResponse
    {
        return ResponseHelper::success('Service retrieved successfully', [
            'service' => $service
        ]);
    }

    /**
     * Update the specified service (Admin only).
     */
    public function update(Request $request, Service $service): JsonResponse
    {
        $validatedData = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'category' => 'sometimes|in:consultation,diagnostic,treatment,surgery,vaccination,grooming,emergency,other',
            'base_price' => 'sometimes|numeric|min:0',
            'min_price' => 'nullable|numeric|min:0',
            'max_price' => 'nullable|numeric|min:0',
            'is_variable_pricing' => 'nullable|boolean',
            'estimated_duration' => 'nullable|integer|min:1',
            'notes' => 'nullable|string',
            'required_equipment' => 'nullable|array',
            'is_active' => 'nullable|boolean',
            'requires_appointment' => 'nullable|boolean',
            'is_emergency_service' => 'nullable|boolean',
            'service_code' => 'sometimes|string|unique:services,service_code,' . $service->id,
            'tags' => 'nullable|array',
        ]);

        // Validate price range if variable pricing
        if ($request->has('is_variable_pricing') && $request->boolean('is_variable_pricing')) {
            $minPrice = $request->has('min_price') ? $request->min_price : $service->min_price;
            $maxPrice = $request->has('max_price') ? $request->max_price : $service->max_price;
            
            if (!$minPrice || !$maxPrice) {
                return ResponseHelper::error('Min and max prices are required for variable pricing', 422);
            }
            if ($minPrice >= $maxPrice) {
                return ResponseHelper::error('Min price must be less than max price', 422);
            }
        }

        $service->update($validatedData);

        return ResponseHelper::success('Service updated successfully', [
            'service' => $service
        ]);
    }

    /**
     * Remove the specified service (Admin only).
     */
    public function destroy(Service $service): JsonResponse
    {
        // Check if service has been used in invoices
        if ($service->invoiceItems()->exists()) {
            return ResponseHelper::error('Cannot delete service that has been used in invoices', 422);
        }

        $service->delete();

        return ResponseHelper::success('Service deleted successfully');
    }

    /**
     * Get services by category.
     */
    public function byCategory(string $category): JsonResponse
    {
        $services = Service::byCategory($category)->active()->get();

        return ResponseHelper::success('Services retrieved successfully', [
            'category' => $category,
            'services' => $services
        ]);
    }

    /**
     * Get emergency services.
     */
    public function emergency(): JsonResponse
    {
        $services = Service::emergency()->active()->get();

        return ResponseHelper::success('Emergency services retrieved successfully', [
            'services' => $services
        ]);
    }

    /**
     * Get service categories with counts.
     */
    public function categories(): JsonResponse
    {
        $categories = Service::selectRaw('category, COUNT(*) as count')
            ->active()
            ->groupBy('category')
            ->get();

        return ResponseHelper::success('Service categories retrieved successfully', [
            'categories' => $categories
        ]);
    }

    /**
     * Get service pricing for a specific service.
     */
    public function pricing(Service $service, Request $request): JsonResponse
    {
        $customPrice = $request->get('custom_price');
        $effectivePrice = $service->getEffectivePrice($customPrice);

        return ResponseHelper::success('Service pricing retrieved successfully', [
            'service' => $service->only(['id', 'name', 'base_price', 'min_price', 'max_price', 'is_variable_pricing']),
            'effective_price' => $effectivePrice,
            'price_range' => $service->price_range,
            'formatted_duration' => $service->formatted_duration,
        ]);
    }
}
