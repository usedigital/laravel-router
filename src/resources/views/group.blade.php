@php /**  @var \UseDigital\LaravelRouter\Models\RouteGroupModel $item */ @endphp

//{{ $item->title }}
Route::group({!! $item->getProps() !!},
function () {

@if($item->itens && $item->itens->count())
    @include("router::template", ["itens" => $item->itens])
@endif

});
