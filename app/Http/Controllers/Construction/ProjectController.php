<?php

namespace App\Http\Controllers\Construction;

use App\Enums\ExpenseCategory;
use App\Enums\PaymentMethod;
use App\Enums\ProjectStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Construction\AssignWorkerRequest;
use App\Http\Requests\Construction\ProjectRequest;
use App\Models\Customer;
use App\Models\MaterialIssue;
use App\Models\MaterialIssueItem;
use App\Models\Project;
use App\Models\User;
use App\Models\Worker;
use App\Services\DocumentNumberService;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProjectController extends Controller
{
    use LogsActivity;

    public function __construct(private readonly DocumentNumberService $documentNumbers) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Project::class);

        $projects = Project::query()
            ->visibleTo($request->user())
            ->with(['customer', 'siteManager'])
            ->withCount('materialRequests')
            ->withSum('ownerPayments as received_total', 'amount')
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->string('q').'%';
                $query->where(function ($inner) use ($term) {
                    $inner->where('name', 'like', $term)
                        ->orWhere('project_code', 'like', $term)
                        ->orWhere('location', 'like', $term);
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('construction.projects.index', compact('projects'));
    }

    public function create(): View
    {
        $this->authorize('create', Project::class);

        return view('construction.projects.create', $this->formData());
    }

    public function store(ProjectRequest $request): RedirectResponse
    {
        $project = Project::query()->create([
            ...$request->safe()->except('site_manager_id'),
            'project_code' => $this->documentNumbers->next('project_prefix', 'PRJ', Project::class, 'project_code'),
            'site_manager_id' => $request->input('site_manager_id') ?: null,
            'progress_percentage' => 0,
            'created_by' => $request->user()->id,
        ]);

        $this->logActivity('created', 'Project', "Created project {$project->project_code}", $project);

        return redirect()->route('construction.projects.show', $project)->with('success', 'Project created.');
    }

    public function show(Project $project): View
    {
        $this->authorize('view', $project);

        $project->load([
            'customer',
            'siteManager',
            'workers',
            'expenses' => fn ($query) => $query
                ->latest('expense_date')
                ->latest('id')
                ->limit(10)
                ->with(['financialTransaction', 'reference' => function (MorphTo $morphTo) {
                    $morphTo->morphWith([
                        MaterialIssue::class => ['items.product.unit'],
                    ]);
                }]),
            'ownerPayments' => fn ($query) => $query->latest('payment_date')->latest('id')->with(['receiver', 'financialTransaction']),
            'dailyProgress' => fn ($query) => $query->latest('progress_date')->limit(10),
            'materialRequests' => fn ($query) => $query->with('items.product.unit')->latest()->limit(10),
        ]);

        $availableWorkers = Worker::query()->active()->orderBy('name')->get();
        $spent = $project->totalSpent();
        $received = $project->totalReceived();
        $stillToReceive = $project->remainingToReceive();
        $cashBalance = $project->cashBalance();
        $manualCategories = ExpenseCategory::manualCases();
        $paymentMethods = array_values(array_filter(
            PaymentMethod::cases(),
            fn (PaymentMethod $method) => $method !== PaymentMethod::Credit,
        ));

        return view('construction.projects.show', compact(
            'project',
            'availableWorkers',
            'spent',
            'received',
            'stillToReceive',
            'cashBalance',
            'manualCategories',
            'paymentMethods',
        ));
    }

    public function dashboard(Project $project): View
    {
        $this->authorize('view', $project);

        $project->load(['customer', 'siteManager']);

        $spent = $project->totalSpent();
        $received = $project->totalReceived();
        $stillToReceive = $project->remainingToReceive();
        $cashBalance = $project->cashBalance();
        $remaining = $project->remainingBudget();
        $usedPercent = $project->budgetUsedPercent();

        $spendByCategory = $project->expenses()
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->get()
            ->mapWithKeys(function ($row) {
                $category = $row->category instanceof ExpenseCategory
                    ? $row->category
                    : ExpenseCategory::from($row->category);

                return [$category->label() => (float) $row->total];
            });

        $materialsReceived = MaterialIssueItem::query()
            ->selectRaw('product_id, SUM(quantity) as quantity, SUM(subtotal) as cost')
            ->whereHas('materialIssue', fn ($query) => $query->where('project_id', $project->id))
            ->groupBy('product_id')
            ->with('product.unit')
            ->get();

        $recentProgress = $project->dailyProgress()
            ->with('recorder')
            ->latest('progress_date')
            ->limit(12)
            ->get();

        $recentIssues = $project->materialIssues()
            ->with('items.product')
            ->latest('issue_date')
            ->limit(8)
            ->get();

        return view('construction.projects.dashboard', compact(
            'project',
            'spent',
            'received',
            'stillToReceive',
            'cashBalance',
            'remaining',
            'usedPercent',
            'spendByCategory',
            'materialsReceived',
            'recentProgress',
            'recentIssues',
        ));
    }

    public function edit(Project $project): View
    {
        $this->authorize('update', $project);

        return view('construction.projects.edit', [...$this->formData(), 'project' => $project]);
    }

    public function update(ProjectRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $project->update([
            ...$request->safe()->except('site_manager_id'),
            'site_manager_id' => $request->input('site_manager_id') ?: null,
        ]);

        $this->logActivity('updated', 'Project', "Updated project {$project->project_code}", $project);

        return redirect()->route('construction.projects.show', $project)->with('success', 'Project updated.');
    }

    public function destroy(Project $project): RedirectResponse
    {
        $this->authorize('delete', $project);

        if ($project->materialRequests()->exists()) {
            return back()->with('error', 'This project has material requests and cannot be deleted.');
        }

        $code = $project->project_code;
        $project->delete();
        $this->logActivity('deleted', 'Project', "Deleted project {$code}");

        return redirect()->route('construction.projects.index')->with('success', 'Project deleted.');
    }

    public function assignWorker(AssignWorkerRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('assignWorker', $project);

        $exists = $project->workers()
            ->where('worker_id', $request->integer('worker_id'))
            ->wherePivot('assigned_from', $request->input('assigned_from'))
            ->exists();

        if ($exists) {
            return back()->with('error', 'This worker is already assigned from that date.');
        }

        $project->workers()->attach($request->integer('worker_id'), [
            'role_on_site' => $request->input('role_on_site'),
            'assigned_from' => $request->input('assigned_from'),
            'assigned_to' => $request->input('assigned_to') ?: null,
        ]);

        $this->logActivity('assigned', 'Project', "Assigned a worker to {$project->project_code}", $project);

        return back()->with('success', 'Worker assigned to this project.');
    }

    public function unassignWorker(Project $project, int $pivot): RedirectResponse
    {
        $this->authorize('assignWorker', $project);

        DB::table('project_worker')
            ->where('project_id', $project->id)
            ->where('id', $pivot)
            ->delete();

        return back()->with('success', 'Worker removed from this project.');
    }

    private function formData(): array
    {
        return [
            'customers' => Customer::query()->active()->orderBy('name')->get(),
            'siteManagers' => User::query()->role('site_manager')->where('is_active', true)->orderBy('name')->get(),
            'statuses' => ProjectStatus::cases(),
        ];
    }
}
