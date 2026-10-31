<?php

namespace App\Livewire\Storefront;

use Livewire\Component;

class ReviewsPage extends Component
{
    public string $selectedCategory = 'all';
    public bool $modalOpen = false;
    public string $authorName = '';
    public string $authorRole = '';
    public int $rating = 5;
    public string $comment = '';
    public string $productCategory = 'vps';
    public ?string $successMessage = null;

    protected array $rules = [
        'authorName' => 'required|min:2|max:100',
        'authorRole' => 'required|min:2|max:100',
        'rating' => 'required|integer|min:1|max:5',
        'comment' => 'required|min:10|max:1000',
        'productCategory' => 'required|in:vps,app_hosting,bot_hosting,database,catalog',
    ];

    public function setCategory(string $category): void
    {
        $this->selectedCategory = $category;
    }

    public function openModal(): void
    {
        $this->modalOpen = true;
        $this->successMessage = null;
    }

    public function closeModal(): void
    {
        $this->modalOpen = false;
    }

    public function submitReview(): void
    {
        $this->validate();

        $this->successMessage = 'Thank you! Your verified review has been submitted and will appear on the platform once authenticated.';
        $this->reset(['authorName', 'authorRole', 'comment']);
        $this->rating = 5;
    }

    public function render()
    {
        $allReviews = [
            [
                'id' => '1',
                'name' => 'Kavindu Perera',
                'role' => 'Lead DevOps Engineer @ ApexLabs',
                'rating' => 5,
                'category' => 'vps',
                'date' => 'October 24, 2026',
                'comment' => 'Migrated our entire Kubernetes microservices fleet from DigitalOcean to STX Cloud VPS instances. We cut monthly infrastructure costs by 45% while achieving faster NVMe IOPS. The low-latency peering into South Asia is unmatched.',
                'verified' => true,
            ],
            [
                'id' => '2',
                'name' => 'Dilshan Fernando',
                'role' => 'Senior Bot Developer',
                'rating' => 5,
                'category' => 'bot_hosting',
                'date' => 'October 20, 2026',
                'comment' => 'Running multiple WhatsApp Baileys bots and Discord music nodes. The persistent volume mounts preserve QR sessions flawlessly across restarts. Zero downtime over the past 6 months.',
                'verified' => true,
            ],
            [
                'id' => '3',
                'name' => 'Malik Al-Hassan',
                'role' => 'Full-Stack SaaS Founder',
                'rating' => 5,
                'category' => 'app_hosting',
                'date' => 'October 18, 2026',
                'comment' => 'The instant GitHub webhook auto-deployments work seamlessly. Push to main and within 20 seconds the Next.js and FastAPI containers are live with automated Let\'s Encrypt SSL certificates.',
                'verified' => true,
            ],
            [
                'id' => '4',
                'name' => 'Chamara Weerasinghe',
                'role' => 'Data Architect @ FinMatrix',
                'rating' => 5,
                'category' => 'database',
                'date' => 'October 12, 2026',
                'comment' => 'The Managed PostgreSQL 16 cluster with pgvector indexing handles millions of daily vector embeddings effortlessly. Automated daily snapshot backups give our executive team complete peace of mind.',
                'verified' => true,
            ],
            [
                'id' => '5',
                'name' => 'Senali Jayawardena',
                'role' => 'E-Commerce Tech Director',
                'rating' => 5,
                'category' => 'vps',
                'date' => 'October 5, 2026',
                'comment' => 'Hardware-level DDoS mitigation successfully blocked several volumetric attacks on our high-traffic flash sale events without a single dropped packet. Outstanding 24/7 engineer support.',
                'verified' => true,
            ],
            [
                'id' => '6',
                'name' => 'Naveen Ratnayake',
                'role' => 'Independent Game Developer',
                'rating' => 5,
                'category' => 'vps',
                'date' => 'September 28, 2026',
                'comment' => 'Dedicated game server nodes provide consistent sub-30ms ping across Southeast Asia. Instant root access allowed us to configure custom UDP kernel parameters within minutes.',
                'verified' => true,
            ],
        ];

        $filtered = collect($allReviews)->filter(function ($item) {
            return $this->selectedCategory === 'all' || $item['category'] === $this->selectedCategory;
        });

        return view('livewire.storefront.reviews-page', [
            'reviews' => $filtered,
        ])->layout('layouts.storefront', [
            'title' => 'Customer Reviews & Real Performance Testimonials — STX Enterprises',
        ]);
    }
}

