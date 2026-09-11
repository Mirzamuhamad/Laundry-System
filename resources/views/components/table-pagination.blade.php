<div class="pagination-bar">
    <p>Menampilkan <strong>{{ $paginator->firstItem() ?? 0 }}–{{ $paginator->lastItem() ?? 0 }}</strong> dari <strong>{{ $paginator->total() }}</strong> data</p>

    @if($paginator->hasPages())
        <nav class="pagination-pages" aria-label="Navigasi halaman">
            <button type="button" wire:click="previousPage('{{ $paginator->getPageName() }}')" wire:loading.attr="disabled" @disabled($paginator->onFirstPage()) aria-label="Halaman sebelumnya">‹</button>

            @foreach($elements as $element)
                @if(is_string($element))
                    <span class="pagination-ellipsis">{{ $element }}</span>
                @endif

                @if(is_array($element))
                    @foreach($element as $page => $url)
                        <button type="button" wire:key="pagination-{{ $paginator->getPageName() }}-{{ $page }}" wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')" class="{{ $page === $paginator->currentPage() ? 'active' : '' }}" @if($page === $paginator->currentPage()) aria-current="page" @endif>{{ $page }}</button>
                    @endforeach
                @endif
            @endforeach

            <button type="button" wire:click="nextPage('{{ $paginator->getPageName() }}')" wire:loading.attr="disabled" @disabled(! $paginator->hasMorePages()) aria-label="Halaman berikutnya">›</button>
        </nav>
    @endif
</div>
