<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Services\Docs\DocsRepository;
use Illuminate\Http\Request;

class DocsController extends Controller
{
    public function index(Request $request, DocsRepository $docs, string $audience = 'client')
    {
        $query = trim((string) $request->query('q', ''));

        return view('storefront.docs-index', [
            'audience' => $audience,
            'articles' => $docs->articles($audience),
            'query' => mb_substr($query, 0, 100),
            'results' => $query !== '' ? $docs->search($audience, mb_substr($query, 0, 100)) : null,
        ]);
    }

    public function show(DocsRepository $docs, string $slug, string $audience = 'client')
    {
        $article = $docs->find($audience, $slug) ?? abort(404);

        return view('storefront.docs-show', [
            'audience' => $audience,
            'article' => $article,
            'html' => $docs->html($article),
            'articles' => $docs->articles($audience),
        ]);
    }
}
