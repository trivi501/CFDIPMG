<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(Request $request): Response
    {
        $products = Product::query()
            ->when($request->string('search')->toString(), function ($query, $search) {
                $query->where('description', 'like', "%{$search}%");
            })
            ->orderBy('description')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('products/index', [
            'products' => $products,
            'filters' => $request->only('search'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('products/create');
    }

    public function store(Request $request): RedirectResponse
    {
        Product::create($this->validated($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Producto creado.']);

        return to_route('products.index');
    }

    public function edit(Product $product): Response
    {
        return Inertia::render('products/edit', ['product' => $product]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $product->update($this->validated($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Producto actualizado.']);

        return to_route('products.index');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Producto eliminado.']);

        return to_route('products.index');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request): array
    {
        return $request->validate([
            'description' => ['required', 'string', 'max:255'],
            'sat_product_key' => ['required', 'string', 'max:8'],
            'sat_unit_key' => ['required', 'string', 'max:3'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}
