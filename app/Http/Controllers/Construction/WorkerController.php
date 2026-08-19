<?php

namespace App\Http\Controllers\Construction;

use App\Enums\WorkerStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Construction\WorkerRequest;
use App\Models\Worker;
use App\Services\DocumentNumberService;
use App\Traits\LogsActivity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkerController extends Controller
{
    use LogsActivity;

    public function __construct(private readonly DocumentNumberService $documentNumbers) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Worker::class);

        $workers = Worker::query()
            ->withCount('projects')
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->string('q').'%';
                $query->where(function ($inner) use ($term) {
                    $inner->where('name', 'like', $term)
                        ->orWhere('worker_code', 'like', $term)
                        ->orWhere('phone', 'like', $term)
                        ->orWhere('nic', 'like', $term);
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('construction.workers.index', compact('workers'));
    }

    public function create(): View
    {
        $this->authorize('create', Worker::class);

        return view('construction.workers.create', ['statuses' => WorkerStatus::cases()]);
    }

    public function store(WorkerRequest $request): RedirectResponse
    {
        $worker = Worker::query()->create([
            ...$request->validated(),
            'worker_code' => $this->documentNumbers->next('worker_prefix', 'WRK', Worker::class, 'worker_code'),
            'daily_rate' => $request->input('daily_rate', 0),
        ]);

        $this->logActivity('created', 'Worker', "Created worker {$worker->name}", $worker);

        return redirect()->route('construction.workers.index')->with('success', 'Worker created.');
    }

    public function show(Worker $worker): View
    {
        $this->authorize('view', $worker);

        $worker->load('projects');

        return view('construction.workers.show', compact('worker'));
    }

    public function edit(Worker $worker): View
    {
        $this->authorize('update', $worker);

        return view('construction.workers.edit', [
            'worker' => $worker,
            'statuses' => WorkerStatus::cases(),
        ]);
    }

    public function update(WorkerRequest $request, Worker $worker): RedirectResponse
    {
        $this->authorize('update', $worker);

        $worker->update([
            ...$request->validated(),
            'daily_rate' => $request->input('daily_rate', 0),
        ]);

        $this->logActivity('updated', 'Worker', "Updated worker {$worker->name}", $worker);

        return redirect()->route('construction.workers.index')->with('success', 'Worker updated.');
    }

    public function destroy(Worker $worker): RedirectResponse
    {
        $this->authorize('delete', $worker);

        if ($worker->projects()->exists()) {
            return back()->with('error', 'This worker is assigned to a project. Set them inactive instead.');
        }

        $name = $worker->name;
        $worker->delete();
        $this->logActivity('deleted', 'Worker', "Deleted worker {$name}");

        return redirect()->route('construction.workers.index')->with('success', 'Worker deleted.');
    }
}
