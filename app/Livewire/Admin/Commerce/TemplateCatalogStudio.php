<?php

namespace App\Livewire\Admin\Commerce;

use App\Livewire\Concerns\RequiresPlatformStaff;
use App\Models\TemplatePermission;
use App\Services\Commerce\HostingCatalogSeederService;
use Livewire\Component;

class TemplateCatalogStudio extends Component
{
    use RequiresPlatformStaff;

    public string $search = '';

    public string $selectedCategory = 'all';

    public bool $showEditModal = false;

    public ?int $editingTemplateId = null;

    // Form fields
    public string $template_name = '';

    public string $display_name = '';

    public string $category = 'cms';

    public string $short_description = '';

    public int $default_port = 80;

    public int $min_ram_mb = 512;

    public float $min_vcpu = 1.0;

    public string $icon_url = '';

    public bool $is_globally_enabled = true;

    public bool $is_featured = false;

    public function mount(): void
    {
        if (TemplatePermission::count() === 0) {
            HostingCatalogSeederService::seedAll();
        }
    }

    public function toggleEnabled(int $id): void
    {
        $template = TemplatePermission::findOrFail($id);
        $template->update(['is_globally_enabled' => ! $template->is_globally_enabled]);
        $status = $template->is_globally_enabled ? 'enabled' : 'disabled';
        $this->dispatch('success', "Template '{$template->template_name}' is now {$status}.");
    }

    public function toggleFeatured(int $id): void
    {
        $template = TemplatePermission::findOrFail($id);
        $template->update(['is_featured' => ! $template->is_featured]);
        $status = $template->is_featured ? 'featured' : 'unfeatured';
        $this->dispatch('success', "Template '{$template->template_name}' is now {$status}.");
    }

    public function openNewModal(): void
    {
        $this->reset([
            'editingTemplateId',
            'template_name',
            'display_name',
            'category',
            'short_description',
            'default_port',
            'min_ram_mb',
            'min_vcpu',
            'icon_url',
            'is_globally_enabled',
            'is_featured',
        ]);
        $this->default_port = 80;
        $this->min_ram_mb = 512;
        $this->min_vcpu = 1.0;
        $this->is_globally_enabled = true;
        $this->showEditModal = true;
    }

    public function edit(int $id): void
    {
        $template = TemplatePermission::findOrFail($id);
        $this->editingTemplateId = $template->id;
        $this->template_name = $template->template_name;
        $this->display_name = $template->display_name ?? '';
        $this->category = $template->category ?? 'cms';
        $this->short_description = $template->short_description ?? '';
        $this->default_port = $template->default_port ?: 80;
        $this->min_ram_mb = $template->min_ram_mb ?: 512;
        $this->min_vcpu = (float) ($template->min_vcpu ?: 1.0);
        $this->icon_url = $template->icon_url ?? '';
        $this->is_globally_enabled = (bool) $template->is_globally_enabled;
        $this->is_featured = (bool) $template->is_featured;

        $this->showEditModal = true;
    }

    public function save(): void
    {
        $rules = [
            'display_name' => 'required|string|max:150',
            'category' => 'required|string|max:50',
            'short_description' => 'nullable|string|max:500',
            'default_port' => 'required|integer|min:1|max:65535',
            'min_ram_mb' => 'required|integer|min:128|max:65536',
            'min_vcpu' => 'required|numeric|min:0.1|max:64.0',
            'icon_url' => 'nullable|string|max:512',
            'is_globally_enabled' => 'boolean',
            'is_featured' => 'boolean',
        ];

        if (! $this->editingTemplateId) {
            $rules['template_name'] = 'required|string|max:100|unique:template_permissions,template_name';
        }

        $this->validate($rules);

        $data = [
            'display_name' => $this->display_name,
            'category' => $this->category,
            'short_description' => $this->short_description,
            'default_port' => $this->default_port,
            'min_ram_mb' => $this->min_ram_mb,
            'min_vcpu' => $this->min_vcpu,
            'icon_url' => $this->icon_url,
            'is_globally_enabled' => $this->is_globally_enabled,
            'is_featured' => $this->is_featured,
            'is_allowed' => true,
        ];

        if ($this->editingTemplateId) {
            $template = TemplatePermission::findOrFail($this->editingTemplateId);
            $template->update($data);
            $this->dispatch('success', "Template '{$template->template_name}' updated successfully.");
        } else {
            $data['template_name'] = strtolower(trim($this->template_name));
            TemplatePermission::create($data);
            $this->dispatch('success', "New template '{$data['template_name']}' created and enabled.");
        }

        $this->showEditModal = false;
    }

    public function render()
    {
        $query = TemplatePermission::whereNull('team_id');

        if ($this->selectedCategory !== 'all') {
            $query->where('category', $this->selectedCategory);
        }

        if (trim($this->search) !== '') {
            $q = strtolower(trim($this->search));
            $query->where(function ($b) use ($q) {
                $b->where('template_name', 'like', "%{$q}%")
                    ->orWhere('display_name', 'like', "%{$q}%")
                    ->orWhere('category', 'like', "%{$q}%");
            });
        }

        $templates = $query->orderByDesc('is_featured')
            ->orderBy('display_name')
            ->get();

        return view('livewire.admin.commerce.template-catalog-studio', [
            'templates' => $templates,
        ]);
    }
}
