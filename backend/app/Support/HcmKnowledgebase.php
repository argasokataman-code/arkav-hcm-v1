<?php

namespace App\Support;

/**
 * Static in-repo help center for /knowledgebase (config/hcm_knowledgebase.php).
 */
final class HcmKnowledgebase
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function categoriesForUser(?object $user): array
    {
        $visible = [];

        foreach (self::categories() as $category) {
            $categoryAudience = self::normalizeAudience($category['visible_to'] ?? ['authenticated']);
            $articles = [];
            foreach (($category['articles'] ?? []) as $article) {
                $articleAudience = self::normalizeAudience($article['visible_to'] ?? $categoryAudience);
                if (! self::userCanAccessAudience($user, $articleAudience)) {
                    continue;
                }

                $copy = $article;
                unset($copy['visible_to']);
                $articles[] = $copy;
            }

            if ($articles === []) {
                continue;
            }

            if (! self::userCanAccessAudience($user, $categoryAudience) && $articles === []) {
                continue;
            }

            $copy = $category;
            $copy['articles'] = $articles;
            unset($copy['visible_to']);
            $visible[] = $copy;
        }

        return $visible;
    }

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
    public static function categoryBySlug(string $slug, ?object $user = null): ?array
    {
        $slug = trim($slug);
        if ($slug === '') {
            return null;
        }

        foreach (self::categoriesForUser($user) as $cat) {
            if (($cat['slug'] ?? '') === $slug) {
                return $cat;
            }
        }

        return null;
    }

    /**
     * @return array{category: array<string, mixed>, article: array<string, mixed>}|null
     */
    public static function resolveArticle(string $articleSlug, ?object $user = null): ?array
    {
        $articleSlug = trim($articleSlug);
        if ($articleSlug === '') {
            return null;
        }

        foreach (self::categoriesForUser($user) as $cat) {
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
    public static function filterForQuery(?string $query, ?object $user = null): array
    {
        $q = strtolower(trim((string) $query));
        if ($q === '') {
            return self::categoriesForUser($user);
        }

        $out = [];
        foreach (self::categoriesForUser($user) as $cat) {
            $catTitle = strtolower((string) ($cat['title'] ?? ''));
            $matchedArticles = [];
            foreach ($cat['articles'] ?? [] as $article) {
                $hay = strtolower(
                    ($article['title'] ?? '')
                    .' '.($article['excerpt'] ?? '')
                    .' '.strip_tags((string) ($article['body_html'] ?? ''))
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

    /**
     * @return list<array{slug: string, title: string, excerpt: string, category_slug: string, category_title: string, reading_minutes: int}>
     */
    public static function guidedTutorials(?object $user = null, int $limit = 6): array
    {
        $slugs = [
            'panduan-admin-harian',
            'panduan-karyawan-harian',
            'checklist-admin-baru',
            'absen-dan-gps',
            'pengajuan-cuti-karyawan',
            'proses-penggajian-bulanan',
        ];

        $rows = [];
        foreach ($slugs as $slug) {
            $resolved = self::resolveArticle($slug, $user);
            if ($resolved === null) {
                continue;
            }

            $rows[] = [
                'slug' => (string) ($resolved['article']['slug'] ?? ''),
                'title' => (string) ($resolved['article']['title'] ?? ''),
                'excerpt' => (string) ($resolved['article']['excerpt'] ?? ''),
                'category_slug' => (string) ($resolved['category']['slug'] ?? ''),
                'category_title' => (string) ($resolved['category']['title'] ?? ''),
                'reading_minutes' => (int) ($resolved['article']['reading_minutes'] ?? 0),
            ];
        }

        return array_slice($rows, 0, max(0, $limit));
    }

    /**
     * @return list<string>
     */
    private static function normalizeAudience(mixed $audience): array
    {
        if (is_string($audience) && $audience !== '') {
            return [strtolower($audience)];
        }

        if (! is_array($audience)) {
            return ['authenticated'];
        }

        $normalized = [];
        foreach ($audience as $entry) {
            if (! is_string($entry) || $entry === '') {
                continue;
            }

            $normalized[] = strtolower($entry);
        }

        return $normalized === [] ? ['authenticated'] : array_values(array_unique($normalized));
    }

    /**
     * @param list<string> $audience
     */
    private static function userCanAccessAudience(?object $user, array $audience): bool
    {
        if ($user === null) {
            return false;
        }

        if (in_array('authenticated', $audience, true)) {
            return true;
        }

        $isGlobalAdmin = method_exists($user, 'isGlobalHcmAdmin') && $user->isGlobalHcmAdmin();
        $isAdmin = method_exists($user, 'isHcmAdmin') && $user->isHcmAdmin();

        if (in_array('global_admin', $audience, true) && $isGlobalAdmin) {
            return true;
        }

        if (in_array('admin', $audience, true) && ($isAdmin || $isGlobalAdmin)) {
            return true;
        }

        if (in_array('employee', $audience, true) && ! $isAdmin && ! $isGlobalAdmin) {
            return true;
        }

        return false;
    }
}
