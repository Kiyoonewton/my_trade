<?php

namespace App\Trait;

trait WhereTrait
{
  // public function scopeWhereSingle($query, $key, $value)
  // {
  //   return $query->where($key, $value);
  // }

  public function scopeWhereOr($query, $key1, $key2, $value1, $value2)
  {
    return $query->where(function ($query) use ($key1, $key2, $value1, $value2) {
      $query->where($key1, $value1)->orWhere($key2, $value2);
    });
  }

  // public function scopeWhereBetween($query, $key, $value1, $value2)
  // {
  //   return $query->whereBetween($key, [$value1, $value2]);
  // }
}
