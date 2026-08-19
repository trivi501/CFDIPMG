<?php

namespace App\Http\Controllers;

use App\Models\ApiClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ApiClientController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('api-clients/index', [
            'apiClients' => ApiClient::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $result = ApiClient::generate($data['name']);

        Inertia::flash('newApiKey', $result['plainTextKey']);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'API key generada. Cópiala ahora, no volverá a mostrarse.']);

        return to_route('api-clients.index');
    }

    public function update(Request $request, ApiClient $apiClient): RedirectResponse
    {
        $data = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $apiClient->update($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'API client actualizado.']);

        return to_route('api-clients.index');
    }

    public function destroy(ApiClient $apiClient): RedirectResponse
    {
        $apiClient->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'API client eliminado.']);

        return to_route('api-clients.index');
    }
}
