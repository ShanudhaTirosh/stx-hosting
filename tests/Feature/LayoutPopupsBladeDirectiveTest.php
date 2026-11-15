<?php

test('layout popups view compiles cleanly without invalid blade directives', function () {
    $view = file_get_contents(resource_path('views/livewire/layout-popups.blade.php'));
    $compiledView = Blade::compileString($view);

    expect($compiledView)
        ->not->toContain('$__env->yieldSection()');
});
