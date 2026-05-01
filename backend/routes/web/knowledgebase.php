<?php

use App\Http\Controllers\KnowledgebaseController;
use App\Support\HcmKnowledgebase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('knowledgebase', [KnowledgebaseController::class, 'index'])->name('knowledgebase');
Route::get('knowledgebase/category/{slug}', [KnowledgebaseController::class, 'category'])->name('knowledgebase.category');
Route::get('knowledgebase/article/{slug}', [KnowledgebaseController::class, 'article'])->name('knowledgebase.article');

Route::get('knowledgebase-view', function (Request $request) {
    $category = $request->query('category');
    if (is_string($category) && $category !== '' && HcmKnowledgebase::categoryBySlug($category, $request->user())) {
        return redirect()->route('knowledgebase.category', ['slug' => $category]);
    }

    return redirect()->route('knowledgebase');
})->name('knowledgebase-view');

Route::get('knowledgebase-details', function (Request $request) {
    $article = $request->query('article');
    if (is_string($article) && $article !== '' && HcmKnowledgebase::resolveArticle($article, $request->user())) {
        return redirect()->route('knowledgebase.article', ['slug' => $article]);
    }

    return redirect()->route('knowledgebase');
})->name('knowledgebase-details');
