<?php

namespace Yajra\DataTables\Tests\Models;

use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
    protected $guarded = [];

    public function post()
    {
        return $this->belongsTo(Post::class);
    }
}
