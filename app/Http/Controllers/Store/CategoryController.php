<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\CategoryRequest;
use App\Models\Category;
use App\Traits\LogsActivity;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CategoryController extends Controller
{
    use LogsActivity;

    public function index(): View
    {
        $this->authorize('viewAny', Category::class);

        $categories = Category::query()
            ->withCount('products')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(20);

        return view('store.categories.index', compact('categories'));
    }

    public function create(): View
    {
        $this->authorize('create', Category::class);

        return view('store.categories.create');
    }

    public function store(CategoryRequest $request): RedirectResponse
    {
        $category = Category::query()->create([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active', true),
        ]);

        $this->logActivity('created', 'Category', "Created category {$category->name}", $category);

        return redirect()->route('store.categories.index')->with('success', 'Category created.');
    }

    public function edit(Category $category): View
    {
        $this->authorize('update', $category);

        return view('store.categories.edit', compact('category'));
    }

    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        $this->authorize('update', $category);

        $category->update([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active'),
        ]);

        $this->logActivity('updated', 'Category', "Updated category {$category->name}", $category);

        return redirect()->route('store.categories.index')->with('success', 'Category updated.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        $this->authorize('delete', $category);

        if ($category->products()->exists()) {
            return back()->with('error', 'This category has products and cannot be deleted.');
        }

        $name = $category->name;
        $category->delete();

        $this->logActivity('deleted', 'Category', "Deleted category {$name}");

        return redirect()->route('store.categories.index')->with('success', 'Category deleted.');
    }
}
