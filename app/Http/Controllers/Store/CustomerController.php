<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\CustomerRequest;
use App\Models\Customer;
use App\Traits\LogsActivity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    use LogsActivity;

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Customer::class);

        $customers = Customer::query()
            ->withCount('sales')
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->string('q').'%';
                $query->where(function ($inner) use ($term) {
                    $inner->where('name', 'like', $term)
                        ->orWhere('phone', 'like', $term)
                        ->orWhere('nic', 'like', $term);
                });
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('store.customers.index', compact('customers'));
    }

    public function create(): View
    {
        $this->authorize('create', Customer::class);

        return view('store.customers.create');
    }

    public function store(CustomerRequest $request): RedirectResponse
    {
        $customer = Customer::query()->create([
            ...$request->safe()->except('is_active'),
            'credit_limit' => $request->input('credit_limit', 0),
            'is_active' => $request->boolean('is_active', true),
        ]);

        $this->logActivity('created', 'Customer', "Created customer {$customer->name}", $customer);

        return redirect()->route('store.customers.index')->with('success', 'Customer created.');
    }

    public function show(Customer $customer): View
    {
        $this->authorize('view', $customer);

        $customer->load(['sales' => fn ($query) => $query->latest()->limit(15)]);

        return view('store.customers.show', compact('customer'));
    }

    public function edit(Customer $customer): View
    {
        $this->authorize('update', $customer);

        return view('store.customers.edit', compact('customer'));
    }

    public function update(CustomerRequest $request, Customer $customer): RedirectResponse
    {
        $this->authorize('update', $customer);

        $customer->update([
            ...$request->safe()->except('is_active'),
            'credit_limit' => $request->input('credit_limit', 0),
            'is_active' => $request->boolean('is_active'),
        ]);

        $this->logActivity('updated', 'Customer', "Updated customer {$customer->name}", $customer);

        return redirect()->route('store.customers.index')->with('success', 'Customer updated.');
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        $this->authorize('delete', $customer);

        if ($customer->sales()->exists()) {
            return back()->with('error', 'This customer has sales and cannot be deleted. Deactivate instead.');
        }

        $name = $customer->name;
        $customer->delete();
        $this->logActivity('deleted', 'Customer', "Deleted customer {$name}");

        return redirect()->route('store.customers.index')->with('success', 'Customer deleted.');
    }
}
