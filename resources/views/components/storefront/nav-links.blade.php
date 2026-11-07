<a href="{{ route('store.pricing') }}" @if (request()->routeIs('store.pricing', 'store.vps', 'store.app-hosting', 'store.web-hosting', 'store.bot-hosting', 'store.databases', 'store.games', 'store.v2ray')) aria-current="page" @endif>Plans</a>
<a href="{{ route('status.public') }}">Status</a>
<a href="{{ route('docs.index') }}" @if (request()->routeIs('docs.*')) aria-current="page" @endif>Help</a>
<a href="{{ route('store.faqs') }}" @if (request()->routeIs('store.faqs')) aria-current="page" @endif>FAQ</a>
<a href="{{ route('store.about') }}" @if (request()->routeIs('store.about')) aria-current="page" @endif>About</a>
<a href="{{ route('store.contact') }}" @if (request()->routeIs('store.contact')) aria-current="page" @endif>Contact</a>
