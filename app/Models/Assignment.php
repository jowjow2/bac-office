<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Assignment extends Model
{
    protected $table = 'staff_assignments';

    protected $fillable = [
        'project_id',
        'staff_id',
        'role_in_project',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function staff()
    {
        return $this->belongsTo(User::class, 'staff_id');
    }
}
