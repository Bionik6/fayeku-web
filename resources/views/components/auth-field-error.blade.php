@props(['name'])

@error($name)
    <p class="auth-error">{{ $message }}</p>
@enderror
