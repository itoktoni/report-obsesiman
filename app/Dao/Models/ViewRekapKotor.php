<?php

namespace App\Dao\Models;

use App\Dao\Models\Core\SystemModel;

class ViewRekapKotor extends SystemModel
{
    protected $connection = 'report';

    protected $perPage = 20;

    protected $table = 'view_rekap_kotor';

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

    public function getFieldNameAttribute()
    {
        return $this->{$this->field_name()};
    }
}
