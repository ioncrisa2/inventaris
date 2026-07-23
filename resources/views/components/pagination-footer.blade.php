@props(['paginator'])

@if($paginator->total() > 0)
<div {{ $attributes->merge(['class' => 'card-footer']) }}>
    {{ $paginator->links() }}
</div>
@endif
