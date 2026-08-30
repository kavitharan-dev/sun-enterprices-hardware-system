<?php

namespace App\Http\Controllers\Store;

use App\Enums\StoreAssetType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Store\StoreAssetIssueRequest;
use App\Http\Requests\Store\StoreAssetRequest;
use App\Models\Project;
use App\Models\StoreAsset;
use App\Models\StoreAssetAssignment;
use App\Models\Worker;
use App\Traits\LogsActivity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class StoreAssetController extends Controller
{
    use LogsActivity;

    public function index(Request $request): View
    {
        $this->authorize('viewAny', StoreAsset::class);

        $type = $request->string('type')->toString();
        $query = StoreAsset::query()
            ->with(['activeAssignment.worker', 'activeAssignment.project'])
            ->orderBy('name');

        if ($type === StoreAssetType::Tool->value) {
            $query->tools();
        } elseif ($type === StoreAssetType::Vehicle->value) {
            $query->vehicles();
        }

        if ($request->filled('q')) {
            $term = '%'.$request->string('q').'%';
            $query->where(function ($inner) use ($term) {
                $inner->where('name', 'like', $term)
                    ->orWhere('identifier', 'like', $term)
                    ->orWhere('vehicle_kind', 'like', $term);
            });
        }

        if ($request->string('status')->toString() === 'out') {
            $query->whereHas('activeAssignment');
        } elseif ($request->string('status')->toString() === 'available') {
            $query->whereDoesntHave('activeAssignment')->active();
        }

        $assets = $query->paginate(20)->withQueryString();

        $outNow = StoreAssetAssignment::query()
            ->with(['asset', 'worker', 'project', 'issuer'])
            ->whereNull('returned_at')
            ->latest('issued_at')
            ->limit(10)
            ->get();

        return view('store.assets.index', compact('assets', 'outNow', 'type'));
    }

    public function create(Request $request): View
    {
        $this->authorize('create', StoreAsset::class);

        return view('store.assets.create', [
            'defaultType' => $request->string('type')->toString(),
        ]);
    }

    public function store(StoreAssetRequest $request): RedirectResponse
    {
        $asset = StoreAsset::query()->create([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active', true),
        ]);

        $this->logActivity('created', 'StoreAsset', "Registered {$asset->type->label()} {$asset->name}", $asset);

        return redirect()->route('store.assets.index', ['type' => $asset->type->value])
            ->with('success', ucfirst($asset->type->value).' registered.');
    }

    public function show(StoreAsset $asset): View
    {
        $this->authorize('view', $asset);

        $asset->load([
            'assignments.worker',
            'assignments.project',
            'assignments.issuer',
            'assignments.returner',
            'activeAssignment.worker',
            'activeAssignment.project',
        ]);

        return view('store.assets.show', [
            'asset' => $asset,
            'workers' => Worker::query()->orderBy('name')->get(),
            'projects' => Project::query()->orderBy('name')->get(),
        ]);
    }

    public function edit(StoreAsset $asset): View
    {
        $this->authorize('update', $asset);

        return view('store.assets.edit', compact('asset'));
    }

    public function update(StoreAssetRequest $request, StoreAsset $asset): RedirectResponse
    {
        $asset->update([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active'),
        ]);

        $this->logActivity('updated', 'StoreAsset', "Updated {$asset->type->label()} {$asset->name}", $asset);

        return redirect()->route('store.assets.show', $asset)->with('success', 'Asset updated.');
    }

    public function destroy(StoreAsset $asset): RedirectResponse
    {
        $this->authorize('delete', $asset);

        if ($asset->activeAssignment()->exists()) {
            return back()->with('error', 'Return this asset before deleting it.');
        }

        $name = $asset->name;
        $type = $asset->type->value;
        $asset->delete();

        $this->logActivity('deleted', 'StoreAsset', "Deleted {$type} {$name}");

        return redirect()->route('store.assets.index', ['type' => $type])
            ->with('success', 'Asset deleted.');
    }

    public function issue(StoreAssetIssueRequest $request, StoreAsset $asset): RedirectResponse
    {
        $this->authorize('issue', $asset);

        if (! $asset->is_active) {
            return back()->with('error', 'Inactive assets cannot be issued.');
        }

        if ($asset->activeAssignment()->exists()) {
            return back()->with('error', 'This asset is already out. Return it first.');
        }

        $assignment = $asset->assignments()->create([
            ...$request->validated(),
            'issued_by' => $request->user()->id,
        ]);

        $this->logActivity(
            'issued',
            'StoreAsset',
            "Issued {$asset->name} to {$assignment->worker->name}",
            $assignment,
        );

        return back()->with('success', 'Asset issued.');
    }

    public function returnAsset(StoreAssetAssignment $assignment): RedirectResponse
    {
        $this->authorize('return', $assignment);

        if (! $assignment->isOpen()) {
            throw new RuntimeException('This assignment is already closed.');
        }

        $assignment->update([
            'returned_at' => now(),
            'returned_by' => auth()->id(),
        ]);

        $assignment->load('asset', 'worker');

        $this->logActivity(
            'returned',
            'StoreAsset',
            "Returned {$assignment->asset->name} from {$assignment->worker->name}",
            $assignment,
        );

        return back()->with('success', 'Asset returned.');
    }
}
