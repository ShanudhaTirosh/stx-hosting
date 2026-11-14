{{ $heading }}
==================================================

{!! strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</h1>', '</h2>', '</h3>', '</li>'], "\n", $html)) !!}

@if ($cta_url && $cta_text)
--------------------------------------------------
{{ $cta_text }}: {{ $cta_url }}
@endif

--------------------------------------------------
STX Enterprises — Digital Empire Studio
Negombo, Western Province, Sri Lanka
hello@stxenterprises.com | www.stxenterprises.com
