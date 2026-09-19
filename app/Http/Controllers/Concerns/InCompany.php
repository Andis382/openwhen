<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Company;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Another distributor's row does not exist.
 *
 * 404 rather than 403. A customer list is the most commercially sensitive
 * thing a small distributor owns, and "you may not see this" still confirms
 * that a shop with that id is somebody's customer.
 */
trait InCompany
{
    protected function company(): Company
    {
        $company = auth()->user()?->company;

        if (! $company) {
            throw new NotFoundHttpException();
        }

        return $company;
    }

    protected function ours(?Model $record): Model
    {
        if (! $record || (int) $record->company_id !== (int) $this->company()->id) {
            throw new NotFoundHttpException();
        }

        return $record;
    }
}
