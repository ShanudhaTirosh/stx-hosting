<?php

namespace App\Livewire\Storefront;

use Livewire\Component;

class LegalPage extends Component
{
    public string $type = 'terms'; // terms, privacy, refund, cookies

    public function mount(string $type = 'terms'): void
    {
        $this->type = in_array($type, ['terms', 'privacy', 'refund', 'cookies']) ? $type : 'terms';
    }

    public function render()
    {
        $titles = [
            'terms' => 'Terms of Service — STX Enterprises',
            'privacy' => 'Privacy Policy & Data Security — STX Enterprises',
            'refund' => 'Refund & Cancellation Policy — STX Enterprises',
            'cookies' => 'Cookie & Session Policy — STX Enterprises',
        ];

        return view('livewire.storefront.legal-page')
            ->layout('layouts.storefront', [
                'title' => $titles[$this->type] ?? 'Legal Documentation — STX Enterprises',
            ]);
    }
}

