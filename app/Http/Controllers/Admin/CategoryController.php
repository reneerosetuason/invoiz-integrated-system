<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index(\Illuminate\Http\Request $request)
    {
        $query = Category::with(['children', 'parent'])->withCount('products');
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")->orWhere('slug', 'like', "%{$s}%");
            });
        }
        $categories = $query->orderBy('name')->get();
        $archivedQuery = Category::onlyTrashed()->with(['parent'])->withCount('products');
        if ($request->filled('search')) {
            $s = $request->search;
            $archivedQuery->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")->orWhere('slug', 'like', "%{$s}%");
            });
        }
        $archived = $archivedQuery->orderBy('name')->get();
        $stats = [
            'all' => Category::count() + Category::onlyTrashed()->count(),
            'active' => Category::count(),
            'archived' => Category::onlyTrashed()->count(),
            'with_products' => Category::has('products')->count(),
        ];
        return view('admin.categories', compact('categories', 'archived', 'stats'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'parent_id' => 'nullable|exists:categories,id',
        ]);

        $slug = $this->uniqueSlug($request->name);

        Category::create([
            'name' => $request->name,
            'slug' => $slug,
            'description' => $request->description,
            'parent_id' => $request->parent_id,
            'active' => true,
        ]);

        return redirect()->back()->with('status', 'Category created successfully.');
    }

    public function update(Request $request, Category $category)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'active' => 'boolean',
        ]);

        $category->update([
            'name' => $request->name,
            'slug' => $this->uniqueSlug($request->name, $category->id),
            'description' => $request->description,
            'active' => $request->boolean('active', true),
        ]);

        return redirect()->back()->with('status', 'Category updated successfully.');
    }

    public function archive(Category $category)
    {
        $category->delete();
        return redirect()->back()->with('status', "Category \"{$category->name}\" archived. It is now hidden and can be restored anytime.");
    }

    public function restore($id)
    {
        $category = Category::onlyTrashed()->findOrFail($id);
        $category->restore();
        return redirect()->back()->with('status', "Category \"{$category->name}\" restored.");
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'category';
        $slug = $base;
        $i = 2;
        while (Category::withTrashed()->where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }
}
