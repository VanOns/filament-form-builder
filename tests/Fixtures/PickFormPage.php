<?php

namespace Tests\Fixtures;

use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component;
use VanOns\FilamentFormBuilder\Filament\Forms\Components\FormSelect;

/**
 * A record with a block that picks a form, the way a page has one.
 */
class PickFormPage extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    public Model $record;

    public mixed $picked = null;

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(['form_id' => $this->picked]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([FormSelect::make('form_id')])
            ->statePath('data')
            ->model($this->record);
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}
