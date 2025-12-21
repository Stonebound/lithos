<?php

declare(strict_types=1);

namespace App\Filament\Resources\OverrideRules\Pages;

use App\Filament\Resources\OverrideRules\OverrideRuleResource;
use App\Models\OverrideRule;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditOverrideRule extends EditRecord
{
    protected static string $resource = OverrideRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('toggleEnabled')
                ->label('Toggle Enabled')
                ->action(function (): void {
                    /** @var OverrideRule $rule */
                    $rule = $this->record;
                    $rule->enabled = ! (bool) $rule->enabled;
                    $rule->save();
                    Notification::make()->title('Rule '.($rule->enabled ? 'enabled' : 'disabled'))->success()->send();
                    $this->fillForm();
                }),
            Action::make('raisePriority')
                ->label('Increase Priority')
                ->action(function (): void {
                    /** @var OverrideRule $rule */
                    $rule = $this->record;
                    $rule->priority = (int) $rule->priority + 1;
                    $rule->save();
                    Notification::make()->title('Priority increased')->success()->send();
                    $this->fillForm();
                }),
            Action::make('lowerPriority')
                ->label('Decrease Priority')
                ->action(function (): void {
                    /** @var OverrideRule $rule */
                    $rule = $this->record;
                    $rule->priority = max(0, (int) $rule->priority - 1);
                    $rule->save();
                    Notification::make()->title('Priority decreased')->success()->send();
                    $this->fillForm();
                }),
            DeleteAction::make(),
        ];
    }
}
