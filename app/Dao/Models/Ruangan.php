<?php

namespace App\Dao\Models;

use App\Dao\Models\Core\SystemModel;

class Ruangan extends SystemModel
{
    protected $connection = 'report';

    protected $perPage = 20;

    protected $table = 'ruangan';

    protected $primaryKey = 'ruangan_id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = ['ruangan_id', 'ruangan_nama', 'ruangan_code'];

    public static function field_name()
    {
        return 'ruangan_nama';
    }

    public function getFieldNameAttribute()
    {
        return $this->{$this->field_name()};
    }
}
