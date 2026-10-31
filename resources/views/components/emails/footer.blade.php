{{ Illuminate\Mail\Markdown::parse('---') }}

Thank you,<br>
{{ config('app.name') ?? 'STX Hosting' }}

{{ Illuminate\Mail\Markdown::parse('[Contact Support](https://stxhosting.com/docs/contact)') }}
