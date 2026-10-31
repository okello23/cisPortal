<label class="form-label" for="theme-{{ $fieldId }}">Theme <span class="text-muted fw-normal">(choose one)</span></label>
<input type="hidden" name="_theme_form" value="{{ $fieldId }}">
<select id="theme-{{ $fieldId }}" name="theme_id" class="form-select">
    <option value="">Not assigned to a theme</option>
    @foreach ($themes as $theme)
        <option value="{{ $theme->id }}" @selected((string) (old('_theme_form') === $fieldId ? old('theme_id') : $selectedTheme) === (string) $theme->id)>{{ $theme->code }} — {{ $theme->name }}</option>
    @endforeach
</select>
