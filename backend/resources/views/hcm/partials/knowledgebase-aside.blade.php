@php
    /** @var list<array<string, mixed>> $categories */
    /** @var list<array<string, mixed>> $popularArticles */
    /** @var list<array<string, mixed>> $latestArticles */
    /** @var array<string, mixed>|null $activeCategory */
    $activeCategory = $activeCategory ?? null;
@endphp
<div class="col-xl-4 theiaStickySidebar">
    <div class="card">
        <div class="card-body pb-1">
            <div class="d-flex align-items-center border-bottom mb-3 pb-3">
                <span class="text-dark fs-16 fw-semibold text-truncate">Kategori</span>
            </div>
            @forelse ($categories as $cat)
                <div class="d-flex align-items-center mb-2 pb-1">
                    <i class="ti ti-folder text-primary fs-16 me-1"></i>
                    <a href="{{ route('knowledgebase.category', ['slug' => $cat['slug']]) }}"
                       class="text-gray fs-14 fw-normal text-truncate {{ ($activeCategory['slug'] ?? '') === ($cat['slug'] ?? '') ? 'fw-semibold text-dark' : '' }}">
                        {{ $cat['title'] }}
                        <span class="text-primary">({{ count($cat['articles'] ?? []) }})</span>
                    </a>
                </div>
            @empty
                <p class="fs-13 text-muted mb-0">Belum ada kategori.</p>
            @endforelse
            <div class="mt-2 pt-2 border-top">
                <a href="{{ route('knowledgebase') }}" class="fs-13 fw-medium">Semua kategori</a>
            </div>
        </div>
    </div>
    <div class="card">
        <div class="card-body pb-1">
            <div class="d-flex align-items-center border-bottom mb-3 pb-3">
                <span class="text-dark fs-16 fw-semibold text-truncate">Artikel populer</span>
            </div>
            @foreach ($popularArticles as $row)
                <div class="d-flex align-items-center mb-2 pb-1">
                    <i class="ti ti-file me-1"></i>
                    <a href="{{ route('knowledgebase.article', ['slug' => $row['slug']]) }}" class="text-gray fs-14 fw-normal text-truncate">{{ $row['title'] }}</a>
                </div>
            @endforeach
        </div>
    </div>
    <div class="card">
        <div class="card-body pb-1">
            <div class="d-flex align-items-center border-bottom mb-3 pb-3">
                <span class="text-dark fs-16 fw-semibold text-truncate">Artikel terbaru</span>
            </div>
            @foreach ($latestArticles as $row)
                <div class="d-flex align-items-center mb-2 pb-1">
                    <i class="ti ti-file me-1"></i>
                    <a href="{{ route('knowledgebase.article', ['slug' => $row['slug']]) }}" class="text-gray fs-14 fw-normal text-truncate">{{ $row['title'] }}</a>
                </div>
            @endforeach
        </div>
    </div>
</div>
