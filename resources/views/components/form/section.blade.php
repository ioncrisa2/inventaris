@props(['title', 'description' => null])

<div class="form-section">
    <h3>{{ $title }}</h3>
    @if($description)
    <p>{{ $description }}</p>
    @endif
</div>
