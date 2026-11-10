<div class="m02-pagination">
<small>Hiển thị {{ $paginator->firstItem()??0 }}–{{ $paginator->lastItem()??0 }} / {{ $paginator->total() }} kết quả</small>
@if($paginator->hasPages())<nav aria-label="Phân trang"><ul class="pagination">
<li class="page-item {{ $paginator->onFirstPage()?'disabled':'' }}">@if(!$paginator->onFirstPage())<a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev">Trước</a>@else<span class="page-link">Trước</span>@endif</li>
@foreach($elements as $element)@if(is_string($element))<li class="page-item disabled"><span class="page-link">{{ $element }}</span></li>@else @foreach($element as $page=>$url)<li class="page-item {{ $page===$paginator->currentPage()?'active':'' }}">@if($page===$paginator->currentPage())<span class="page-link" aria-current="page">{{ $page }}</span>@else<a class="page-link" href="{{ $url }}" aria-label="Trang {{ $page }}">{{ $page }}</a>@endif</li>@endforeach @endif @endforeach
<li class="page-item {{ !$paginator->hasMorePages()?'disabled':'' }}">@if($paginator->hasMorePages())<a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next">Sau</a>@else<span class="page-link">Sau</span>@endif</li>
</ul></nav>@endif</div>
