@props([
    'id' => null,
    'label' => null,
    'helper' => null,
    'required' => false,
    'disabled' => false,
])

<div class="w-full">
    @if ($label)
        <label for="{{ $id }}" class="flex gap-1 items-center mb-1 text-xs font-medium text-neutral-700 dark:text-neutral-300 {{ $disabled ? 'opacity-60' : '' }}">
            {{ $label }}
            @if ($required)
                <span class="text-error">*</span>
            @endif
            @if ($helper)
                <x-helper :helper="$helper" />
            @endif
        </label>
    @endif
    <select id="{{ $id }}" @disabled($disabled) @required($required)
        {{ $attributes->merge(['class' => 'select w-full']) }}>
        {{ $slot }}
    </select>
    @if ($id)
        @error($id)
            <label class="label mt-1">
                <span class="text-xs text-error">{{ $message }}</span>
            </label>
        @enderror
    @endif
</div>
