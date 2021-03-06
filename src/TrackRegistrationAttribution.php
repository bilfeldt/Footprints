<?php

namespace Kyranb\Footprints;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Kyranb\Footprints\Jobs\AssignPreviousVisits;

/**
 * Class TrackRegistrationAttribution.
 *
 * @method static void created(callable $callback)
 */
trait TrackRegistrationAttribution
{
    public static function bootTrackRegistrationAttribution()
    {
        // Add an observer that upon registration will automatically sync up prior visits.
        static::created(function (Model $model) {
            $model->assignPreviousVisits();
        });
    }

    /**
     * Get all of the visits for the user.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function visits()
    {
        return $this->hasMany(Visit::class, config('footprints.column_name'))->orderBy('created_at', 'desc');
    }

    /**
     * Sync visits from the logged in user before they registered.
     *
     * @return void
     */
    public function assignPreviousVisits()
    {
        $job = new AssignPreviousVisits(request(), $this);

        if (config('footprints.async') == true) {
            dispatch($job);
        } else {
            $job->handle();
        }
    }

    /**
     * Assign earlier visits using current request.
     */
    public function trackRegistration(Request $request): void
    {
        Visit::unassignedPreviousVisits($request->footprint())->update(
            [
                config('footprints.column_name') => $this->id,
            ]
        );
    }

    /**
     * The initial attribution data that eventually led to a registration.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function initialAttributionData()
    {
        return $this->visits()->orderBy('created_at', 'asc')->first();
    }

    /**
     * The final attribution data before registration.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function finalAttributionData()
    {
        return $this->visits()->orderBy('created_at', 'desc')->first();
    }
}
