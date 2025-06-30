<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;


class CustomerController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Customer::query()->with('addresses');

        $query->when($request->filled('q'), function ($q) use ($request) {
            $search = $request->input('q');
            $q->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('company_name', 'like', "%{$search}%")
                ->orWhere('tax_id', 'like', "%{$search}%");
        });

        return CustomerResource::collection($query->paginate($request->input('per_page', 15)));
    }

    public function store(StoreCustomerRequest $request): CustomerResource
    {
        $customer = Customer::create($request->validated());
        return new CustomerResource($customer);
    }

    public function show(Customer $customer): CustomerResource
    {
        return new CustomerResource($customer->load('addresses'));
    }

    public function update(Request $request, Customer $customer): CustomerResource
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:customers,email,' . $customer->id,
            'phone' => 'nullable|string|max:30',
            'company_name' => 'nullable|string|max:255',
            'tax_id' => 'nullable|string|max:20',
        ]);

        $customer->update($validatedData);
        return new CustomerResource($customer);
    }

    public function destroy(Customer $customer): Response
    {
        $customer->delete();
        return response()->noContent();
    }
    public function getTree(): JsonResponse
    {
        $categories = Category::whereNull('parent_id')->with('childrenRecursive')->get();

        return response()->json(CategoryResource::collection($categories));
    }
}
