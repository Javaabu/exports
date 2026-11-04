<?php

namespace Javaabu\Exports\Policies;

use Illuminate\Database\Eloquent\Model;
use Javaabu\Exports\Models\Export;
use Javaabu\Permissions\Policies\ResourcePolicy;

/**
 * Who may see, download, cancel and delete exports
 *
 * | Ability                    | Permission                                                     |
 * | -------------------------- | -------------------------------------------------------------- |
 * | viewAny, create            | view_exports or view_others_exports                            |
 * | view, download             | view_others_exports, or view_exports and owning the export     |
 * | viewOthers                 | view_others_exports                                            |
 * | cancel, delete             | delete_others_exports, or delete_exports and owning the export |
 *
 * Override it with `config('permissions.policies')` or `Gate::policy()` in the app.
 */
class ExportPolicy extends ResourcePolicy
{
    protected string $resource = 'exports';

    protected bool $owned = true;

    public function viewAny(Model $user): bool
    {
        return $this->allows($user, $this->permission('view'), $this->permission('view_others'));
    }

    public function viewOthers(Model $user): bool
    {
        return $this->allows($user, $this->permission('view_others'));
    }

    public function create(Model $user): bool
    {
        return $this->viewAny($user);
    }

    public function view(Model $user, Model $export): bool
    {
        if ($this->allows($user, $this->permission('view_others'))) {
            return true;
        }

        return $this->allows($user, $this->permission('view')) && $this->isOwner($user, $export);
    }

    public function download(Model $user, Model $export): bool
    {
        return $this->view($user, $export);
    }

    public function cancel(Model $user, Model $export): bool
    {
        return $this->delete($user, $export);
    }

    public function update(Model $user, Model $export): bool
    {
        return false;
    }

    public function forceDelete(Model $user, Model $export): bool
    {
        return false;
    }

    public function restore(Model $user, Model $export): bool
    {
        return false;
    }

    public function viewTrash(Model $user): bool
    {
        return false;
    }

    protected function isOwner(Model $user, Model $model): bool
    {
        return $model instanceof Export && $model->isOwnedBy($user);
    }
}
