<a href="{{ route('store.pricing') }}" @if (request()->routeIs('store.pricing')) aria-current="page" @endif>Pricing</a>
<a href="{{ route('store.vps') }}" @if (request()->routeIs('store.vps')) aria-current="page" @endif>Cloud VPS</a>
<a href="{{ route('store.templates') }}" @if (request()->routeIs('store.templates', 'store.app-hosting')) aria-current="page" @endif>Templates</a>
<a href="{{ route('store.email-hosting') }}" @if (request()->routeIs('store.email-hosting')) aria-current="page" @endif>Email</a>
<a href="{{ route('store.features') }}" @if (request()->routeIs('store.features')) aria-current="page" @endif>Features</a>
<a href="{{ route('store.how-it-works') }}" @if (request()->routeIs('store.how-it-works')) aria-current="page" @endif>How It Works</a>
<a href="{{ route('blog.index') }}" @if (request()->routeIs('blog.*')) aria-current="page" @endif>Blog</a>
<a href="{{ route('docs.index') }}" @if (request()->routeIs('docs.*')) aria-current="page" @endif>Docs</a>
<a href="{{ route('status.public') }}" target="_blank" rel="noopener">Status</a>
