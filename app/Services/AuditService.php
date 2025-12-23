<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditService
{
    /**
     * Get only the changed attributes for audit logging.
     */
    private function getChangedAttributes(Model $model, bool $isUpdate = false): array
    {
        if ($isUpdate) {
            // For updates, only log the dirty (changed) attributes
            $dirty = $model->getDirty();
            $attributes = [];

            foreach ($dirty as $key => $value) {
                $attributes[$key] = $value;
            }
        } else {
            // For creates/deletes, log all attributes
            $attributes = $model->getAttributes();
        }

        // Censor hidden attributes
        $hidden = $model->getHidden();
        foreach ($hidden as $hiddenAttribute) {
            if (array_key_exists($hiddenAttribute, $attributes)) {
                $attributes[$hiddenAttribute] = '***CHANGED***';
            }
        }

        return $attributes;
    }

    /**
     * Get original values for changed attributes only.
     */
    private function getOriginalChangedAttributes(Model $model): array
    {
        $dirty = $model->getDirty();
        $original = $model->getOriginal();
        $attributes = [];

        foreach ($dirty as $key => $value) {
            $attributes[$key] = $original[$key] ?? null;
        }

        // Censor hidden attributes in original values too
        $hidden = $model->getHidden();
        foreach ($hidden as $hiddenAttribute) {
            if (array_key_exists($hiddenAttribute, $attributes)) {
                $attributes[$hiddenAttribute] = '***CHANGED***';
            }
        }

        return $attributes;
    }

    public function log(string $action, Model $model, ?array $oldValues = null, ?array $newValues = null): void
    {
        AuditLog::create([
            'user_id' => Auth::id(),
            'model_type' => get_class($model),
            'model_id' => $model->getKey(),
            'action' => $action,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }

    public function logCreate(Model $model): void
    {
        $newValues = $this->getChangedAttributes($model, false);
        $this->log('create', $model, null, $newValues);
    }

    public function logUpdate(Model $model): void
    {
        $oldValues = $this->getOriginalChangedAttributes($model);
        $newValues = $this->getChangedAttributes($model, true);
        $this->log('update', $model, $oldValues, $newValues);
    }

    public function logDelete(Model $model): void
    {
        $oldValues = $this->getChangedAttributes($model, false);
        $this->log('delete', $model, $oldValues, null);
    }
}
