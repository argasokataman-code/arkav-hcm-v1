<?php

namespace App\Http\Controllers;

use App\Support\HcmKnowledgebase;
use Illuminate\Http\Request;
use Illuminate\View\View;

class KnowledgebaseController extends Controller
{
    public function index(Request $request): View
    {
        $q = $request->query('q');
        $query = is_string($q) ? $q : '';

        return view('knowledgebase', [
            'categories' => HcmKnowledgebase::filterForQuery($query !== '' ? $query : null),
            'query' => $query,
            'popularArticles' => HcmKnowledgebase::popularArticles(5),
            'latestArticles' => HcmKnowledgebase::latestArticles(5),
        ]);
    }

    public function category(string $slug): View
    {
        $category = HcmKnowledgebase::categoryBySlug($slug);
        if ($category === null) {
            abort(404);
        }

        return view('knowledgebase-view', [
            'category' => $category,
            'categories' => HcmKnowledgebase::categories(),
            'popularArticles' => HcmKnowledgebase::popularArticles(5),
            'latestArticles' => HcmKnowledgebase::latestArticles(5),
        ]);
    }

    public function article(string $slug): View
    {
        $resolved = HcmKnowledgebase::resolveArticle($slug);
        if ($resolved === null) {
            abort(404);
        }

        return view('knowledgebase-details', [
            'category' => $resolved['category'],
            'article' => $resolved['article'],
            'categories' => HcmKnowledgebase::categories(),
            'popularArticles' => HcmKnowledgebase::popularArticles(5),
            'latestArticles' => HcmKnowledgebase::latestArticles(5),
        ]);
    }
}
