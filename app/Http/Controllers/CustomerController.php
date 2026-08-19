<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        $customers = Customer::query()
            ->when($request->string('search')->toString(), function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('legal_name', 'like', "%{$search}%")
                        ->orWhere('rfc', 'like', "%{$search}%");
                });
            })
            ->orderBy('legal_name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('customers/index', [
            'customers' => $customers,
            'filters' => $request->only('search'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('customers/create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        Customer::create($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Cliente creado.']);

        return to_route('customers.index');
    }

    public function edit(Customer $customer): Response
    {
        return Inertia::render('customers/edit', ['customer' => $customer]);
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $data = $this->validated($request, $customer->id);

        $customer->update($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Cliente actualizado.']);

        return to_route('customers.index');
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        $customer->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Cliente eliminado.']);

        return to_route('customers.index');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'legal_name' => ['required', 'string', 'max:255'],
            'rfc' => ['required', 'string', 'max:13', 'unique:customers,rfc,'.($ignoreId ?? 'NULL').',id'],
            'tax_system' => ['required', 'string', 'max:3'],
            'email' => ['nullable', 'email', 'max:255'],
            'zip' => ['required', 'string', 'max:5'],
        ]);
    }
}
