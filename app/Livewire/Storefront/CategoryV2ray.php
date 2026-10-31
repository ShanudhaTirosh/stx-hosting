<?php

namespace App\Livewire\Storefront;

use Livewire\Component;

class CategoryV2ray extends Component
{
    public string $storeUrl = 'https://store.shanutechx.com';

    public function render()
    {
        return view('livewire.storefront.category-v2ray')
            ->layout('layouts.storefront', [
                'title' => 'V2Ray Proxies & Digital Goods | Official Store Notice — STX Enterprises',
            ]);
    }
}

