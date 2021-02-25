@php
    /**
     * @var \UseDigital\LaravelRouter\Models\RouteModel[]|\UseDigital\LaravelRouter\Models\RouteGroupModel[] $itens
     */
@endphp
@foreach($itens as $item)
    @if(get_class($item) == \UseDigital\LaravelRouter\Models\RouteGroupModel::class)@include("router::group", compact("item"))@elseif(get_class($item) == \UseDigital\LaravelRouter\Models\RouteModel::class)@include("router::route", compact("item"))@endif
@endforeach
