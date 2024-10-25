<?php

namespace App\Dao\Models;

use App\Dao\Models\Core\SystemModel;

class Rs extends SystemModel
{
    protected $connection = 'report';

    protected $perPage = 20;

    protected $table = 'rs';

    protected $primaryKey = 'rs_id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = ['rs_id', 'rs_nama', 'rs_code'];

    public static function field_name()
    {
        return 'rs_nama';
    }

    public function fieldSearching()
    {
        return $this->field_name();
    }

    public function getFieldNameAttribute()
    {
        return $this->{$this->field_name()};
    }

    public function has_ruangan()
    {
        return $this->belongsToMany(Ruangan::class, 'rs_dan_ruangan', Rs::field_primary(), Ruangan::field_primary());
    }

    public function has_jenis()
    {
        return $this->hasMany(Jenis::class, 'jenis_id_rs', $this->field_primary());
    }
}
