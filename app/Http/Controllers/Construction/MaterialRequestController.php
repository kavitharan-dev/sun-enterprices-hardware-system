<?php

namespace App\Http\Controllers\Construction;

use App\Http\Controllers\Controller;
use App\Http\Requests\Construction\MaterialRequestFormRequest;
use App\Models\MaterialRequest;
use App\Models\Product;
use App\Models\Project;
use App\Services\MaterialRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class MaterialRequestController extends Controller
{
    public function __construct(private readonly MaterialRequestService $requests) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', MaterialRequest::class);

        $requests = MaterialRequest::query()
            ->visibleTo($request->user())
            ->with(['project', 'requester', 'items.product.unit'])
            ->withCount('items')
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->string('q').'%';
                $query->where(function ($inner) use ($term) {
                    $inner->where('request_no', 'like', $term)
                        ->orWhereHas('project', fn ($project) => $project->where('name', 'like', $term))
                        ->orWhereHas('items.product', fn ($product) => $product->where('name', 'like', $term)->orWhere('sku', 'like', $term));
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest('request_date')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('construction.material-requests.index', compact('requests'));
    }

    public function create(): View
    {
        $this->authorize('create', MaterialRequest::class);

        return view('construction.material-requests.create', $this->formData());
    }

    public function store(MaterialRequestFormRequest $request): RedirectResponse
    {
        try {
            $materialRequest = $this->requests->create(
                $request->safe()->except('items'),
                $request->validated('items'),
                $request->user()->id,
            );
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        if ($request->boolean('submit')) {
            try {
                $this->requests->submit($materialRequest);
            } catch (RuntimeException $e) {
                return redirect()->route('construction.material-requests.show', $materialRequest)
                    ->with('error', $e->getMessage());
            }

            return redirect()->route('construction.material-requests.show', $materialRequest)
                ->with('success', "Request {$materialRequest->request_no} submitted for store approval.");
        }

        return redirect()->route('construction.material-requests.show', $materialRequest)
            ->with('success', "Draft {$materialRequest->request_no} saved.");
    }

    public function show(MaterialRequest $materialRequest): View
    {
        $this->authorize('view', $materialRequest);

        $materialRequest->load(['project.customer', 'requester', 'reviewer', 'items.product.unit', 'issues.issuer']);

        return view('construction.material-requests.show', compact('materialRequest'));
    }

    public function edit(MaterialRequest $materialRequest): View
    {
        $this->authorize('update', $materialRequest);

        $materialRequest->load('items');

        return view('construction.material-requests.edit', [...$this->formData(), 'materialRequest' => $materialRequest]);
    }

    public function update(MaterialRequestFormRequest $request, MaterialRequest $materialRequest): RedirectResponse
    {
        $this->authorize('update', $materialRequest);

        try {
            $this->requests->update(
                $materialRequest,
                $request->safe()->except('items'),
                $request->validated('items'),
                $request->user()->id,
            );
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('construction.material-requests.show', $materialRequest)
            ->with('success', 'Request updated.');
    }

    public function submit(MaterialRequest $materialRequest): RedirectResponse
    {
        $this->authorize('submit', $materialRequest);

        try {
            $this->requests->submit($materialRequest);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('construction.material-requests.show', $materialRequest)
            ->with('success', "Request {$materialRequest->request_no} submitted for store approval.");
    }

    public function destroy(MaterialRequest $materialRequest): RedirectResponse
    {
        $this->authorize('delete', $materialRequest);

        try {
            $this->requests->cancel($materialRequest);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('construction.material-requests.index')->with('success', 'Draft request cancelled.');
    }

    private function formData(): array
    {
        $user = auth()->user();

        return [
            'projects' => Project::query()
                ->visibleTo($user)
                ->orderBy('name')
                ->get(),
            'products' => Product::query()->active()->with('unit')->orderBy('name')->get(),
        ];
    }
}
