@props(['success' => 'success', 'error' => 'error'])

@if ($success && session($success))
    <div class="alert alert-success app-alert" role="status">
        <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
        <span>{{ session($success) }}</span>
    </div>
@endif

@if ($error && session($error))
    <div class="alert alert-danger app-alert" role="alert">
        <i class="bi bi-exclamation-circle-fill" aria-hidden="true"></i>
        <span>{{ session($error) }}</span>
    </div>
@endif
