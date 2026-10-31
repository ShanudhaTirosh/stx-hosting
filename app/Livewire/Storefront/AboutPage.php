<?php

namespace App\Livewire\Storefront;

use Livewire\Component;

class AboutPage extends Component
{
    public function render()
    {
        return view('livewire.storefront.about-page')
            ->layout('layouts.storefront', [
                'title' => 'About Us — High-Performance Cloud Infrastructure — STX Enterprises',
            ]);
    }
}

