<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** An owner's edit of one of the built-in emails. No row means the built-in text is used. */
class EmailTemplate extends Model
{
    protected $guarded = [];
}
