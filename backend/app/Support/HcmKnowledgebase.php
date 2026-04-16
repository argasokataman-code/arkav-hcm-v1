<?php

namespace App\Support;

/**
 * Static in-repo help center for /knowledgebase (config/hcm_knowledgebase.php).
 */
final class HcmKnowledgebase
{
    /**
     * @return list<array{slug: string, title: string, icon: string, articles: list<array{slug: string, title: string, excerpt: string, body_html: string}>}>
     */
    public static function categories(): array
    {
        $raw = config('hcm_knowledgebase.categories', []);

        return is_array($raw) ? array_values($raw) : [];
    }

    /**
     * @return array{slug: string, title: string, icon: string, articles: list<array<string, mixed>>}|null
     */
    public static function categoryBySlug(string $slug): ?array
    {
        $slug = trim($slug);
        if ($slug === '') {
            return null;
        }

        foreach (self::categories() as $cat) {
            if (($cat['slug'] ?? '') === $slug) {
                return $cat;
            }
        }

        return null;
    }

    /**
     * @return array{category: array<string, mixed>, article: array<string, mixed>}|null
     */
    public static function resolveArticle(string $articleSlug): ?array
    {
        $articleSlug = trim($articleSlug);
        if ($articleSlug === '') {
            return null;
        }

        foreach (self::categories() as $cat) {
            foreach ($cat['articles'] ?? [] as $article) {
                if (($article['slug'] ?? '') === $articleSlug) {
                    return ['category' => $cat, 'article' => $article];
                }
            }
        }

        return null;
    }

    /**
     * Filter categories; when $query is empty, returns all categories.
     *
     * @return list<array{slug: string, title: string, icon: string, articles: list<array<string, mixed>>}>
     */
    public static function filterForQuery(?string $query): array
    {
        $q = strtolower(trim((string) $query));
        if ($q === '') {
            return self::categories();
        }

        $out = [];
        foreach (self::categories() as $cat) {
            $catTitle = strtolower((string) ($cat['title'] ?? ''));
            $matchedArticles = [];
            foreach ($cat['articles'] ?? [] as $article) {
                $hay = strtolower(
                    ($article['title'] ?? '')
                    .' '.($article['excerpt'] ?? '')
                );
                if (str_contains($hay, $q) || str_contains($catTitle, $q)) {
                    $matchedArticles[] = $article;
                }
            }
            if ($matchedArticles !== []) {
                $copy = $cat;
                $copy['articles'] = $matchedArticles;
                $out[] = $copy;
            }
        }

        return $out;
    }

    /**
     * @return list<array{slug: string, title: string, excerpt: string, category_slug: string, category_title: string}>
     */
    public static function allArticlesFlat(): array
    {
        $flat = [];
        foreach (self::categories() as $cat) {
            foreach ($cat['articles'] ?? [] as $article) {
                $flat[] = [
                    'slug' => (string) ($article['slug'] ?? ''),
                    'title' => (string) ($article['title'] ?? ''),
                    'excerpt' => (string) ($article['excerpt'] ?? ''),
                    'category_slug' => (string) ($cat['slug'] ?? ''),
                    'category_title' => (string) ($cat['title'] ?? ''),
                ];
            }
        }

        return $flat;
    }

    /**
     * @return list<array{slug: string, title: string, excerpt: string, category_slug: string, category_title: string}>
     */
    public static function popularArticles(int $limit = 5): array
    {
        return array_slice(self::allArticlesFlat(), 0, max(0, $limit));
    }

    /**
     * @return list<array{slug: string, title: string, excerpt: string, category_slug: string, category_title: string}>
     */
    public static function latestArticles(int $limit = 5): array
    {
        $all = self::allArticlesFlat();

        return array_slice(array_reverse($all), 0, max(0, $limit));
    }
}
