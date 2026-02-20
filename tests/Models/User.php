<?php

namespace Yajra\DataTables\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Staudenmeir\EloquentHasManyDeep\HasRelationships;

class User extends Model
{
    use HasRelationships;

    protected $guarded = [];

    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    public function heart()
    {
        return $this->hasOne(Heart::class);
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }

    public function user()
    {
        return $this->morphTo();
    }

    public function comments()
    {
        return $this->hasManyDeep(Comment::class, [Post::class]);
    }

    public function getColorAttribute()
    {
        return $this->color ?? '#000000';
    }
}
