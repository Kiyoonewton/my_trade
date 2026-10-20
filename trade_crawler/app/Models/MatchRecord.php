<?php
namespace App\Models;
use MongoDB\Laravel\Eloquent\Model;
class MatchRecord extends Model
{
    protected $connection = 'mongodb';
    // Preserve compatibility with the existing collection.
    protected $table = 'win_or_draws';
    protected $guarded = ['_id'];
}
