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
        $user = $request->user();

        return view('knowledgebase', [
            'categories' => HcmKnowledgebase::filterForQuery($query !== '' ? $query : null, $user),
            'query' => $query,
            'guidedTutorials' => HcmKnowledgebase::guidedTutorials($user, 6),
            'popularArticles' => HcmKnowledgebase::popularArticles(5),
            'latestArticles' => HcmKnowledgebase::latestArticles(5),
        ]);
    }

    public function category(string $slug): View
    {
        $user = request()->user();
        $category = HcmKnowledgebase::categoryBySlug($slug, $user);
        if ($category === null) {
            abort(404);
        }

        return view('knowledgebase-view', [
            'category' => $category,
            'categories' => HcmKnowledgebase::categoriesForUser($user),
            'popularArticles' => HcmKnowledgebase::popularArticles(5),
            'latestArticles' => HcmKnowledgebase::latestArticles(5),
        ]);
    }

    public function article(string $slug): View
    {
        $user = request()->user();
        $resolved = HcmKnowledgebase::resolveArticle($slug, $user);
        if ($resolved === null) {
            abort(404);
        }

        return view('knowledgebase-details', [
            'category' => $resolved['category'],
            'article' => $resolved['article'],
            'categories' => HcmKnowledgebase::categoriesForUser($user),
            'popularArticles' => HcmKnowledgebase::popularArticles(5),
            'latestArticles' => HcmKnowledgebase::latestArticles(5),
        ]);
    }
}
