<?php

namespace Modules\Billing\Filament\Support;

use Closure;
use Illuminate\Database\Eloquent\Builder;

trait SearchesPatients
{
    /**
     * Search closure for the patient identity columns (mrn, first name, last name)
     * across branches, since `full_name` is a computed accessor rather than a column.
     */
    protected static function patientSearchQuery(): Closure
    {
        return function (Builder $query, string $search): Builder {
            return $query->whereHas('patient', function (Builder $query) use ($search): Builder {
                return $query->withoutGlobalScopes()->where(function (Builder $query) use ($search): Builder {
                    $term = '%'.$search.'%';

                    return $query
                        ->where('mrn', 'like', $term)
                        ->orWhere('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term);
                });
            });
        };
    }
}
