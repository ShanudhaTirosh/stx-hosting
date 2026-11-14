<?php /** @var string $html sanitised initial HTML  @var string $label */ ?>
<div data-editor-wrap>
  <div class="editor-toolbar" role="toolbar" aria-label="Text formatting" data-toolbar hidden>
    <button type="button" class="button button-icon" data-cmd="bold" aria-label="Bold" title="Bold"><strong>B</strong></button>
    <button type="button" class="button button-icon" data-cmd="italic" aria-label="Italic" title="Italic"><em>I</em></button>
    <button type="button" class="button button-icon" data-cmd="underline" aria-label="Underline" title="Underline"><u>U</u></button>
    <button type="button" class="button" data-cmd="insertUnorderedList" aria-label="Bulleted list">• List</button>
    <button type="button" class="button" data-cmd="insertOrderedList" aria-label="Numbered list">1. List</button>
    <button type="button" class="button" data-cmd="quote" aria-label="Quote">Quote</button>
    <button type="button" class="button" data-cmd="link" aria-label="Insert link">Link</button>
    <button type="button" class="button" data-cmd="clear" aria-label="Clear formatting">Clear</button>
  </div>
  <div class="editor" contenteditable="true" role="textbox" aria-multiline="true" aria-label="<?= e($label ?? 'Text') ?>" data-editor hidden><?= $html ?></div>
  <input type="hidden" name="html" data-editor-field value="">
  <noscript><div class="compose-row"><textarea class="input" name="text_fallback" rows="8" aria-label="<?= e($label ?? 'Text') ?>" style="height:auto;padding:8px 12px"></textarea></div></noscript>
</div>
