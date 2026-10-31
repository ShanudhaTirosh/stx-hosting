<?php

namespace App\Livewire\Storefront;

use Livewire\Component;

class ContactPage extends Component
{
    public string $name = '';
    public string $email = '';
    public string $department = 'tech';
    public string $subject = '';
    public string $message = '';
    public bool $sent = false;

    protected array $rules = [
        'name' => 'required|min:2|max:100',
        'email' => 'required|email|max:150',
        'department' => 'required|in:tech,sales,billing,abuse',
        'subject' => 'nullable|max:150',
        'message' => 'required|min:10|max:3000',
    ];

    public function submitMessage(): void
    {
        $this->validate();

        // In production this dispatches to support ticketing or notification mail
        $this->sent = true;
        $this->reset(['name', 'email', 'subject', 'message']);
    }

    public function render()
    {
        return view('livewire.storefront.contact-page')
            ->layout('layouts.storefront', [
                'title' => 'Contact Us & 24/7 Live Technical Support — STX Enterprises',
            ]);
    }
}

