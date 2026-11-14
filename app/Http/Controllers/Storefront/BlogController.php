<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Services\Blog\BlogRepository;
use Illuminate\Http\Request;

class BlogController extends Controller
{
    public function index(Request $request, BlogRepository $blog)
    {
        $articles = $blog->articles();
        $category = $request->query('category');

        if (filled($category)) {
            $articles = $articles->filter(fn ($a) => strtolower($a['category']) === strtolower((string) $category))->values();
        }

        return view('storefront.blog-index', [
            'articles' => $articles,
            'activeCategory' => $category,
            'categories' => $blog->articles()->pluck('category')->unique()->values()->all(),
        ]);
    }

    public function show(BlogRepository $blog, string $slug)
    {
        $article = $blog->find($slug) ?? abort(404);
        $related = $blog->articles()
            ->filter(fn ($a) => $a['slug'] !== $slug)
            ->take(3)
            ->values();

        return view('storefront.blog-show', [
            'article' => $article,
            'html' => $blog->html($article),
            'related' => $related,
        ]);
    }
}
