@props(['title', 'description' => null])

<div class="form-section">
    <div class="form-section__heading">
        <h3>{{ $title }}</h3>
        @isset($actions)
            <div class="form-section__actions">{{ $actions }}</div>
        @endisset
    </div>
    @if($description)
    <p>{{ $description }}</p>
    @endif
</div>
