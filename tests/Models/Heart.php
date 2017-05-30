<?php

namespace Yajra\Datatables\Tests\Models;

use Illuminate\Database\Eloquent\Model;

class Heart extends Model
{
    protected $guarded = [];

    public function user()
    {
        return $this->hasOne(User::class);
    }
}
