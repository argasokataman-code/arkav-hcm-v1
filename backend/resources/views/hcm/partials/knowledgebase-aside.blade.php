@php
    /** @var list<array<string, mixed>> $categories */
    /** @var list<array<string, mixed>> $popularArticles */
    /** @var list<array<string, mixed>> $latestArticles */
    /** @var array<string, mixed>|null $activeCategory */
    $activeCategory = $activeCategory ?? null;
@endphp
<div class="col-xl-4 theiaStickySidebar">
    <div class="card">
        <div class="card-body">
            <div class="border-bottom mb-3 pb-3 d-flex align-items-center justify-content-between">
                <h6 class="mb-0">Semua Kategori</h6>
                <a href="{{ route('knowledgebase') }}" class="fs-12">Lihat semua</a>
            </div>
            <div class="d-flex flex-column list-group settings-list">
                @forelse ($categories as $cat)
                    @php $isActive = ($activeCategory['slug'] ?? '') === ($cat['slug'] ?? ''); @endphp
                    <a href="{{ route('knowledgebase.category', ['slug' => $cat['slug']]) }}" class="d-inline-flex align-items-center justify-content-between rounded py-2 px-3 {{ $isActive ? 'active' : '' }}">
                        <span class="d-inline-flex align-items-center gap-2 text-truncate">
                            <i class="{{ $cat['icon'] ?? 'ti ti-folder' }}"></i>
                            <span class="text-truncate">{{ $cat['title'] }}</span>
                        </span>
                        <span class="badge bg-light text-dark border">{{ count($cat['articles'] ?? []) }}</span>
                    </a>
                @empty
                    <p class="fs-13 text-muted mb-0">Belum ada kategori.</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="border-bottom mb-3 pb-3">
                <h6 class="mb-0">Artikel Populer</h6>
            </div>
            <div class="d-flex flex-column gap-3">
                @foreach ($popularArticles as $row)
                    <div>
                        <div class="fw-medium fs-13"><a href="{{ route('knowledgebase.article', ['slug' => $row['slug']]) }}" class="text-dark">{{ $row['title'] }}</a></div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="border-bottom mb-3 pb-3">
                <h6 class="mb-0">Artikel Terbaru</h6>
            </div>
            <div class="d-flex flex-column gap-3">
                @foreach ($latestArticles as $row)
                    <div>
                        <div class="fw-medium fs-13"><a href="{{ route('knowledgebase.article', ['slug' => $row['slug']]) }}" class="text-dark">{{ $row['title'] }}</a></div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="border-bottom mb-3 pb-3">
                <h6 class="mb-0">Butuh Bantuan?</h6>
            </div>
            <p class="text-muted fs-13">Kalau panduan belum menjawab kebutuhan Anda, lanjutkan lewat tiket support.</p>
            <a href="{{ url('tickets-employee') }}" class="btn btn-primary btn-sm">Buat tiket</a>
        </div>
    </div>
</div>

