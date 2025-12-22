<?php

declare(strict_types=1);

namespace App\Livewire\Releases;

use App\Models\Release;
use Livewire\Component;

class ReleaseLogs extends Component
{
    public Release $release;

    public function render()
    {
        return view('livewire.releases.release-logs', [
            'logs' => $this->release->logs()->latest('id')->get(),
        ]);
    }
}
