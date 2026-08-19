<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\MaterialRequestReviewRequest;
use App\Models\MaterialRequest;
use App\Services\MaterialRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class MaterialRequestReviewController extends Controller
{
    public function __construct(private readonly MaterialRequestService $requests) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', MaterialRequest::class);

        $requests = MaterialRequest::query()
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
            ->when(
                $request->input('status', 'pending') === 'all',
                fn ($query) => $query,
                fn ($query) => $query->where('status', $request->input('status', 'pending')),
            )
            ->latest('request_date')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('store.material-requests.index', compact('requests'));
    }

    public function show(MaterialRequest $materialRequest): View
    {
        $this->authorize('view', $materialRequest);

        $materialRequest->load(['project.customer', 'requester', 'reviewer', 'items.product.unit', 'issues.issuer']);

        return view('store.material-requests.show', compact('materialRequest'));
    }

    public function approve(MaterialRequestReviewRequest $request, MaterialRequest $materialRequest): RedirectResponse
    {
        $this->authorize('review', $materialRequest);

        try {
            $this->requests->approve($materialRequest, $request->validated('items'), $request->user()->id);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('store.material-requests.show', $materialRequest)
            ->with('success', "Request {$materialRequest->request_no} approved. Issue materials to reduce stock.");
    }

    public function reject(Request $request, MaterialRequest $materialRequest): RedirectResponse
    {
        $this->authorize('review', $materialRequest);

        $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $this->requests->reject($materialRequest, $request->string('rejection_reason')->toString(), $request->user()->id);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('store.material-requests.index')
            ->with('success', "Request {$materialRequest->request_no} rejected.");
    }
}
