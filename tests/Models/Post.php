<?php

namespace Yajra\DataTables\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $dates = ['deleted_at'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function postUser()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function post_user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function heart()
    {
        return $this->hasOneThrough(Heart::class, User::class, 'id', 'user_id', 'user_id', 'id');
    }
}
