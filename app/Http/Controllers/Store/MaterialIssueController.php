<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\MaterialIssueRequest;
use App\Models\MaterialIssue;
use App\Models\MaterialRequest;
use App\Services\MaterialIssueService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class MaterialIssueController extends Controller
{
    public function __construct(private readonly MaterialIssueService $issues) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', MaterialIssue::class);

        $issues = MaterialIssue::query()
            ->with(['project', 'materialRequest', 'issuer', 'items.product.unit'])
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->string('q').'%';
                $query->where(function ($inner) use ($term) {
                    $inner->where('issue_no', 'like', $term)
                        ->orWhereHas('project', fn ($project) => $project->where('name', 'like', $term))
                        ->orWhereHas('materialRequest', fn ($materialRequest) => $materialRequest->where('request_no', 'like', $term))
                        ->orWhereHas('items.product', fn ($product) => $product->where('name', 'like', $term)->orWhere('sku', 'like', $term));
                });
            })
            ->latest('issue_date')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('store.material-issues.index', compact('issues'));
    }

    public function show(MaterialIssue $materialIssue): View
    {
        $this->authorize('view', $materialIssue);

        $materialIssue->load(['project', 'materialRequest', 'issuer', 'items.product.unit']);

        return view('store.material-issues.show', compact('materialIssue'));
    }

    public function store(MaterialIssueRequest $request, MaterialRequest $materialRequest): RedirectResponse
    {
        $this->authorize('issue', $materialRequest);

        try {
            $issue = $this->issues->issueFromRequest(
                $materialRequest,
                $request->validated('items'),
                $request->user()->id,
                $request->safe()->only('issue_date', 'notes'),
            );
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('store.material-issues.show', $issue)
            ->with('success', "Issue {$issue->issue_no} completed. Stock reduced and project expense recorded.");
    }
}
