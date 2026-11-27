<?php

namespace Yajra\DataTables\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class HumanUser.
 */
class HumanUser extends Model
{
    use SoftDeletes;

    /**
     * @var array
     */
    protected $guarded = [];

    /**
     * @return MorphMany
     */
    public function users()
    {
        return $this->morphMany(User::class, 'user');
    }
}
